@php
    $dashboardActive = request()->routeIs('dashboard');
    $activityActive = request()->routeIs('activity.*');
    $chatActive = request()->routeIs('chat*');
    $contactsActive = request()->routeIs('contacts.*');
    $mediaActive = request()->routeIs('media.*');
    $profileActive = request()->routeIs('profile.*');
    $authUser = Auth::user();
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
                <a class="tg-nav__link {{ $activityActive ? 'is-active' : '' }}" href="{{ route('activity.index') }}">
                    {{ __('Activity') }}
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
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button class="tg-nav__user" type="button" @click="open = ! open" :aria-expanded="open.toString()">
                    <span class="tg-nav__user-avatar">
                        @if($authUser->avatar_url)
                            <img class="tg-avatar__img" src="{{ $authUser->avatar_url }}" alt="{{ $authUser->name }}">
                        @else
                            <span class="tg-avatar__text">{{ $authUser->initials }}</span>
                        @endif
                    </span>
                    <span class="tg-nav__user-text">
                        <strong>{{ $authUser->name }}</strong>
                        <small>{{ __('Open menu') }}</small>
                    </span>

                    <svg class="tg-nav__caret" :class="{ 'is-open': open }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div
                    x-cloak
                    x-show="open"
                    x-transition:enter="transition ease-out duration-180"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-120"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="tg-nav-menu">
                    <a class="tg-nav-dropdown__link {{ $profileActive ? 'is-active' : '' }}" href="{{ route('profile.edit') }}">
                        {{ __('Profile settings') }}
                    </a>
                    <a class="tg-nav-dropdown__link {{ $activityActive ? 'is-active' : '' }}" href="{{ route('activity.index') }}">
                        {{ __('Activity feed') }}
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="tg-nav-dropdown__link" type="submit">
                            {{ __('Log Out') }}
                        </button>
                    </form>
                </div>
            </div>

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
            <a class="tg-nav-mobile__link {{ $activityActive ? 'is-active' : '' }}" href="{{ route('activity.index') }}">
                {{ __('Activity') }}
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
            <div class="tg-nav-mobile__avatar">
                @if($authUser->avatar_url)
                    <img class="tg-avatar__img" src="{{ $authUser->avatar_url }}" alt="{{ $authUser->name }}">
                @else
                    <span class="tg-avatar__text">{{ $authUser->initials }}</span>
                @endif
            </div>
            <p>{{ $authUser->name }}</p>
            <span>{{ $authUser->email }}</span>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="tg-nav-mobile__logout" type="submit">{{ __('Log Out') }}</button>
        </form>
    </div>
</nav>
