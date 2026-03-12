@php
    $dashboardActive = request()->routeIs('dashboard');
    $chatActive = request()->routeIs('chat*');
    $contactsActive = request()->routeIs('contacts.*');
    $mediaActive = request()->routeIs('media.*');
    $profileActive = request()->routeIs('profile.*');
@endphp

<nav x-data="{ open: false }" class="tg-nav">
    <div class="tg-nav__inner">
        <div class="tg-nav__left">
            <a class="tg-nav__logo" href="{{ route('dashboard') }}">
                <x-application-logo class="tg-nav__logo-mark fill-current" />
            </a>

            <div class="tg-nav__links">
                <a class="tg-nav__link {{ $dashboardActive ? 'is-active' : '' }}" href="{{ route('dashboard') }}">
                    {{ __('Dashboard') }}
                </a>
                <a class="tg-nav__link {{ $chatActive ? 'is-active' : '' }}" href="{{ route('chat') }}">
                    {{ __('Chat') }}
                </a>
                <a class="tg-nav__link {{ $contactsActive ? 'is-active' : '' }}" href="{{ route('contacts.index') }}">
                    {{ __('Contacts') }}
                </a>
                <a class="tg-nav__link {{ $mediaActive ? 'is-active' : '' }}" href="{{ route('media.index') }}">
                    {{ __('Media') }}
                </a>
            </div>
        </div>

        <div class="tg-nav__right">
            <x-dropdown align="right" width="48" contentClasses="py-1 tg-nav-dropdown">
                <x-slot name="trigger">
                    <button class="tg-nav__user" type="button">
                        <span>{{ Auth::user()->name }}</span>

                        <svg class="tg-nav__caret" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </x-slot>

                <x-slot name="content">
                    <a class="tg-nav-dropdown__link {{ $profileActive ? 'is-active' : '' }}" href="{{ route('profile.edit') }}">
                        {{ __('Profile') }}
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="tg-nav-dropdown__link" type="submit">
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </x-slot>
            </x-dropdown>

            <button @click="open = ! open" class="tg-nav__hamburger" type="button">
                <svg class="tg-nav__hamburger-icon" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="tg-nav-mobile hidden">
        <div class="tg-nav-mobile__links">
            <a class="tg-nav-mobile__link {{ $dashboardActive ? 'is-active' : '' }}" href="{{ route('dashboard') }}">
                {{ __('Dashboard') }}
            </a>
            <a class="tg-nav-mobile__link {{ $chatActive ? 'is-active' : '' }}" href="{{ route('chat') }}">
                {{ __('Chat') }}
            </a>
            <a class="tg-nav-mobile__link {{ $contactsActive ? 'is-active' : '' }}" href="{{ route('contacts.index') }}">
                {{ __('Contacts') }}
            </a>
            <a class="tg-nav-mobile__link {{ $mediaActive ? 'is-active' : '' }}" href="{{ route('media.index') }}">
                {{ __('Media') }}
            </a>
            <a class="tg-nav-mobile__link {{ $profileActive ? 'is-active' : '' }}" href="{{ route('profile.edit') }}">
                {{ __('Profile') }}
            </a>
        </div>

        <div class="tg-nav-mobile__account">
            <p>{{ Auth::user()->name }}</p>
            <span>{{ Auth::user()->email }}</span>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="tg-nav-mobile__logout" type="submit">{{ __('Log Out') }}</button>
        </form>
    </div>
</nav>
