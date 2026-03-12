<x-app-layout>
    <x-slot name="header">
        <h2 class="tg-app-title">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <section class="tg-dashboard-grid">
        <article class="tg-panel">
            <h3>{{ __('Workspace overview') }}</h3>
            <p>{{ __('Live counters and quick navigation for messenger operations.') }}</p>

            <div class="tg-dashboard-stats">
                <div class="tg-stat-card">
                    <strong>{{ $metrics['total_users'] }}</strong>
                    <span>{{ __('Available contacts') }}</span>
                </div>
                <div class="tg-stat-card">
                    <strong>{{ $metrics['unread_messages'] }}</strong>
                    <span>{{ __('Unread messages') }}</span>
                </div>
                <div class="tg-stat-card">
                    <strong>{{ $metrics['sent_messages'] }}</strong>
                    <span>{{ __('Sent by you') }}</span>
                </div>
                <div class="tg-stat-card">
                    <strong>{{ $metrics['attachments'] }}</strong>
                    <span>{{ __('Shared files') }}</span>
                </div>
            </div>
        </article>

        <article class="tg-panel">
            <h3>{{ __('Quick actions') }}</h3>
            <p>{{ __('Jump to dialogs, contacts or media in one click.') }}</p>

            <div class="tg-actions-row">
                <a class="tg-panel__cta" href="{{ route('chat') }}">{{ __('Open chat') }}</a>
                <a class="tg-panel__cta tg-panel__cta--secondary" href="{{ route('contacts.index') }}">{{ __('Contacts') }}</a>
                <a class="tg-panel__cta tg-panel__cta--secondary" href="{{ route('media.index') }}">{{ __('Media') }}</a>
            </div>
        </article>

        <article class="tg-panel">
            <h3>{{ __('Most active dialogs') }}</h3>
            <p>{{ __('Top contacts sorted by unread + latest activity.') }}</p>

            <div class="tg-cards-grid">
                @forelse ($topContacts as $contact)
                    <article class="tg-user-card">
                        <div class="tg-user-card__head">
                            <div class="tg-avatar tg-avatar--sm">
                                {{ mb_strtoupper(mb_substr($contact['name'], 0, 1)) }}
                            </div>
                            <strong>
                                {{ $contact['name'] }}
                                <span class="tg-online-dot tg-online-dot--lg {{ $contact['online'] ? 'is-online' : '' }}"></span>
                            </strong>
                        </div>

                        <p class="tg-user-card__meta">
                            {{ $contact['last_message'] ?: ($contact['last_message_attachment_type'] ? __('Attachment: :type', ['type' => $contact['last_message_attachment_type']]) : __('No messages yet')) }}
                        </p>
                        <a class="tg-panel__cta" href="{{ route('chat', ['contact' => $contact['id']]) }}">{{ __('Open chat') }}</a>
                    </article>
                @empty
                    <p class="tg-panel__empty">{{ __('No contacts available yet.') }}</p>
                @endforelse
            </div>
        </article>
    </section>
</x-app-layout>
