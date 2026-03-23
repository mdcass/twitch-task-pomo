<?php

namespace App\Notifications\Auth;

use App\Support\Branding\ProductBrand;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmail extends BaseVerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage())
            ->subject('Confirm your email to finish setting up your account')
            ->greeting('Welcome to '.ProductBrand::productName().'!')
            ->line('Please confirm your email address to finish setting up your account and access your workspace.')
            ->action('Confirm Email Address', $url)
            ->line('If you did not create an account, you can safely ignore this email.')
            ->salutation('The '.ProductBrand::productName().' team');
    }
}
