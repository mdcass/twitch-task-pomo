<?php

namespace App\Http\Controllers\Testing;

use App\Enums\ExternalAuthProvider;
use App\Http\Controllers\Controller;
use App\Testing\Auth\TestingSocialAuthScenario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TestingSocialAuthController extends Controller
{
    public function storeScenario(Request $request, ExternalAuthProvider $provider, string $scenario): RedirectResponse
    {
        abort_unless(app()->environment('testing'), 404);

        TestingSocialAuthScenario::payload($provider, $scenario);

        $request->session()->put(
            TestingSocialAuthScenario::sessionKey($provider),
            $scenario,
        );

        $next = $request->query('next');

        return redirect()->to(is_string($next) && str_starts_with($next, '/') ? $next : '/');
    }

    public function redirectToProvider(Request $request, ExternalAuthProvider $provider): RedirectResponse
    {
        abort_unless(app()->environment('testing'), 404);

        $scenario = $request->session()->get(TestingSocialAuthScenario::sessionKey($provider));
        abort_unless(is_string($scenario) && $scenario !== '', 404);

        TestingSocialAuthScenario::payload($provider, $scenario);

        return redirect()->route('oauth.callback', [
            'provider' => $provider->value,
            'code' => 'testing-code',
            'state' => 'testing-state',
        ]);
    }
}
