<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, ['en', 'id'], true)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);

        return back()->cookie('locale', $locale, 60 * 24 * 365);
    }
}
