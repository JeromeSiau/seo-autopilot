<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PreferencesController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'locale' => 'sometimes|in:en,fr,es',
            'theme' => 'sometimes|in:light,dark,system',
        ]);

        if (Auth::check()) {
            Auth::user()->update($validated);
        }

        $response = back();

        foreach ($validated as $key => $value) {
            $response->cookie($key, $value, 60 * 24 * 365, '/', null, false, false);
        }

        return $response;
    }
}
