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
    attachButton: document.getElementById("attach-button"),
    attachmentInput: document.getElementById("attachment-input"),
    attachmentPreview: document.getElementById("attachment-preview"),
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
        emojiPickerOpen: false,
        queryContactId: preselectedContactId > 0 ? preselectedContactId : null,
    };

    const emojiPalette = [
        "😀", "😄", "😁", "😉", "😊", "😍", "😘", "🤔", "😎", "😭",
        "😡", "🙏", "👍", "👎", "👏", "🔥", "❤️", "💙", "💯", "🎉",
        "📎", "📸", "🎬", "🚀",
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

        if (!["image", "video", "file"].includes(type)) {
            if (mime.startsWith("image/")) {
                type = "image";
            } else if (mime.startsWith("video/")) {
                type = "video";
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
            return `📎 ${base}`;
        }

        return `📎 ${base}: ${name}`;
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
            return;
        }

        elements.activeAvatar.innerHTML = avatarContentMarkup(
            contact.avatar_initials,
            contact.avatar_url
        );
        elements.activeName.textContent = contact.name;
        elements.activeMeta.textContent = contact.online ? "Online" : formatLastSeen(contact.last_seen_at);
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
                contact.email.toLowerCase().includes(query)
            );
        });

        if (!filtered.length) {
            elements.contactsList.innerHTML = `<div class="tg-state">No contacts found.</div>`;
            renderOnlineSummary();
            return;
        }

        const html = filtered
            .map((contact) => {
                const isActive = contact.id === state.activeContactId;
                const unreadBadge =
                    contact.unread_count > 0 ? `<span class="tg-unread">${contact.unread_count}</span>` : "";
                const statusSymbol = contactStatusTick(contact);
                const statusClass = contact.last_message_status === "read" ? "is-read" : "";

                return `
                    <button class="tg-contact ${isActive ? "is-active" : ""}" data-contact-id="${contact.id}" type="button">
                        ${avatarMarkup(contact.avatar_initials, contact.avatar_url, "tg-avatar tg-avatar--sm")}
                        <div class="tg-contact__main">
                            <p class="tg-contact__name">
                                ${escapeHtml(contact.name)}
                                <span class="tg-online-dot ${contact.online ? "is-online" : ""}"></span>
                            </p>
                            <p class="tg-contact__preview">${escapeHtml(messagePreview(contact))}</p>
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
                    </button>
                `;
            })
            .join("");

        elements.contactsList.innerHTML = html;
        renderOnlineSummary();
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

        return `
            <a class="tg-message__file" href="${safeUrl}" target="_blank" rel="noopener noreferrer" download>
                <span class="tg-message__file-icon">📄</span>
                <span class="tg-message__file-meta">
                    <strong>${safeName}</strong>
                    <small>${safeMime} • ${safeSize}</small>
                </span>
            </a>
        `;
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

                return `
                    <article class="tg-message ${mine ? "is-mine" : ""}" data-message-id="${message.id}">
                        <div class="tg-message__bubble">
                            ${textHtml}
                            ${attachmentHtml}
                            <div class="tg-message__meta">
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
                <button class="tg-attachment-preview__remove" type="button" data-remove-attachment aria-label="Remove attachment">✕</button>
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

            renderContacts();
            updateHeader();

            if (state.activeContactId !== null) {
                await loadMessages(state.activeContactId, { reset: true });
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

        state.activeContactId = contactId;
        contact.unread_count = 0;

        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set("contact", String(contactId));
        window.history.replaceState({}, "", currentUrl);

        renderContacts();
        updateHeader();
        renderMessages();
        setMobileChatState(true);

        await loadMessages(contactId, { reset: true });
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
        if (!state.activeContactId) {
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

        if (!state.activeContactId || (!body.length && !attachment)) {
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
            closeEmojiPicker();
        } catch (error) {
            console.error("Cannot send message", error);
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
        const button = event.target.closest("[data-contact-id]");

        if (!button) {
            return;
        }

        const contactId = Number(button.dataset.contactId);

        if (!contactId) {
            return;
        }

        void openContact(contactId);
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

    elements.mobileBack.addEventListener("click", () => {
        setMobileChatState(false);
    });

    elements.refreshContacts.addEventListener("click", () => {
        void loadContacts({ preserveSelection: true });
    });

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
            const removeButton = event.target.closest("[data-remove-attachment]");

            if (!removeButton) {
                return;
            }

            clearPendingAttachment();
        });
    }

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
            }
        });

        renderEmojiPicker();
    }

    mobileMedia.addEventListener("change", () => {
        if (!mobileMedia.matches) {
            elements.shell.classList.remove("is-mobile-chat-open");
        }
    });

    setupEcho();
    void loadContacts({ preserveSelection: false });

    elements.onlineSummary.textContent = `Connecting as ${currentUserName}...`;
}
