<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SocialAuthController extends Controller
{
    public function redirectToGoogle(): Response
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::updateOrCreate(
            ['google_id' => $googleUser->getId()],
            [
                'display_name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'avatar_url' => $googleUser->getAvatar(),
            ]
        );

        Auth::login($user, remember: true);

        return redirect(config('draftslate.frontend_url') . '/app/dashboard');
    }
}
