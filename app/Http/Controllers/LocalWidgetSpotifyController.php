<?php

namespace App\Http\Controllers;

use App\Actions\LocalWidgets\SpotifyWidgetService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class LocalWidgetSpotifyController extends Controller
{
    private const CONNECT_USER_SESSION_KEY = 'local_widgets.spotify_connect_user_id';

    public function connect(Request $request, SpotifyWidgetService $spotify): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if (! $spotify->isConfigured()) {
            Log::warning('Local Spotify connect attempted without Spotify config.', [
                'user_id' => $user->id,
            ]);

            return redirect()
                ->route('local.widgets.index')
                ->withErrors(['spotify' => 'Spotify client credentials are not configured for local widget tooling.']);
        }

        $request->session()->put(self::CONNECT_USER_SESSION_KEY, $user->id);

        Log::info('Starting local Spotify connect flow.', [
            'user_id' => $user->id,
        ]);

        return $spotify->redirect();
    }

    public function callback(Request $request, SpotifyWidgetService $spotify): RedirectResponse
    {
        $sessionUserId = $request->session()->get(self::CONNECT_USER_SESSION_KEY);
        $user = null;

        if (is_numeric($sessionUserId)) {
            $user = User::query()->find((int) $sessionUserId);
        }

        if (! $user instanceof User) {
            $user = $request->user();
        }

        if (! $user instanceof User) {
            Log::warning('Local Spotify callback could not resolve a user from session.', [
                'session_user_id' => $sessionUserId,
                'has_code' => $request->filled('code'),
                'has_state' => $request->filled('state'),
                'provider_error' => $request->query('error'),
            ]);

            $request->session()->forget(self::CONNECT_USER_SESSION_KEY);

            return redirect()
                ->route('login')
                ->withErrors(['spotify' => 'Your Spotify connection session expired. Please try again.']);
        }

        try {
            if (! Auth::check()) {
                Auth::login($user);
            }

            Log::info('Handling local Spotify callback.', [
                'user_id' => $user->id,
                'provider_error' => $request->query('error'),
            ]);

            $spotify->connect($user);
        } catch (Throwable $exception) {
            Log::error('Local Spotify callback failed.', [
                'user_id' => $user->id,
                'provider_error' => $request->query('error'),
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            $request->session()->forget(self::CONNECT_USER_SESSION_KEY);

            return redirect()
                ->route('local.widgets.index')
                ->withErrors(['spotify' => 'We could not connect Spotify. Please try again.']);
        }

        $request->session()->forget(self::CONNECT_USER_SESSION_KEY);

        Log::info('Local Spotify callback completed successfully.', [
            'user_id' => $user->id,
        ]);

        return redirect()
            ->route('local.widgets.index')
            ->with('status', 'Spotify connected for local widget previews.');
    }

    public function disconnect(Request $request, SpotifyWidgetService $spotify): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $spotify->disconnect($user);

        Log::info('Local Spotify disconnected from launcher.', [
            'user_id' => $user->id,
        ]);

        return redirect()
            ->route('local.widgets.index')
            ->with('status', 'Spotify disconnected from local widget previews.');
    }

    public function show(): View
    {
        return view('local.widgets.spotify');
    }
}
