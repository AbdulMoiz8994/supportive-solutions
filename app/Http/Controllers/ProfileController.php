<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateProfileRequest;

class ProfileController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        return view('pages.profile', ['title' => 'My Profile', 'user' => $user]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = auth()->user();
        $validated = $request->validated();

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return redirect()->route('profile')->with('success', 'Profile updated successfully!');
    }
}
