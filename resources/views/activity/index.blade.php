<x-app-layout>
    <x-slot name="header">
        <h2 class="tg-app-title">{{ __('Activity Feed') }}</h2>
    </x-slot>

    <section class="tg-dashboard-grid">
        <article class="tg-panel">
            <h3>{{ __('Message activity') }}</h3>
            <p>{{ __('Track latest events in your dialogs.') }}</p>

            <div class="tg-dashboard-stats">
                <div class="tg-stat-card">
                    <strong>{{ $stats['sent_today'] }}</strong>
                    <span>{{ __('Sent today') }}</span>
                </div>
                <div class="tg-stat-card">
                    <strong>{{ $stats['received_today'] }}</strong>
                    <span>{{ __('Received today') }}</span>
                </div>
                <div class="tg-stat-card">
                    <strong>{{ $stats['total'] }}</strong>
                    <span>{{ __('Recent events') }}</span>
                </div>
            </div>
        </article>

        <article class="tg-panel">
            <div class="tg-activity-list">
                @forelse ($messages as $message)
                    @php
                        $isMine = (int) $message->sender_id === (int) $authUserId;
                        $peer = $isMine ? $message->receiver : $message->sender;
                        $peerInitials = $peer?->initials ?? 'U';
                        $peerAvatar = $peer?->avatar_url;
                        $label = $isMine ? __('You sent') : __('You received');
                        $text = trim((string) $message->body);
                    @endphp

                    <article class="tg-activity-item">
                        <div class="tg-avatar tg-avatar--sm">
                            @if($peerAvatar)
                                <img class="tg-avatar__img" src="{{ $peerAvatar }}" alt="{{ $peer?->name }}">
                            @else
                                <span class="tg-avatar__text">{{ $peerInitials }}</span>
                            @endif
                        </div>

                        <div class="tg-activity-item__body">
                            <p class="tg-activity-item__title">
                                {{ $label }} <strong>{{ $peer?->name ?? __('Unknown user') }}</strong>
                            </p>
                            <p class="tg-activity-item__text">
                                {{ $text !== '' ? $text : __('Attachment message') }}
                            </p>
                        </div>

                        <div class="tg-activity-item__meta">
                            <span class="tg-status {{ $message->status === 'read' ? 'is-read' : '' }}">
                                {{ strtoupper($message->status) }}
                            </span>
                            <time>{{ $message->created_at?->format('d M, H:i') }}</time>
                        </div>
                    </article>
                @empty
                    <p class="tg-panel__empty">{{ __('No activity yet.') }}</p>
                @endforelse
            </div>
        </article>
    </section>
</x-app-layout>
