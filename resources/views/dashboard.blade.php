<x-app-layout>
    <x-slot name="header">
        <h2 class="tg-app-title">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <section class="tg-dashboard-grid">
        <article class="tg-panel">
            <h3>{{ __("You're logged in!") }}</h3>
            <p>{{ __('Messenger workspace is ready. Open chats to continue.') }}</p>
            <a class="tg-panel__cta" href="{{ route('chat') }}">{{ __('Open chat') }}</a>
        </article>
    </section>
</x-app-layout>
