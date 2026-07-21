<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocalizationController extends Controller
{
    public function set(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, ['en', 'ar'], true)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);

        return back();
    }
}
