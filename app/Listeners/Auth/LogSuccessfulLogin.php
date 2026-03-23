<?php

namespace App\Listeners\Auth;

use App\Enums\ActivityEvent;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        if (! request()?->is('login')) {
            return;
        }

        if (request()->attributes->get('auth.local_login_succeeded_logged') === true) {
            return;
        }

        request()->attributes->set('auth.local_login_succeeded_logged', true);

        if (! $event->user instanceof User) {
            return;
        }

        Activity::log(ActivityEvent::AuthLocalLoginSucceeded, subject: $event->user, causer: $event->user);
    }
}
