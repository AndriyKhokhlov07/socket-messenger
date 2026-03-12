<x-app-layout>
    <x-slot name="header">
        <h2 class="tg-app-title">{{ __('Profile') }}</h2>
    </x-slot>

    <section class="tg-profile-stack">
        <article class="tg-form-card">
            <h3>{{ __('Profile information') }}</h3>
            <p>{{ __('Update your name and email address.') }}</p>

            <form id="send-verification" method="post" action="{{ route('verification.send') }}">
                @csrf
            </form>

            <form method="post" action="{{ route('profile.update') }}" class="tg-form-grid">
                @csrf
                @method('patch')

                <div>
                    <label for="name">{{ __('Name') }}</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name">
                    @error('name')
                        <p class="tg-form-errors">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
                    @error('email')
                        <p class="tg-form-errors">{{ $message }}</p>
                    @enderror
                </div>

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <p class="tg-form-errors">
                        {{ __('Your email is not verified.') }}
                        <button class="tg-btn" type="submit" form="send-verification">
                            {{ __('Resend verification') }}
                        </button>
                    </p>
                @endif

                <div class="tg-form-actions">
                    <button class="tg-btn tg-btn--primary" type="submit">{{ __('Save') }}</button>
                    @if (session('status') === 'profile-updated')
                        <span class="tg-user-card__meta is-online">{{ __('Saved.') }}</span>
                    @endif
                </div>
            </form>
        </article>

        <article class="tg-form-card">
            <h3>{{ __('Update password') }}</h3>
            <p>{{ __('Use a strong password to protect your account.') }}</p>

            <form method="post" action="{{ route('password.update') }}" class="tg-form-grid">
                @csrf
                @method('put')

                <div>
                    <label for="current_password">{{ __('Current password') }}</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password">
                    @if ($errors->updatePassword->has('current_password'))
                        <p class="tg-form-errors">{{ $errors->updatePassword->first('current_password') }}</p>
                    @endif
                </div>

                <div>
                    <label for="password">{{ __('New password') }}</label>
                    <input id="password" name="password" type="password" autocomplete="new-password">
                    @if ($errors->updatePassword->has('password'))
                        <p class="tg-form-errors">{{ $errors->updatePassword->first('password') }}</p>
                    @endif
                </div>

                <div>
                    <label for="password_confirmation">{{ __('Confirm password') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password">
                    @if ($errors->updatePassword->has('password_confirmation'))
                        <p class="tg-form-errors">{{ $errors->updatePassword->first('password_confirmation') }}</p>
                    @endif
                </div>

                <div class="tg-form-actions">
                    <button class="tg-btn tg-btn--primary" type="submit">{{ __('Update password') }}</button>
                    @if (session('status') === 'password-updated')
                        <span class="tg-user-card__meta is-online">{{ __('Saved.') }}</span>
                    @endif
                </div>
            </form>
        </article>

        <article class="tg-form-card">
            <h3>{{ __('Delete account') }}</h3>
            <p>{{ __('This action is irreversible. Enter your password to confirm.') }}</p>

            <form method="post" action="{{ route('profile.destroy') }}" class="tg-form-grid">
                @csrf
                @method('delete')

                <div>
                    <label for="delete_password">{{ __('Password') }}</label>
                    <input id="delete_password" name="password" type="password" autocomplete="current-password" required>
                    @if ($errors->userDeletion->has('password'))
                        <p class="tg-form-errors">{{ $errors->userDeletion->first('password') }}</p>
                    @endif
                </div>

                <div class="tg-form-actions">
                    <button class="tg-btn tg-btn--danger" type="submit">{{ __('Delete account') }}</button>
                </div>
            </form>
        </article>
    </section>
</x-app-layout>
