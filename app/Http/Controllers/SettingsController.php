<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;

class SettingsController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        return view('pages.settings.index', [
            'title' => 'Settings',
            'isSuperAdmin' => $user->isSuperAdmin(),
        ]);
    }

    public function roles()
    {
        $roles = Role::withCount(['permissions', 'users'])
            ->where('slug', '!=', 'super-administrator')
            ->orderBy('name')
            ->get();

        $modules = Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module');

        return view('pages.settings.roles', [
            'title' => 'Roles & Permissions',
            'roles' => $roles,
            'modules' => $modules,
        ]);
    }

    public function apiKeys()
    {
        if (auth()->user()->isSuperAdmin()) {
            return redirect()->route('settings.global', ['tab' => 'credential-vault']);
        }

        return redirect()
            ->route('settings.index')
            ->with('warning', 'Integration credentials are managed in Global Settings by platform administrators.');
    }
}
