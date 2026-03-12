<x-guest-layout>
    <form class="tg-auth-form" method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div class="tg-form-group">
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="tg-auth-errors" />
        </div>

        <!-- Email Address -->
        <div class="tg-form-group">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="tg-auth-errors" />
        </div>

        <!-- Password -->
        <div class="tg-form-group">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="tg-auth-errors" />
        </div>

        <!-- Confirm Password -->
        <div class="tg-form-group">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="tg-auth-errors" />
        </div>

        <div class="tg-auth-actions">
            <a class="tg-auth-link" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="tg-auth-submit">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
