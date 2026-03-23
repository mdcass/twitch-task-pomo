<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompleteRegistration
{
    public function handle(Request $request, User $user, bool $remember = false): User
    {
        Auth::login($user, $remember);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        event(new Registered($user));

        return $user;
    }
}
