<x-app-layout>
    <x-slot name="header">
        <h2 class="tg-app-title">{{ __('Contacts') }}</h2>
    </x-slot>

    <section class="tg-dashboard-grid">
        <article class="tg-panel">
            <h3>{{ __('All registered users') }}</h3>
            <p>{{ __('Open any dialog directly from the directory.') }}</p>
        </article>

        <article class="tg-panel">
            <div class="tg-cards-grid">
                @forelse ($contacts as $contact)
                    <article class="tg-user-card">
                        <div class="tg-user-card__head">
                            <div class="tg-avatar tg-avatar--sm">
                                @if(!empty($contact['avatar_url']))
                                    <img class="tg-avatar__img" src="{{ $contact['avatar_url'] }}" alt="{{ $contact['name'] }}">
                                @else
                                    <span class="tg-avatar__text">{{ $contact['avatar_initials'] }}</span>
                                @endif
                            </div>
                            <strong>
                                {{ $contact['name'] }}
                                <span class="tg-online-dot tg-online-dot--lg {{ $contact['online'] ? 'is-online' : '' }}"></span>
                            </strong>
                        </div>

                        <p class="tg-user-card__meta {{ $contact['online'] ? 'is-online' : '' }}">
                            {{ $contact['online'] ? __('Online now') : __('Offline') }}
                        </p>
                        <p class="tg-user-card__email">{{ $contact['email'] }}</p>

                        <a class="tg-panel__cta" href="{{ route('chat', ['contact' => $contact['id']]) }}">
                            {{ __('Open chat') }}
                        </a>
                    </article>
                @empty
                    <p class="tg-panel__empty">{{ __('No contacts found.') }}</p>
                @endforelse
            </div>
        </article>
    </section>
</x-app-layout>
