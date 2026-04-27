<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => (new CurrentUserResource($user))->resolve(),
        ]);
    }

    public function logout(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Delete the Bearer token if it exists and user is authenticated
        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        // Always clear cookies, even if user is null (e.g., token expired but cookie still exists)
        // This ensures HttpOnly cookies are properly deleted
        $cookieName = config('session.cookie', 'laravel_session');
        $cookie = cookie($cookieName, '', -1, '/', null, false, true);

        // Clear the auth_session cookie (custom cookie name used by Qwik frontend)
        $authCookieName = 'auth_session';
        $authCookie = cookie($authCookieName, '', -1, '/', null, false, true);

        // Return 204 No Content with cookies cleared
        // This works even if the user was not authenticated (e.g., token expired)
        return response()->noContent()
            ->cookie($cookie)
            ->cookie($authCookie);
    }
}
