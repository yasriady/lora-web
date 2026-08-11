<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function redirect(): SymfonyRedirectResponse|RedirectResponse
    {
        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('login')
                ->with('error', __('ui.auth.google_failed'));
        }

        $email = Str::lower((string) $googleUser->getEmail());

        if ($email === '' || ! $this->isAllowedEmail($email)) {
            return redirect()
                ->route('login')
                ->with('error', __('ui.auth.not_allowed'));
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'google_id' => $googleUser->getId(),
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
                'password' => null,
            ],
        );

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function isAllowedEmail(string $email): bool
    {
        $allowedEmails = array_filter(array_map(
            static fn (string $value): string => Str::lower(trim($value)),
            explode(',', (string) config('services.google.allowed_emails', '')),
        ));

        if ($allowedEmails !== [] && ! in_array($email, $allowedEmails, true)) {
            return false;
        }

        $allowedDomains = array_filter(array_map(
            static fn (string $value): string => Str::lower(trim($value)),
            explode(',', (string) config('services.google.allowed_domains', 'gmail.com')),
        ));

        if ($allowedDomains === []) {
            return true;
        }

        $domain = Str::after($email, '@');

        return in_array($domain, $allowedDomains, true);
    }
}
