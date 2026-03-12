<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="tg-auth-status" :status="session('status')" />

    <form class="tg-auth-form" method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div class="tg-form-group">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="tg-auth-errors" />
        </div>

        <!-- Password -->
        <div class="tg-form-group">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="tg-auth-errors" />
        </div>

        <!-- Remember Me -->
        <div class="tg-auth-remember">
            <label for="remember_me">
                <input id="remember_me" type="checkbox" name="remember">
                <span>{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="tg-auth-actions">
            @if (Route::has('password.request'))
                <a class="tg-auth-link" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="tg-auth-submit">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
