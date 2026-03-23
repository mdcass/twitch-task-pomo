<?php

namespace App\Listeners\Auth;

use App\Enums\ActivityEvent;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function handle(Failed $event): void
    {
        if (! request()?->is('login')) {
            return;
        }

        if (request()->attributes->get('auth.local_login_failed_logged') === true) {
            return;
        }

        request()->attributes->set('auth.local_login_failed_logged', true);

        $user = $event->user instanceof User ? $event->user : null;

        Activity::log(ActivityEvent::AuthLocalLoginFailed, [
            'reason' => 'invalid_credentials',
        ], subject: $user);
    }
}
