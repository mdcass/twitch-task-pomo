<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index(Request $request): View
    {
        $team = $request->user()?->currentTeam;
        abort_unless($team !== null, 403);

        $this->authorize('view', $team);

        return view('integrations.index');
    }
}
