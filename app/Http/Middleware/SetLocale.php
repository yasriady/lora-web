<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED = ['en', 'id'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale')
            ?? $request->cookie('locale')
            ?? config('app.locale', 'id');

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'id';
        }

        $request->session()->put('locale', $locale);
        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
