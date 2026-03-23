<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CompleteRegistration;
use App\Enums\ActivityEvent;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;

class RegisterController extends \Laravel\Fortify\Http\Controllers\RegisteredUserController
{
    public function store(
        Request $request,
        CreatesNewUsers $creator,
    ): RegisterResponse {
        if (config('fortify.lowercase_usernames') && $request->has(Fortify::username())) {
            $request->merge([
                Fortify::username() => Str::lower($request->{Fortify::username()}),
            ]);
        }

        $user = $creator->create($request->all());

        app(CompleteRegistration::class)->handle($request, $user, $request->boolean('remember'));
        Activity::log(ActivityEvent::AuthLocalRegistrationCompleted, subject: $user, causer: $user);

        return app(RegisterResponse::class);
    }
}
