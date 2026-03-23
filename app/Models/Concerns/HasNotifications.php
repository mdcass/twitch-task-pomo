<?php

namespace App\Models\Concerns;

use App\Notifications\Auth\ResetPassword;
use App\Notifications\Auth\VerifyEmail;

trait HasNotifications
{
    public function sendEmailVerificationNotification(): void
    {
        $request = request();

        if ($request !== null) {
            $key = 'notifications.verify_email_sent.'.static::class.'.'.$this->getKey();

            if ($request->attributes->get($key) === true) {
                return;
            }

            $request->attributes->set($key, true);
        }

        $this->notify(new VerifyEmail());
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPassword($token));
    }
}
