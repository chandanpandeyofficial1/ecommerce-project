<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    // Save a new profile photo and remove the old one.
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $user = $request->user();
        $old = $user->avatar;

        // Random file name on the public disk.
        $user->avatar = $request->file('avatar')->store('avatars', 'public');
        $user->save();

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'Profile photo updated.',
        ]);
    }

    // Remove the profile photo, if there is one.
    public function destroyAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = null;
        $user->save();

        return response()->json([
            'data' => new UserResource($user),
            'message' => 'Profile photo removed.',
        ]);
    }
}
