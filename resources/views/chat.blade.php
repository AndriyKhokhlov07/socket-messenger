<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Messenger</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
    ])
</head>

<body
    class="tg-page"
    data-user-id="{{ $authUser->id }}"
    data-user-name="{{ $authUser->name }}"
    data-user-avatar="{{ $authUser->avatar_url }}"
    data-user-initials="{{ $authUser->initials }}">

    <div class="tg-atmosphere"></div>

    <main class="tg-shell" id="messenger-app">
        <aside class="tg-sidebar" id="chat-sidebar">
            <header class="tg-sidebar__header">
                <div class="tg-avatar">
                    @if($authUser->avatar_url)
                        <img class="tg-avatar__img" src="{{ $authUser->avatar_url }}" alt="{{ $authUser->name }}">
                    @else
                        <span class="tg-avatar__text">{{ $authUser->initials }}</span>
                    @endif
                </div>
                <div class="tg-sidebar__identity">
                    <h1>Socket Messenger</h1>
                    <p id="online-summary">Connecting...</p>
                </div>
            </header>

            <div class="tg-search">
                <input id="contact-search" type="text" placeholder="Search users">
            </div>

            <div class="tg-contact-tools">
                <button id="select-mode-toggle" class="tg-contact-tools__btn" type="button">Select</button>
                <button id="bulk-block" class="tg-contact-tools__btn" type="button" disabled>Block</button>
                <button id="bulk-report" class="tg-contact-tools__btn" type="button" disabled>Report</button>
                <button id="bulk-delete" class="tg-contact-tools__btn tg-contact-tools__btn--danger" type="button" disabled>Delete</button>
            </div>

            <section class="tg-contacts" id="contacts-list">
                <div class="tg-state">Loading contacts...</div>
            </section>
        </aside>

        <section class="tg-chat">
            <header class="tg-chat__header">
                <button
                    type="button"
                    id="mobile-back"
                    class="tg-mobile-back"
                    aria-label="Back to contacts">
                    <
                </button>

                <button
                    type="button"
                    id="contact-profile-trigger"
                    class="tg-chat__identity tg-chat__identity-btn"
                    disabled>
                    <div class="tg-avatar tg-avatar--sm" id="active-contact-avatar">?</div>
                    <div>
                        <h2 id="active-contact-name">Select a conversation</h2>
                        <p id="active-contact-meta">Choose a user from the list</p>
                    </div>
                </button>

                <button id="refresh-contacts" class="tg-refresh" type="button">Refresh</button>
            </header>

            <div class="tg-typing" id="typing-indicator" hidden></div>

            <section class="tg-messages" id="messages">
                <div id="messages-empty" class="tg-state">
                    Select a user to start messaging.
                </div>
            </section>

            <aside class="tg-contact-profile" id="contact-profile-panel" hidden>
                <div class="tg-contact-profile__head">
                    <h3>Contact info</h3>
                    <button id="contact-profile-close" type="button" aria-label="Close contact info">&times;</button>
                </div>
                <div class="tg-contact-profile__body" id="contact-profile-content"></div>
            </aside>

            <form class="tg-inputbar" id="message-form" enctype="multipart/form-data">
                <input
                    id="attachment-input"
                    type="file"
                    hidden
                    accept="image/*,video/*,audio/*,.pdf,.txt,.doc,.docx,.xls,.xlsx,.zip,.rar">

                <div class="tg-attachment-preview" id="attachment-preview" hidden></div>
                <div class="tg-reply-preview" id="reply-preview" hidden></div>
                <div class="tg-emoji-picker" id="emoji-picker" hidden></div>

                <div class="tg-inputbar__row">
                    <button
                        id="emoji-button"
                        type="button"
                        class="tg-inputbar__icon"
                        aria-label="Insert emoji">
                        &#128578;
                    </button>

                    <button
                        id="attach-button"
                        type="button"
                        class="tg-inputbar__icon"
                        aria-label="Attach file">
                        &#128206;
                    </button>

                    <button
                        id="voice-button"
                        type="button"
                        class="tg-inputbar__icon"
                        aria-label="Record voice message">
                        &#127908;
                    </button>

                    <textarea
                        id="message-input"
                        rows="1"
                        maxlength="4000"
                        placeholder="Write a message..."
                        autocomplete="off"></textarea>

                    <button id="send-button" type="submit">Send</button>
                </div>
            </form>
        </section>
    </main>

</body>

</html>
