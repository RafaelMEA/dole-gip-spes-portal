<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new student account.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = new User([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        // Public registrations always create student accounts.
        // The role is never taken from the request payload.
        $user->role = User::ROLE_STUDENT;
        $user->save();

        // Do not authenticate the user after registration; they must sign in.
        return (new UserResource($user))
            ->additional(['message' => 'Your account has been created.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Authenticate an existing user and start a session.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $request->session()->regenerate();

        return (new UserResource(Auth::guard('web')->user()))->response();
    }

    /**
     * Log the authenticated user out and invalidate the session.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        // Drop any user cached on resolved guards (e.g. the stateless sanctum
        // guard) so a later request in the same process is not re-authenticated.
        Auth::forgetGuards();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'You have been signed out.']);
    }

    /**
     * Return the currently authenticated user.
     */
    public function user(Request $request): JsonResponse
    {
        return (new UserResource($request->user()))->response();
    }
}
