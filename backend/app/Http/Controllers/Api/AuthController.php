<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Create a customer account and return a token.
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => ['nullable', 'regex:/^[0-9+\-\s]{7,15}$/'],
        ], ['phone.regex' => 'Enter a valid phone number']);

        $user = new User($data);
        // Role and active state are never taken from the request.
        $user->role = 'customer';
        $user->is_active = true;
        $user->save();

        return response()->json([
            'message' => 'Registered.',
            'data' => ['user' => new UserResource($user), 'token' => $user->createToken('api')->plainTextToken],
        ], 201);
    }

    // Check credentials and return a token.
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Your account has been disabled. Contact support.'], 403);
        }

        return response()->json([
            'message' => 'Logged in.',
            'data' => ['user' => new UserResource($user), 'token' => $user->createToken('api')->plainTextToken],
        ]);
    }

    // Revoke only the token used for this request.
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    // The logged in user.
    public function me(Request $request)
    {
        return response()->json(['data' => new UserResource($request->user())]);
    }
}
