<?php

namespace App\Notifications\Auth;

use App\Support\Branding\ProductBrand;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPassword extends BaseResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage())
            ->subject('Use this secure link to choose a new password')
            ->greeting('Hello!')
            ->line('We received a request to reset the password for your '.ProductBrand::productName().' account.')
            ->action('Choose a New Password', $url)
            ->line('This password reset link will expire in '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' minutes.')
            ->line('If you did not request a password reset, no further action is required.')
            ->salutation('The '.ProductBrand::productName().' team');
    }
}
