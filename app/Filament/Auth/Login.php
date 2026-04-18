<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    /**
     * Replace the email field with a generic "login" field that accepts
     * either a username or an email address.
     */
    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Username or Email')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * Resolve credentials: if the input looks like an email use it directly,
     * otherwise look up the user by username and use their email.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[\SensitiveParameter] array $data): array
    {
        $login = $data['email'];

        $email = str_contains($login, '@')
            ? $login
            : User::where('username', $login)->value('email');

        return [
            'email' => $email ?? $login, // fall back to original input so validation fails naturally
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
