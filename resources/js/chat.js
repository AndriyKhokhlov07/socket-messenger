const currentUserId = Number(document.body.dataset.userId ?? 0);
const currentUserName = document.body.dataset.userName ?? "User";
const preselectedContactId = Number(new URLSearchParams(window.location.search).get("contact") ?? 0);

const elements = {
    shell: document.getElementById("messenger-app"),
    contactsList: document.getElementById("contacts-list"),
    contactSearch: document.getElementById("contact-search"),
    onlineSummary: document.getElementById("online-summary"),
    activeAvatar: document.getElementById("active-contact-avatar"),
    activeName: document.getElementById("active-contact-name"),
    activeMeta: document.getElementById("active-contact-meta"),
    typingIndicator: document.getElementById("typing-indicator"),
    messages: document.getElementById("messages"),
    messagesEmpty: document.getElementById("messages-empty"),
    form: document.getElementById("message-form"),
    input: document.getElementById("message-input"),
    sendButton: document.getElementById("send-button"),
    mobileBack: document.getElementById("mobile-back"),
    refreshContacts: document.getElementById("refresh-contacts"),
    contactProfileTrigger: document.getElementById("contact-profile-trigger"),
    contactProfilePanel: document.getElementById("contact-profile-panel"),
    contactProfileContent: document.getElementById("contact-profile-content"),
    contactProfileClose: document.getElementById("contact-profile-close"),
    selectModeToggle: document.getElementById("select-mode-toggle"),
    bulkBlockButton: document.getElementById("bulk-block"),
    bulkReportButton: document.getElementById("bulk-report"),
    bulkDeleteButton: document.getElementById("bulk-delete"),
    attachButton: document.getElementById("attach-button"),
    attachmentInput: document.getElementById("attachment-input"),
    attachmentPreview: document.getElementById("attachment-preview"),
    replyPreview: document.getElementById("reply-preview"),
    voiceButton: document.getElementById("voice-button"),
    emojiButton: document.getElementById("emoji-button"),
    emojiPicker: document.getElementById("emoji-picker"),
};

if (
    !currentUserId ||
    !elements.shell ||
    !elements.contactsList ||
    !elements.messages ||
    !elements.form ||
    !elements.input
) {
    console.log("Chat not initialized on this page");
} else {
    const state = {
        contacts: [],
        activeContactId: null,
        conversations: new Map(),
        onlineIds: new Set(),
        searchTerm: "",
        loadingContacts: false,
        typingIndicatorTimeout: null,
        typingThrottleTimeout: null,
        pendingAttachment: null,
        pendingAttachmentPreviewUrl: null,
        pendingReplyMessage: null,
        emojiPickerOpen: false,
        selectionMode: false,
        selectedContactIds: new Set(),
        openedContactMenuId: null,
        bulkBlockAction: "block",
        mediaRecorder: null,
        mediaStream: null,
        recordingChunks: [],
        dragDepth: 0,
        contactProfileData: null,
        queryContactId: preselectedContactId > 0 ? preselectedContactId : null,
    };

    const emojiPalette = [
        "\u{1F600}", "\u{1F604}", "\u{1F601}", "\u{1F609}", "\u{1F60A}", "\u{1F60D}", "\u{1F618}", "\u{1F914}", "\u{1F60E}", "\u{1F62D}",
        "\u{1F621}", "\u{1F64F}", "\u{1F44D}", "\u{1F44E}", "\u{1F44F}", "\u{1F525}", "\u{2764}\u{FE0F}", "\u{1F499}", "\u{1F4AF}", "\u{1F389}",
        "\u{1F4CE}", "\u{1F4F8}", "\u{1F3AC}", "\u{1F680}",
    ];

    const messageTimeFormatter = new Intl.DateTimeFormat(undefined, {
        hour: "2-digit",
        minute: "2-digit",
    });

    const contactTimeFormatter = new Intl.DateTimeFormat(undefined, {
        month: "short",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });

    const mobileMedia = window.matchMedia("(max-width: 960px)");

    const statusLabelMap = {
        sent: "Sent",
        delivered: "Delivered",
        read: "Read",
    };

    const attachmentLabelMap = {
        image: "Photo",
        video: "Video",
        audio: "Audio",
        file: "File",
    };

    const escapeHtml = (value) =>
        String(value ?? "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");

    const initials = (name) =>
        String(name ?? "")
            .trim()
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((word) => word[0]?.toUpperCase() ?? "")
            .join("") || "U";

    const normalizeContact = (raw) => ({
        id: Number(raw.id),
        name: String(raw.name ?? "Unknown"),
        avatar: String(raw.avatar ?? initials(raw.name)),
        avatar_initials: String(raw.avatar_initials ?? raw.avatar ?? initials(raw.name)),
        avatar_url: String(raw.avatar_url ?? ""),
        email: String(raw.email ?? ""),
        nickname: String(raw.nickname ?? ""),
        phone: String(raw.phone ?? ""),
        bio: String(raw.bio ?? ""),
        is_blocked_by_me: Boolean(raw.is_blocked_by_me),
        has_blocked_me: Boolean(raw.has_blocked_me),
        online: Boolean(raw.online),
        last_seen_at: raw.last_seen_at ?? null,
        unread_count: Number(raw.unread_count ?? 0),
        last_message: raw.last_message ?? null,
        last_message_attachment_name: raw.last_message_attachment_name ?? null,
        last_message_attachment_type: raw.last_message_attachment_type ?? null,
        last_message_status: raw.last_message_status ?? null,
        last_message_is_mine: Boolean(raw.last_message_is_mine),
        last_message_at: raw.last_message_at ?? null,
    });

    const normalizeAttachment = (raw) => {
        if (!raw || typeof raw !== "object") {
            return null;
        }

        const url = String(raw.url ?? "");

        if (!url.length) {
            return null;
        }

        const mime = String(raw.mime ?? "application/octet-stream");
        let type = String(raw.type ?? "");

        if (!["image", "video", "audio", "file"].includes(type)) {
            if (mime.startsWith("image/")) {
                type = "image";
            } else if (mime.startsWith("video/")) {
                type = "video";
            } else if (mime.startsWith("audio/")) {
                type = "audio";
            } else {
                type = "file";
            }
        }

        return {
            url,
            path: String(raw.path ?? ""),
            name: String(raw.name ?? "attachment"),
            mime,
            size: Number(raw.size ?? 0),
            type,
        };
    };

    const avatarContentMarkup = (avatarInitials, avatarUrl) => {
        if (avatarUrl && avatarUrl.length > 0) {
            return `<img class="tg-avatar__img" src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(avatarInitials)}">`;
        }

        return `<span class="tg-avatar__text">${escapeHtml(avatarInitials)}</span>`;
    };

    const avatarMarkup = (avatarInitials, avatarUrl, classes = "tg-avatar tg-avatar--sm") => {
        return `<div class="${classes}">${avatarContentMarkup(avatarInitials, avatarUrl)}</div>`;
    };

    const normalizeMessage = (raw) => ({
        id: Number(raw.id),
        sender_id: Number(raw.sender_id),
        receiver_id: Number(raw.receiver_id),
        reply_to: raw.reply_to ? {
            id: Number(raw.reply_to.id),
            sender_id: Number(raw.reply_to.sender_id),
            body: String(raw.reply_to.body ?? ""),
            attachment_name: String(raw.reply_to.attachment_name ?? ""),
            attachment_type: String(raw.reply_to.attachment_type ?? ""),
        } : null,
        body: String(raw.body ?? ""),
        attachment: normalizeAttachment(raw.attachment),
        status: String(raw.status ?? "sent"),
        created_at: raw.created_at ?? new Date().toISOString(),
        delivered_at: raw.delivered_at ?? null,
        read_at: raw.read_at ?? null,
        is_mine: Number(raw.sender_id) === currentUserId,
    });

    const ensureConversation = (contactId) => {
        if (!state.conversations.has(contactId)) {
            state.conversations.set(contactId, {
                messages: [],
                hasMore: true,
                nextBeforeId: null,
                loading: false,
            });
        }

        return state.conversations.get(contactId);
    };

    const isNearBottom = () =>
        elements.messages.scrollHeight - elements.messages.scrollTop - elements.messages.clientHeight < 120;

    const scrollToBottom = () => {
        elements.messages.scrollTop = elements.messages.scrollHeight;
    };

    const resizeInput = () => {
        elements.input.style.height = "44px";
        elements.input.style.height = `${Math.min(elements.input.scrollHeight, 130)}px`;
    };

    const formatFileSize = (bytes) => {
        const value = Number(bytes ?? 0);

        if (!Number.isFinite(value) || value <= 0) {
            return "0 B";
        }

        const units = ["B", "KB", "MB", "GB"];
        let unitIndex = 0;
        let size = value;

        while (size >= 1024 && unitIndex < units.length - 1) {
            size /= 1024;
            unitIndex += 1;
        }

        return `${size.toFixed(size >= 10 || unitIndex === 0 ? 0 : 1)} ${units[unitIndex]}`;
    };

    const formatMessageTime = (isoDateTime) => {
        const date = new Date(isoDateTime ?? "");

        if (Number.isNaN(date.getTime())) {
            return "";
        }

        return messageTimeFormatter.format(date);
    };

    const formatLastSeen = (isoDateTime) => {
        if (!isoDateTime) {
            return "Offline";
        }

        const timestamp = Date.parse(isoDateTime);

        if (Number.isNaN(timestamp)) {
            return "Offline";
        }

        const diffSeconds = Math.max(0, Math.floor((Date.now() - timestamp) / 1000));

        if (diffSeconds < 45) {
            return "Last seen just now";
        }

        if (diffSeconds < 3600) {
            return `Last seen ${Math.floor(diffSeconds / 60)}m ago`;
        }

        if (diffSeconds < 86400) {
            return `Last seen ${Math.floor(diffSeconds / 3600)}h ago`;
        }

        return `Last seen ${contactTimeFormatter.format(new Date(timestamp))}`;
    };

    const formatContactTime = (isoDateTime) => {
        if (!isoDateTime) {
            return "";
        }

        const date = new Date(isoDateTime);

        if (Number.isNaN(date.getTime())) {
            return "";
        }

        const now = new Date();
        const sameDay = now.toDateString() === date.toDateString();

        if (sameDay) {
            return messageTimeFormatter.format(date);
        }

        return contactTimeFormatter.format(date);
    };

    const statusTick = (status, isMine) => {
        if (!isMine) {
            return "";
        }

        if (status === "sent") {
            return "v";
        }

        return "vv";
    };

    const attachmentPreviewLabel = (type, name = "") => {
        const base = attachmentLabelMap[type] ?? "File";

        if (!name.length) {
            return `Attachment: ${base}`;
        }

        return `Attachment: ${base}: ${name}`;
    };

    const contactBlockLabel = (contact) => {
        if (!contact) {
            return "";
        }

        if (contact.has_blocked_me) {
            return "This user blocked you";
        }

        if (contact.is_blocked_by_me) {
            return "You blocked this user";
        }

        return "";
    };

    const canSendToContact = (contact) => {
        if (!contact) {
            return false;
        }

        return !contact.is_blocked_by_me && !contact.has_blocked_me;
    };

    const renderOnlineSummary = () => {
        const onlineCount = state.contacts.filter((contact) => contact.online).length;
        const total = state.contacts.length;

        elements.onlineSummary.textContent =
            total === 0 ? "No contacts yet" : `${onlineCount} online / ${total} total`;
    };

    const activeContact = () =>
        state.contacts.find((contact) => contact.id === state.activeContactId) ?? null;

    const updateHeader = () => {
        const contact = activeContact();

        if (!contact) {
            elements.activeAvatar.innerHTML = `<span class="tg-avatar__text">?</span>`;
            elements.activeName.textContent = "Select a conversation";
            elements.activeMeta.textContent = "Choose a user from the list";
            if (elements.contactProfileTrigger) {
                elements.contactProfileTrigger.disabled = true;
            }
            state.contactProfileData = null;
            closeContactProfile();
            updateComposerLockState();
            return;
        }

        elements.activeAvatar.innerHTML = avatarContentMarkup(
            contact.avatar_initials,
            contact.avatar_url
        );
        elements.activeName.textContent = contact.name;
        elements.activeMeta.textContent = contactBlockLabel(contact) || (contact.online ? "Online" : formatLastSeen(contact.last_seen_at));
        if (elements.contactProfileTrigger) {
            elements.contactProfileTrigger.disabled = false;
        }
        updateComposerLockState();
    };

    const updateComposerLockState = () => {
        const contact = activeContact();
        const canSend = canSendToContact(contact);

        elements.input.disabled = !canSend;
        elements.sendButton.disabled = !canSend;

        if (elements.attachButton) {
            elements.attachButton.disabled = !canSend;
        }

        if (elements.voiceButton) {
            elements.voiceButton.disabled = !canSend;
        }

        if (elements.emojiButton) {
            elements.emojiButton.disabled = !canSend;
        }

        if (!canSend) {
            clearPendingReply();
            closeEmojiPicker();

            if (state.mediaRecorder) {
                stopVoiceRecording();
            }
        }

        elements.input.placeholder = canSend
            ? "Write a message..."
            : (contact ? (contactBlockLabel(contact) || "Messaging is unavailable") : "Select a conversation");
    };

    const messagePreview = (contact) => {
        const body = String(contact.last_message ?? "").trim();

        if (body.length > 0) {
            return body;
        }

        if (contact.last_message_attachment_type) {
            return attachmentPreviewLabel(
                String(contact.last_message_attachment_type),
                String(contact.last_message_attachment_name ?? "")
            );
        }

        return "No messages yet";
    };

    const contactStatusTick = (contact) => {
        if (!contact.last_message && !contact.last_message_attachment_type) {
            return "";
        }

        if (!contact.last_message_is_mine) {
            return "";
        }

        return statusTick(contact.last_message_status ?? "sent", true);
    };

    const renderContacts = () => {
        const query = state.searchTerm.toLowerCase();
        const filtered = state.contacts.filter((contact) => {
            if (!query.length) {
                return true;
            }

            return (
                contact.name.toLowerCase().includes(query) ||
                contact.email.toLowerCase().includes(query) ||
                contact.nickname.toLowerCase().includes(query) ||
                contact.phone.toLowerCase().includes(query)
            );
        });

        if (!filtered.length) {
            elements.contactsList.innerHTML = `<div class="tg-state">No contacts found.</div>`;
            updateBulkActionButtons();
            renderOnlineSummary();
            return;
        }

        const html = filtered
            .map((contact) => {
                const isActive = contact.id === state.activeContactId;
                const isSelected = state.selectedContactIds.has(contact.id);
                const unreadBadge =
                    contact.unread_count > 0 ? `<span class="tg-unread">${contact.unread_count}</span>` : "";
                const statusSymbol = contactStatusTick(contact);
                const statusClass = contact.last_message_status === "read" ? "is-read" : "";
                const blockedLabel = contactBlockLabel(contact);
                const previewText = blockedLabel.length > 0 ? blockedLabel : messagePreview(contact);
                const menuBlockAction = contact.is_blocked_by_me ? "unblock" : "block";
                const menuBlockLabel = contact.is_blocked_by_me ? "Unblock" : "Block";

                return `
                    <article
                        class="tg-contact ${isActive ? "is-active" : ""} ${state.selectionMode ? "is-select-mode" : ""} ${isSelected ? "is-selected" : ""}"
                        data-contact-id="${contact.id}"
                        role="button"
                        tabindex="0"
                        aria-label="Open chat with ${escapeHtml(contact.name)}">
                        <input class="tg-contact__check" type="checkbox" data-select-contact="${contact.id}" ${isSelected ? "checked" : ""} aria-label="Select ${escapeHtml(contact.name)}">
                        ${avatarMarkup(contact.avatar_initials, contact.avatar_url, "tg-avatar tg-avatar--sm")}
                        <div class="tg-contact__main">
                            <p class="tg-contact__name">
                                ${escapeHtml(contact.name)}
                                <span class="tg-online-dot ${contact.online ? "is-online" : ""}"></span>
                            </p>
                            <p class="tg-contact__preview">${escapeHtml(previewText)}</p>
                        </div>
                        <div class="tg-contact__meta">
                            <div class="tg-contact__time-row">
                                <div class="tg-contact__time">${escapeHtml(formatContactTime(contact.last_message_at))}</div>
                                ${
                                    statusSymbol
                                        ? `<span class="tg-contact__status ${statusClass}">${escapeHtml(statusSymbol)}</span>`
                                        : ""
                                }
                            </div>
                            ${unreadBadge}
                        </div>
                        <div class="tg-contact__actions">
                            <button class="tg-contact__action-btn" type="button" data-contact-menu-toggle="${contact.id}" aria-label="Open actions">&#8942;</button>
                            <div class="tg-contact__menu" data-contact-menu="${contact.id}" ${state.openedContactMenuId === contact.id ? "" : "hidden"}>
                                <button type="button" data-contact-action="${menuBlockAction}" data-contact-id="${contact.id}">${menuBlockLabel}</button>
                                <button type="button" data-contact-action="report" data-contact-id="${contact.id}">Report</button>
                                <button type="button" data-contact-action="delete_conversation" data-contact-id="${contact.id}">Delete chat</button>
                            </div>
                        </div>
                    </article>
                `;
            })
            .join("");

        elements.contactsList.innerHTML = html;
        updateBulkActionButtons();
        renderOnlineSummary();
    };
    const updateBulkActionButtons = () => {
        const selectedContacts = state.contacts.filter((contact) => state.selectedContactIds.has(contact.id));
        const selectedCount = selectedContacts.length;
        const hasSelection = selectedCount > 0;
        const allBlocked = hasSelection && selectedContacts.every((contact) => contact.is_blocked_by_me);
        state.bulkBlockAction = allBlocked ? "unblock" : "block";

        if (elements.bulkBlockButton) {
            elements.bulkBlockButton.disabled = !hasSelection;
            const label = state.bulkBlockAction === "unblock" ? "Unblock" : "Block";
            elements.bulkBlockButton.textContent = hasSelection ? `${label} (${selectedCount})` : label;
        }

        if (elements.bulkReportButton) {
            elements.bulkReportButton.disabled = !hasSelection;
            elements.bulkReportButton.textContent = hasSelection ? `Report (${selectedCount})` : "Report";
        }

        if (elements.bulkDeleteButton) {
            elements.bulkDeleteButton.disabled = !hasSelection;
            elements.bulkDeleteButton.textContent = hasSelection ? `Delete (${selectedCount})` : "Delete";
        }
    };

    const toggleSelectionMode = () => {
        state.selectionMode = !state.selectionMode;
        setContactMenu(null);

        if (!state.selectionMode) {
            state.selectedContactIds.clear();
        }

        if (elements.selectModeToggle) {
            elements.selectModeToggle.textContent = state.selectionMode ? "Cancel" : "Select";
        }

        updateBulkActionButtons();
        renderContacts();
    };

    const setContactMenu = (contactId = null) => {
        if (state.selectionMode) {
            state.openedContactMenuId = null;
        } else {
            state.openedContactMenuId = contactId;
        }

        renderContacts();
    };

    const runContactAction = async (action, contactIds) => {
        if (!contactIds.length) {
            return false;
        }

        if (action === "delete_conversation") {
            const shouldDelete = window.confirm(
                `Delete conversation history for ${contactIds.length} contact(s)?`
            );

            if (!shouldDelete) {
                return false;
            }
        }

        const reason = action === "report"
            ? window.prompt("Optional report reason:", "") ?? ""
            : "";

        try {
            await axios.post("/chat/contacts/actions", {
                action,
                contact_ids: contactIds,
                reason,
            });

            if (action === "delete_conversation") {
                contactIds.forEach((contactId) => {
                    const conversation = state.conversations.get(contactId);

                    if (conversation) {
                        conversation.messages = [];
                        conversation.hasMore = false;
                        conversation.nextBeforeId = null;
                    }
                });
            }

            if (action === "block") {
                state.contacts.forEach((contact) => {
                    if (contactIds.includes(contact.id)) {
                        contact.is_blocked_by_me = true;
                    }
                });
            }

            if (action === "unblock") {
                state.contacts.forEach((contact) => {
                    if (contactIds.includes(contact.id)) {
                        contact.is_blocked_by_me = false;
                    }
                });
            }

            state.selectedContactIds.clear();
            setContactMenu(null);
            updateBulkActionButtons();
            await loadContacts({ preserveSelection: true });
            updateHeader();

            if (state.activeContactId !== null && !elements.contactProfilePanel.hidden) {
                void loadContactProfile(state.activeContactId, { force: true });
            }

            return true;
        } catch (error) {
            console.error("Cannot execute contact action", error);
            return false;
        }
    };

    const mergeMessages = (existingMessages, incomingMessages, prepend = false) => {
        const ordered = prepend
            ? [...incomingMessages, ...existingMessages]
            : [...existingMessages, ...incomingMessages];

        const byId = new Map();
        ordered.forEach((message) => {
            byId.set(message.id, message);
        });

        return [...byId.values()].sort((first, second) => first.id - second.id);
    };

    const renderMessageAttachment = (attachment) => {
        if (!attachment) {
            return "";
        }

        const safeUrl = escapeHtml(attachment.url);
        const safeName = escapeHtml(attachment.name);
        const safeMime = escapeHtml(attachment.mime);
        const safeSize = escapeHtml(formatFileSize(attachment.size));

        if (attachment.type === "image") {
            return `
                <a class="tg-message__image-link" href="${safeUrl}" target="_blank" rel="noopener noreferrer">
                    <img class="tg-message__image" src="${safeUrl}" alt="${safeName}">
                </a>
            `;
        }

        if (attachment.type === "video") {
            return `
                <video class="tg-message__video" src="${safeUrl}" controls preload="metadata"></video>
                <p class="tg-message__caption">${safeName}</p>
            `;
        }

        if (attachment.type === "audio") {
            return `
                <audio class="tg-message__audio" src="${safeUrl}" controls preload="metadata"></audio>
                <p class="tg-message__caption">${safeName}</p>
            `;
        }

        return `
            <a class="tg-message__file" href="${safeUrl}" target="_blank" rel="noopener noreferrer" download>
                <span class="tg-message__file-icon">[file]</span>
                <span class="tg-message__file-meta">
                    <strong>${safeName}</strong>
                    <small>${safeMime}  |  ${safeSize}</small>
                </span>
            </a>
        `;
    };

    const replySnippetText = (replyMessage) => {
        const body = String(replyMessage?.body ?? "").trim();

        if (body.length > 0) {
            return body.length > 84 ? `${body.slice(0, 84)}...` : body;
        }

        if (replyMessage?.attachment_type) {
            return attachmentPreviewLabel(replyMessage.attachment_type, replyMessage.attachment_name ?? "");
        }

        return "Message";
    };

    const renderMessages = ({ forceBottom = false } = {}) => {
        const contactId = state.activeContactId;

        if (!contactId) {
            elements.messages.innerHTML = `<div class="tg-state">Select a user to start messaging.</div>`;
            return;
        }

        const conversation = ensureConversation(contactId);
        const shouldKeepBottom = forceBottom || isNearBottom();

        if (!conversation.messages.length) {
            elements.messages.innerHTML = `<div class="tg-state">No messages yet. Start the conversation.</div>`;
            return;
        }

        const html = conversation.messages
            .map((message) => {
                const mine = message.sender_id === currentUserId;
                const statusClass = message.status === "read" ? "is-read" : "";
                const statusSymbol = statusTick(message.status, mine);
                const statusTitle = mine ? statusLabelMap[message.status] ?? "Sent" : "";
                const text = String(message.body ?? "").trim();
                const textHtml = text.length ? `<p class="tg-message__text">${escapeHtml(text)}</p>` : "";
                const attachmentHtml = renderMessageAttachment(message.attachment);
                const replyHtml = message.reply_to
                    ? `<div class="tg-message__reply"><strong>Reply</strong>${escapeHtml(replySnippetText(message.reply_to))}</div>`
                    : "";

                return `
                    <article class="tg-message ${mine ? "is-mine" : ""}" data-message-id="${message.id}">
                        <div class="tg-message__bubble">
                            ${replyHtml}
                            ${textHtml}
                            ${attachmentHtml}
                            <div class="tg-message__meta">
                                <button class="tg-message__reply-btn" type="button" data-reply-message-id="${message.id}">Reply</button>
                                <time>${escapeHtml(formatMessageTime(message.created_at))}</time>
                                ${
                                    mine
                                        ? `<span class="tg-status ${statusClass}" title="${escapeHtml(statusTitle)}">${statusSymbol}</span>`
                                        : ""
                                }
                            </div>
                        </div>
                    </article>
                `;
            })
            .join("");

        elements.messages.innerHTML = html;

        if (shouldKeepBottom) {
            scrollToBottom();
        }
    };

    const updateContactFromConversation = (contactId) => {
        const contact = state.contacts.find((entry) => entry.id === contactId);

        if (!contact) {
            return;
        }

        const conversation = ensureConversation(contactId);
        const lastMessage = conversation.messages[conversation.messages.length - 1];

        if (!lastMessage) {
            contact.last_message = null;
            contact.last_message_attachment_name = null;
            contact.last_message_attachment_type = null;
            contact.last_message_status = null;
            contact.last_message_is_mine = false;
            contact.last_message_at = null;
            return;
        }

        contact.last_message = lastMessage.body;
        contact.last_message_attachment_name = lastMessage.attachment?.name ?? null;
        contact.last_message_attachment_type = lastMessage.attachment?.type ?? null;
        contact.last_message_status = lastMessage.status;
        contact.last_message_is_mine = lastMessage.sender_id === currentUserId;
        contact.last_message_at = lastMessage.created_at;
    };

    const upsertMessage = (contactId, message, prepend = false) => {
        const conversation = ensureConversation(contactId);
        conversation.messages = mergeMessages(conversation.messages, [message], prepend);
        updateContactFromConversation(contactId);
    };

    const renderPendingAttachment = () => {
        if (!elements.attachmentPreview) {
            return;
        }

        const file = state.pendingAttachment;

        if (!file) {
            elements.attachmentPreview.hidden = true;
            elements.attachmentPreview.innerHTML = "";
            return;
        }

        const mime = String(file.type ?? "");
        const isImage = mime.startsWith("image/");
        const isVideo = mime.startsWith("video/");

        let mediaPreview = "";

        if ((isImage || isVideo) && state.pendingAttachmentPreviewUrl) {
            if (isImage) {
                mediaPreview = `<img class="tg-attachment-preview__thumb" src="${escapeHtml(state.pendingAttachmentPreviewUrl)}" alt="${escapeHtml(file.name)}">`;
            } else {
                mediaPreview = `<video class="tg-attachment-preview__thumb" src="${escapeHtml(state.pendingAttachmentPreviewUrl)}" muted></video>`;
            }
        }

        elements.attachmentPreview.hidden = false;
        elements.attachmentPreview.innerHTML = `
            <div class="tg-attachment-preview__card">
                ${mediaPreview}
                <div class="tg-attachment-preview__meta">
                    <strong>${escapeHtml(file.name)}</strong>
                    <small>${escapeHtml(formatFileSize(file.size))}</small>
                </div>
                <button class="tg-attachment-preview__remove" type="button" data-remove-attachment aria-label="Remove attachment">&times;</button>
            </div>
        `;
    };

    const clearPendingAttachment = () => {
        state.pendingAttachment = null;

        if (state.pendingAttachmentPreviewUrl) {
            URL.revokeObjectURL(state.pendingAttachmentPreviewUrl);
            state.pendingAttachmentPreviewUrl = null;
        }

        if (elements.attachmentInput) {
            elements.attachmentInput.value = "";
        }

        renderPendingAttachment();
    };

    const setPendingAttachment = (file) => {
        clearPendingAttachment();

        if (!file) {
            return;
        }

        state.pendingAttachment = file;

        if (String(file.type ?? "").startsWith("image/") || String(file.type ?? "").startsWith("video/")) {
            state.pendingAttachmentPreviewUrl = URL.createObjectURL(file);
        }

        renderPendingAttachment();
    };

    const closeEmojiPicker = () => {
        state.emojiPickerOpen = false;

        if (elements.emojiPicker) {
            elements.emojiPicker.hidden = true;
        }

        if (elements.emojiButton) {
            elements.emojiButton.classList.remove("is-active");
        }
    };

    const openEmojiPicker = () => {
        state.emojiPickerOpen = true;

        if (elements.emojiPicker) {
            elements.emojiPicker.hidden = false;
        }

        if (elements.emojiButton) {
            elements.emojiButton.classList.add("is-active");
        }
    };

    const toggleEmojiPicker = () => {
        if (!elements.emojiPicker || !elements.emojiButton) {
            return;
        }

        if (state.emojiPickerOpen) {
            closeEmojiPicker();
            return;
        }

        openEmojiPicker();
    };

    const insertEmoji = (emoji) => {
        const textarea = elements.input;
        const start = textarea.selectionStart ?? textarea.value.length;
        const end = textarea.selectionEnd ?? textarea.value.length;
        const value = textarea.value;

        textarea.value = `${value.slice(0, start)}${emoji}${value.slice(end)}`;

        const nextCursor = start + emoji.length;
        textarea.selectionStart = nextCursor;
        textarea.selectionEnd = nextCursor;

        textarea.focus();
        resizeInput();
    };

    const renderEmojiPicker = () => {
        if (!elements.emojiPicker) {
            return;
        }

        elements.emojiPicker.innerHTML = emojiPalette
            .map((emoji) => `<button type="button" class="tg-emoji-picker__item" data-emoji="${escapeHtml(emoji)}">${escapeHtml(emoji)}</button>`)
            .join("");
    };

    const extractReplyPayload = (message) => ({
        id: Number(message.id),
        sender_id: Number(message.sender_id),
        body: String(message.body ?? ""),
        attachment_name: String(message.attachment?.name ?? ""),
        attachment_type: String(message.attachment?.type ?? ""),
    });

    const clearPendingReply = () => {
        state.pendingReplyMessage = null;

        if (elements.replyPreview) {
            elements.replyPreview.hidden = true;
            elements.replyPreview.innerHTML = "";
        }
    };

    const renderReplyPreview = () => {
        if (!elements.replyPreview) {
            return;
        }

        if (!state.pendingReplyMessage) {
            elements.replyPreview.hidden = true;
            elements.replyPreview.innerHTML = "";
            return;
        }

        const authorName = state.pendingReplyMessage.sender_id === currentUserId
            ? "You"
            : (activeContact()?.name ?? "User");

        elements.replyPreview.hidden = false;
        elements.replyPreview.innerHTML = `
            <span class="tg-reply-preview__text">
                <strong>Replying to ${escapeHtml(authorName)}</strong>
                ${escapeHtml(replySnippetText(state.pendingReplyMessage))}
            </span>
            <button class="tg-reply-preview__close" type="button" data-clear-reply aria-label="Cancel reply">&times;</button>
        `;
    };

    const setPendingReplyByMessageId = (messageId) => {
        if (!state.activeContactId) {
            return;
        }

        const conversation = ensureConversation(state.activeContactId);
        const message = conversation.messages.find((entry) => entry.id === messageId);

        if (!message) {
            return;
        }

        state.pendingReplyMessage = extractReplyPayload(message);
        renderReplyPreview();
        elements.input.focus();
    };

    const closeContactProfile = () => {
        if (!elements.contactProfilePanel) {
            return;
        }

        elements.contactProfilePanel.hidden = true;
    };

    const renderProfileAttachment = (attachment) => {
        const name = String(attachment?.name ?? "attachment");
        const type = String(attachment?.type ?? "file");
        const url = String(attachment?.url ?? "");
        const safeName = escapeHtml(name);
        const safeUrl = escapeHtml(url);

        if (type === "image") {
            return `
                <a class="tg-contact-card__media-item" href="${safeUrl}" target="_blank" rel="noopener noreferrer">
                    <img src="${safeUrl}" alt="${safeName}">
                </a>
            `;
        }

        if (type === "video") {
            return `
                <a class="tg-contact-card__media-item" href="${safeUrl}" target="_blank" rel="noopener noreferrer">
                    <video src="${safeUrl}" muted preload="metadata"></video>
                </a>
            `;
        }

        const label = type === "audio" ? "Audio" : "File";

        return `
            <a class="tg-contact-card__media-item tg-contact-card__media-link" href="${safeUrl}" target="_blank" rel="noopener noreferrer">
                ${escapeHtml(label)}
            </a>
        `;
    };

    const renderContactProfile = () => {
        if (!elements.contactProfileContent) {
            return;
        }

        if (!state.contactProfileData) {
            elements.contactProfileContent.innerHTML = `<div class="tg-state">Select a contact to view details.</div>`;
            return;
        }

        const data = state.contactProfileData;
        const stats = data.shared_stats ?? {};
        const recentAttachments = Array.isArray(data.recent_attachments) ? data.recent_attachments : [];
        const avatarInitials = String(data.avatar_initials ?? initials(data.name));
        const avatarUrl = String(data.avatar_url ?? "");
        const blockLabel = data.has_blocked_me
            ? "This user blocked you"
            : (data.is_blocked_by_me ? "You blocked this user" : "No restrictions");
        const blockAction = data.is_blocked_by_me ? "unblock" : "block";
        const blockActionLabel = data.is_blocked_by_me ? "Unblock user" : "Block user";

        elements.contactProfileContent.innerHTML = `
            <article class="tg-contact-card">
                <div class="tg-contact-card__row">
                    ${avatarMarkup(avatarInitials, avatarUrl, "tg-avatar tg-avatar--sm")}
                    <div class="tg-contact-card__meta">
                        <strong>${escapeHtml(String(data.name ?? "Unknown user"))}</strong>
                        <small>${escapeHtml(String(data.nickname ?? "No nickname"))}</small>
                    </div>
                </div>
                <p class="tg-contact-card__bio">${escapeHtml(blockLabel)}</p>
                <p class="tg-contact-card__bio">${escapeHtml(String(data.bio ?? "No bio provided."))}</p>
                <p class="tg-contact-card__bio">Email: ${escapeHtml(String(data.email ?? "N/A"))}</p>
                <p class="tg-contact-card__bio">Phone: ${escapeHtml(String(data.phone ?? "N/A"))}</p>
                <div class="tg-contact-card__actions">
                    <button type="button" data-profile-action="${blockAction}">${escapeHtml(blockActionLabel)}</button>
                    <button type="button" data-profile-action="report">Report</button>
                    <button type="button" data-profile-action="delete_conversation" class="tg-danger">Delete chat</button>
                </div>
            </article>

            <article class="tg-contact-card">
                <div class="tg-contact-card__stats">
                    <div class="tg-contact-card__stat"><strong>${Number(stats.images ?? 0)}</strong><span>Images</span></div>
                    <div class="tg-contact-card__stat"><strong>${Number(stats.videos ?? 0)}</strong><span>Videos</span></div>
                    <div class="tg-contact-card__stat"><strong>${Number(stats.audio ?? 0)}</strong><span>Audio</span></div>
                    <div class="tg-contact-card__stat"><strong>${Number(stats.files ?? 0)}</strong><span>Files</span></div>
                </div>
            </article>

            <article class="tg-contact-card">
                <strong>Shared media</strong>
                <div class="tg-contact-card__media-grid">
                    ${recentAttachments.length
                        ? recentAttachments.map((attachment) => renderProfileAttachment(attachment)).join("")
                        : `<div class="tg-state">No shared files yet.</div>`}
                </div>
            </article>
        `;
    };

    const loadContactProfile = async (contactId, { force = false } = {}) => {
        if (!contactId || !elements.contactProfileContent) {
            return;
        }

        if (!force && state.contactProfileData?.id === contactId) {
            return;
        }

        elements.contactProfileContent.innerHTML = `<div class="tg-state">Loading contact info...</div>`;

        try {
            const response = await axios.get(`/chat/contacts/${contactId}/profile`);
            state.contactProfileData = response.data?.data ?? null;
            renderContactProfile();
        } catch (error) {
            console.error("Cannot load contact profile", error);
            elements.contactProfileContent.innerHTML = `<div class="tg-state">Cannot load contact info.</div>`;
        }
    };

    const openContactProfile = async () => {
        if (!state.activeContactId || !elements.contactProfilePanel) {
            return;
        }

        elements.contactProfilePanel.hidden = false;
        await loadContactProfile(state.activeContactId, { force: true });
    };

    const stopMediaTracks = () => {
        if (!state.mediaStream) {
            return;
        }

        state.mediaStream.getTracks().forEach((track) => track.stop());
        state.mediaStream = null;
    };

    const setRecordingState = (isRecording) => {
        if (!elements.voiceButton) {
            return;
        }

        elements.voiceButton.classList.toggle("is-recording", isRecording);
        elements.voiceButton.setAttribute(
            "aria-label",
            isRecording ? "Stop voice recording" : "Record voice message"
        );
    };

    const stopVoiceRecording = () => {
        if (!state.mediaRecorder) {
            return;
        }

        if (state.mediaRecorder.state !== "inactive") {
            state.mediaRecorder.stop();
        }

        state.mediaRecorder = null;
        setRecordingState(false);
    };

    const startVoiceRecording = async () => {
        if (!window.MediaRecorder || !navigator.mediaDevices?.getUserMedia) {
            window.alert("Voice recording is not supported in this browser.");
            return;
        }

        try {
            state.mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            state.recordingChunks = [];

            const candidates = [
                "audio/webm;codecs=opus",
                "audio/webm",
                "audio/ogg;codecs=opus",
                "audio/ogg",
                "audio/mp4",
            ];

            const supportedMime = candidates.find((mime) => {
                return typeof MediaRecorder.isTypeSupported === "function"
                    ? MediaRecorder.isTypeSupported(mime)
                    : false;
            });

            const recorder = supportedMime
                ? new MediaRecorder(state.mediaStream, { mimeType: supportedMime })
                : new MediaRecorder(state.mediaStream);

            recorder.addEventListener("dataavailable", (event) => {
                if (event.data && event.data.size > 0) {
                    state.recordingChunks.push(event.data);
                }
            });

            recorder.addEventListener("stop", () => {
                const mimeType = recorder.mimeType || state.recordingChunks[0]?.type || "audio/webm";
                const extension = mimeType.includes("ogg") ? "ogg" : (mimeType.includes("mp4") ? "m4a" : "webm");

                if (state.recordingChunks.length > 0) {
                    const blob = new Blob(state.recordingChunks, { type: mimeType });
                    const file = new File([blob], `voice-${Date.now()}.${extension}`, { type: mimeType });
                    setPendingAttachment(file);
                }

                state.recordingChunks = [];
                stopMediaTracks();
            });

            recorder.start();
            state.mediaRecorder = recorder;
            setRecordingState(true);
        } catch (error) {
            console.error("Cannot start voice recording", error);
            stopMediaTracks();
            setRecordingState(false);
        }
    };

    const toggleVoiceRecording = () => {
        if (state.mediaRecorder && state.mediaRecorder.state !== "inactive") {
            stopVoiceRecording();
            return;
        }

        void startVoiceRecording();
    };

    const loadContacts = async ({ preserveSelection = true } = {}) => {
        if (state.loadingContacts) {
            return;
        }

        state.loadingContacts = true;

        try {
            const previousActiveContactId = preserveSelection ? state.activeContactId : null;
            const response = await axios.get("/chat/contacts");
            const contacts = (response.data?.data ?? []).map(normalizeContact);

            contacts.forEach((contact) => {
                if (state.onlineIds.has(contact.id)) {
                    contact.online = true;
                }
            });

            state.contacts = contacts;
            const availableIds = new Set(state.contacts.map((contact) => contact.id));
            state.selectedContactIds = new Set(
                [...state.selectedContactIds].filter((contactId) => availableIds.has(contactId))
            );
            state.openedContactMenuId = state.openedContactMenuId !== null && availableIds.has(state.openedContactMenuId)
                ? state.openedContactMenuId
                : null;

            const previousContactId = state.activeContactId;

            if (
                previousActiveContactId !== null &&
                state.contacts.some((contact) => contact.id === previousActiveContactId)
            ) {
                state.activeContactId = previousActiveContactId;
            } else if (
                state.queryContactId !== null &&
                state.contacts.some((contact) => contact.id === state.queryContactId)
            ) {
                state.activeContactId = state.queryContactId;
                state.queryContactId = null;
            } else if (state.contacts.length) {
                state.activeContactId = state.contacts[0].id;
            } else {
                state.activeContactId = null;
            }

            if (state.activeContactId !== previousContactId) {
                clearPendingReply();
                clearPendingAttachment();
            }

            renderContacts();
            updateHeader();

            if (state.activeContactId !== null) {
                await loadMessages(state.activeContactId, { reset: true });
                if (!elements.contactProfilePanel.hidden) {
                    await loadContactProfile(state.activeContactId, { force: true });
                }
            } else {
                state.contactProfileData = null;
                closeContactProfile();
            }
        } catch (error) {
            console.error("Cannot load contacts", error);
            elements.contactsList.innerHTML = `<div class="tg-state">Cannot load contacts.</div>`;
        } finally {
            state.loadingContacts = false;
        }
    };

    const loadMessages = async (contactId, { prepend = false, reset = false } = {}) => {
        if (!contactId) {
            return;
        }

        const conversation = ensureConversation(contactId);

        if (conversation.loading) {
            return;
        }

        if (prepend && !conversation.hasMore) {
            return;
        }

        conversation.loading = true;

        const params = { limit: 30 };

        if (prepend && conversation.nextBeforeId) {
            params.before_id = conversation.nextBeforeId;
        }

        const previousHeight = elements.messages.scrollHeight;
        const previousTop = elements.messages.scrollTop;

        try {
            const response = await axios.get(`/messages/${contactId}`, { params });
            const data = (response.data?.data ?? []).map(normalizeMessage);
            const meta = response.data?.meta ?? {};

            if (prepend) {
                conversation.messages = mergeMessages(conversation.messages, data, true);
            } else if (reset) {
                conversation.messages = data;
            } else {
                conversation.messages = mergeMessages(conversation.messages, data, false);
            }

            conversation.hasMore = Boolean(meta.has_more);
            conversation.nextBeforeId = meta.next_before_id ? Number(meta.next_before_id) : null;

            const contact = state.contacts.find((entry) => entry.id === contactId);

            if (contact) {
                contact.unread_count = 0;
            }

            renderMessages({ forceBottom: !prepend });
            renderContacts();
            updateHeader();

            if (prepend) {
                const newHeight = elements.messages.scrollHeight;
                elements.messages.scrollTop = newHeight - previousHeight + previousTop;
            }
        } catch (error) {
            console.error("Cannot load conversation", error);
        } finally {
            conversation.loading = false;
        }
    };

    const setMobileChatState = (openConversation) => {
        if (!mobileMedia.matches) {
            return;
        }

        elements.shell.classList.toggle("is-mobile-chat-open", openConversation);
    };

    const openContact = async (contactId) => {
        const contact = state.contacts.find((entry) => entry.id === contactId);

        if (!contact) {
            return;
        }

        const changedContact = state.activeContactId !== contactId;
        state.activeContactId = contactId;
        contact.unread_count = 0;
        setContactMenu(null);

        if (changedContact) {
            state.contactProfileData = null;
            clearPendingReply();
            clearPendingAttachment();
        }

        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set("contact", String(contactId));
        window.history.replaceState({}, "", currentUrl);

        renderContacts();
        updateHeader();
        renderMessages();
        setMobileChatState(true);

        await loadMessages(contactId, { reset: true });

        if (!elements.contactProfilePanel.hidden) {
            await loadContactProfile(contactId, { force: true });
        }
    };

    const updateMessageStatusesInMemory = (messageIds, status, updatedAt = null) => {
        const ids = new Set(messageIds.map(Number));
        let activeConversationChanged = false;

        state.conversations.forEach((conversation, contactId) => {
            let changed = false;

            conversation.messages = conversation.messages.map((message) => {
                if (!ids.has(message.id)) {
                    return message;
                }

                changed = true;

                const patch = {
                    ...message,
                    status,
                };

                if (status === "delivered" && !patch.delivered_at) {
                    patch.delivered_at = updatedAt ?? new Date().toISOString();
                }

                if (status === "read") {
                    patch.delivered_at = patch.delivered_at ?? updatedAt ?? new Date().toISOString();
                    patch.read_at = patch.read_at ?? updatedAt ?? new Date().toISOString();
                }

                return patch;
            });

            if (changed) {
                updateContactFromConversation(contactId);

                if (contactId === state.activeContactId) {
                    activeConversationChanged = true;
                }
            }
        });

        if (activeConversationChanged) {
            renderMessages();
        }

        renderContacts();
    };

    const acknowledgeStatus = async (messageId, status) => {
        try {
            await axios.patch(`/messages/${messageId}/status`, { status });
        } catch (error) {
            console.error("Cannot acknowledge message status", error);
        }
    };

    const handleIncomingMessage = (rawMessage) => {
        const message = normalizeMessage(rawMessage);
        const contactId =
            message.sender_id === currentUserId ? message.receiver_id : message.sender_id;

        upsertMessage(contactId, message, false);

        const contact = state.contacts.find((entry) => entry.id === contactId);

        if (!contact) {
            void loadContacts({ preserveSelection: true });
            return;
        }

        if (contactId === state.activeContactId) {
            contact.unread_count = 0;
            renderMessages({ forceBottom: true });

            if (message.sender_id !== currentUserId) {
                void acknowledgeStatus(message.id, "read");
            }
        } else if (message.sender_id !== currentUserId) {
            contact.unread_count += 1;
            void acknowledgeStatus(message.id, "delivered");
        }

        renderContacts();
        updateHeader();
    };

    const setTypingIndicator = (text) => {
        elements.typingIndicator.textContent = text;
        elements.typingIndicator.hidden = false;

        if (state.typingIndicatorTimeout) {
            clearTimeout(state.typingIndicatorTimeout);
        }

        state.typingIndicatorTimeout = setTimeout(() => {
            elements.typingIndicator.hidden = true;
            elements.typingIndicator.textContent = "";
        }, 2200);
    };

    const emitTyping = async () => {
        if (!state.activeContactId || !canSendToContact(activeContact())) {
            return;
        }

        if (state.typingThrottleTimeout) {
            return;
        }

        state.typingThrottleTimeout = setTimeout(() => {
            state.typingThrottleTimeout = null;
        }, 900);

        try {
            await axios.post("/messages/typing", {
                receiver_id: state.activeContactId,
            });
        } catch (error) {
            console.error("Cannot emit typing event", error);
        }
    };

    const sendMessage = async () => {
        const body = elements.input.value.trim();
        const attachment = state.pendingAttachment;
        const contact = activeContact();

        if (!state.activeContactId || !canSendToContact(contact) || (!body.length && !attachment)) {
            return;
        }

        elements.sendButton.disabled = true;

        try {
            const formData = new FormData();
            formData.append("receiver_id", String(state.activeContactId));
            formData.append("body", body);

            if (attachment) {
                formData.append("attachment", attachment, attachment.name);
            }

            if (state.pendingReplyMessage?.id) {
                formData.append("reply_to_message_id", String(state.pendingReplyMessage.id));
            }

            const response = await axios.post("/messages", formData, {
                headers: {
                    "Content-Type": "multipart/form-data",
                },
            });

            const message = normalizeMessage(response.data?.data ?? response.data);
            upsertMessage(state.activeContactId, message, false);
            renderMessages({ forceBottom: true });
            renderContacts();

            elements.input.value = "";
            resizeInput();
            clearPendingAttachment();
            clearPendingReply();
            closeEmojiPicker();
        } catch (error) {
            console.error("Cannot send message", error);
            if (error?.response?.status === 403) {
                await loadContacts({ preserveSelection: true });
            }
        } finally {
            elements.sendButton.disabled = false;
        }
    };

    const updateOnlineState = () => {
        state.contacts.forEach((contact) => {
            contact.online = state.onlineIds.has(contact.id);
        });

        renderContacts();
        updateHeader();
    };

    const setupEcho = () => {
        if (!window.Echo) {
            return;
        }

        window.Echo.join("messenger.presence")
            .here((users) => {
                state.onlineIds = new Set(users.map((user) => Number(user.id)));
                updateOnlineState();
            })
            .joining((user) => {
                state.onlineIds.add(Number(user.id));
                updateOnlineState();
            })
            .leaving((user) => {
                state.onlineIds.delete(Number(user.id));
                updateOnlineState();
            });

        window.Echo.private(`chat.${currentUserId}`)
            .listen("MessageSent", (event) => {
                if (event?.message) {
                    handleIncomingMessage(event.message);
                }
            })
            .listen("MessageStatusUpdated", (event) => {
                updateMessageStatusesInMemory(
                    event?.message_ids ?? [],
                    event?.status ?? "sent",
                    event?.updated_at ?? null
                );
            })
            .listen("UserTyping", (event) => {
                const senderId = Number(event?.sender_id);

                if (!senderId || senderId !== state.activeContactId) {
                    return;
                }

                setTypingIndicator(`${event.sender_name ?? "User"} is typing...`);
            });
    };

    elements.contactsList.addEventListener("click", (event) => {
        const target = event.target;
        if (!(target instanceof Element)) {
            return;
        }

        const menuToggleButton = target.closest("[data-contact-menu-toggle]");
        if (menuToggleButton) {
            event.preventDefault();
            event.stopPropagation();
            const contactId = Number(menuToggleButton.dataset.contactMenuToggle);
            if (contactId) {
                setContactMenu(state.openedContactMenuId === contactId ? null : contactId);
            }
            return;
        }

        const menuActionButton = target.closest("[data-contact-action]");
        if (menuActionButton) {
            event.preventDefault();
            event.stopPropagation();
            const action = String(menuActionButton.dataset.contactAction ?? "");
            const contactId = Number(menuActionButton.dataset.contactId ?? 0);
            if (action && contactId) {
                void runContactAction(action, [contactId]);
            }
            return;
        }

        const selectCheckbox = target.closest("[data-select-contact]");
        if (selectCheckbox) {
            event.stopPropagation();
            const contactId = Number(selectCheckbox.dataset.selectContact ?? 0);
            if (!contactId) {
                return;
            }

            if (!state.selectionMode) {
                selectCheckbox.checked = false;
                return;
            }

            if (selectCheckbox.checked) {
                state.selectedContactIds.add(contactId);
            } else {
                state.selectedContactIds.delete(contactId);
            }

            updateBulkActionButtons();
            renderContacts();
            return;
        }

        const item = target.closest(".tg-contact[data-contact-id]");
        if (!item) {
            return;
        }

        const contactId = Number(item.dataset.contactId);

        if (!contactId) {
            return;
        }

        if (state.selectionMode) {
            if (state.selectedContactIds.has(contactId)) {
                state.selectedContactIds.delete(contactId);
            } else {
                state.selectedContactIds.add(contactId);
            }

            updateBulkActionButtons();
            renderContacts();
            return;
        }

        void openContact(contactId);
    });

    elements.contactsList.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") {
            return;
        }

        const item = event.target.closest(".tg-contact[data-contact-id]");
        if (!item) {
            return;
        }

        event.preventDefault();
        const contactId = Number(item.dataset.contactId ?? 0);

        if (contactId && !state.selectionMode) {
            void openContact(contactId);
        }
    });

    elements.contactSearch.addEventListener("input", (event) => {
        state.searchTerm = String(event.target.value ?? "").trim();
        renderContacts();
    });

    elements.form.addEventListener("submit", (event) => {
        event.preventDefault();
        void sendMessage();
    });

    elements.input.addEventListener("input", () => {
        resizeInput();

        if (elements.input.value.trim().length > 0) {
            void emitTyping();
        }
    });

    elements.input.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" || event.shiftKey) {
            return;
        }

        event.preventDefault();
        void sendMessage();
    });

    elements.messages.addEventListener("scroll", () => {
        if (elements.messages.scrollTop < 80 && state.activeContactId !== null) {
            void loadMessages(state.activeContactId, { prepend: true });
        }
    });

    elements.messages.addEventListener("click", (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const replyButton = event.target.closest("[data-reply-message-id]");
        if (!replyButton) {
            return;
        }

        const messageId = Number(replyButton.dataset.replyMessageId ?? 0);
        if (!messageId) {
            return;
        }

        setPendingReplyByMessageId(messageId);
    });

    elements.mobileBack.addEventListener("click", () => {
        setMobileChatState(false);
    });

    elements.refreshContacts.addEventListener("click", () => {
        void loadContacts({ preserveSelection: true });
    });

    if (elements.selectModeToggle) {
        elements.selectModeToggle.addEventListener("click", () => {
            toggleSelectionMode();
        });
    }

    if (elements.bulkBlockButton) {
        elements.bulkBlockButton.addEventListener("click", () => {
            const ids = [...state.selectedContactIds];
            void runContactAction(state.bulkBlockAction, ids);
        });
    }

    if (elements.bulkReportButton) {
        elements.bulkReportButton.addEventListener("click", () => {
            const ids = [...state.selectedContactIds];
            void runContactAction("report", ids);
        });
    }

    if (elements.bulkDeleteButton) {
        elements.bulkDeleteButton.addEventListener("click", () => {
            const ids = [...state.selectedContactIds];
            if (!ids.length) {
                return;
            }
            void runContactAction("delete_conversation", ids);
        });
    }

    if (elements.attachButton && elements.attachmentInput) {
        elements.attachButton.addEventListener("click", () => {
            elements.attachmentInput.click();
        });

        elements.attachmentInput.addEventListener("change", (event) => {
            const [file] = event.target.files ?? [];
            setPendingAttachment(file ?? null);
        });
    }

    if (elements.attachmentPreview) {
        elements.attachmentPreview.addEventListener("click", (event) => {
            if (!(event.target instanceof Element)) {
                return;
            }

            const removeButton = event.target.closest("[data-remove-attachment]");

            if (!removeButton) {
                return;
            }

            clearPendingAttachment();
        });
    }

    if (elements.replyPreview) {
        elements.replyPreview.addEventListener("click", (event) => {
            if (!(event.target instanceof Element)) {
                return;
            }

            const closeButton = event.target.closest("[data-clear-reply]");
            if (!closeButton) {
                return;
            }

            clearPendingReply();
        });
    }

    if (elements.voiceButton) {
        elements.voiceButton.addEventListener("click", () => {
            if (elements.voiceButton.disabled) {
                return;
            }

            toggleVoiceRecording();
        });
    }

    if (elements.contactProfileTrigger) {
        elements.contactProfileTrigger.addEventListener("click", () => {
            void openContactProfile();
        });
    }

    if (elements.contactProfileClose) {
        elements.contactProfileClose.addEventListener("click", () => {
            closeContactProfile();
        });
    }

    if (elements.contactProfileContent) {
        elements.contactProfileContent.addEventListener("click", (event) => {
            if (!(event.target instanceof Element)) {
                return;
            }

            const actionButton = event.target.closest("[data-profile-action]");
            if (!actionButton || !state.activeContactId) {
                return;
            }

            const action = String(actionButton.dataset.profileAction ?? "");
            if (!action.length) {
                return;
            }

            void runContactAction(action, [state.activeContactId]);
        });
    }

    const setDragState = (isActive) => {
        state.dragDepth = isActive ? Math.max(1, state.dragDepth) : 0;
        elements.form.classList.toggle("is-drag-over", isActive);
    };

    const handleDropFile = (event) => {
        if (!canSendToContact(activeContact())) {
            return;
        }

        const [file] = event.dataTransfer?.files ?? [];
        if (!file) {
            return;
        }

        setPendingAttachment(file);
    };

    [elements.messages, elements.form].forEach((dropZone) => {
        if (!dropZone) {
            return;
        }

        dropZone.addEventListener("dragenter", (event) => {
            event.preventDefault();

            if (!canSendToContact(activeContact())) {
                return;
            }

            state.dragDepth += 1;
            setDragState(true);
        });

        dropZone.addEventListener("dragover", (event) => {
            if (!canSendToContact(activeContact())) {
                return;
            }

            event.preventDefault();
        });

        dropZone.addEventListener("dragleave", (event) => {
            event.preventDefault();
            state.dragDepth = Math.max(0, state.dragDepth - 1);
            if (state.dragDepth === 0) {
                setDragState(false);
            }
        });

        dropZone.addEventListener("drop", (event) => {
            event.preventDefault();
            setDragState(false);
            handleDropFile(event);
        });
    });

    if (elements.emojiButton && elements.emojiPicker) {
        elements.emojiButton.addEventListener("click", () => {
            toggleEmojiPicker();
        });

        elements.emojiPicker.addEventListener("click", (event) => {
            const button = event.target.closest("[data-emoji]");

            if (!button) {
                return;
            }

            insertEmoji(String(button.dataset.emoji ?? ""));
        });

        document.addEventListener("click", (event) => {
            if (!state.emojiPickerOpen) {
                return;
            }

            if (!(event.target instanceof Node)) {
                return;
            }

            const target = event.target;
            const insidePicker = elements.emojiPicker.contains(target);
            const isToggleButton = elements.emojiButton.contains(target);

            if (!insidePicker && !isToggleButton) {
                closeEmojiPicker();
            }
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                closeEmojiPicker();
                setContactMenu(null);
                closeContactProfile();
            }
        });

        renderEmojiPicker();
    }

    document.addEventListener("click", (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const target = event.target;

        if (state.openedContactMenuId !== null && !target.closest(".tg-contact__actions")) {
            setContactMenu(null);
        }

        if (
            !elements.contactProfilePanel.hidden &&
            !elements.contactProfilePanel.contains(target) &&
            !(elements.contactProfileTrigger && elements.contactProfileTrigger.contains(target))
        ) {
            closeContactProfile();
        }
    });

    window.addEventListener("beforeunload", () => {
        if (state.mediaRecorder && state.mediaRecorder.state !== "inactive") {
            state.mediaRecorder.stop();
        }
        stopMediaTracks();
    });

    mobileMedia.addEventListener("change", () => {
        if (!mobileMedia.matches) {
            elements.shell.classList.remove("is-mobile-chat-open");
        }
    });

    setupEcho();
    void loadContacts({ preserveSelection: false });

    elements.onlineSummary.textContent = `Connecting as ${currentUserName}...`;
}



