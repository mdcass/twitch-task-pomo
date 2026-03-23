<?php

namespace App\Models\Concerns;

use App\Notifications\Auth\ResetPassword;
use App\Notifications\Auth\VerifyEmail;

trait HasNotifications
{
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail());
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPassword($token));
    }
}
