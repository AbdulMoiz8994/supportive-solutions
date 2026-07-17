<?php

namespace App\Http\Controllers;

use App\Http\Requests\Platform\StorePlatformUserRequest;
use App\Http\Requests\Platform\UpdatePlatformUserRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('managePlatformUsers', User::class);

        $query = User::with('organization')->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        $users = $query->paginate(15)->withQueryString();
        $organizations = Organization::where('status', 'Active')->get();

        $roles = [
            User::ROLE_SUPER_ADMIN,
            User::ROLE_ADMIN,
            User::ROLE_STAFF,
            User::ROLE_EMPLOYEE,
        ];

        return view('pages.users.index', compact('users', 'organizations', 'roles'), ['title' => 'User Management']);
    }

    public function store(StorePlatformUserRequest $request)
    {
        $validated = $request->validated();

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'organization_id' => $validated['organization_id'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('users.index')->with('success', "User '{$validated['name']}' created successfully.");
    }

    public function update(UpdatePlatformUserRequest $request, $id)
    {
        $user = User::findOrFail($id);
        $validated = $request->validated();

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'organization_id' => $validated['organization_id'] ?? null,
        ];

        if (! empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return redirect()->route('users.index')->with('success', "User '{$user->name}' updated successfully.");
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $this->authorize('deletePlatformUser', $user);

        $name = $user->name;
        $user->delete();

        return redirect()->route('users.index')->with('success', "User '{$name}' deleted.");
    }
}
