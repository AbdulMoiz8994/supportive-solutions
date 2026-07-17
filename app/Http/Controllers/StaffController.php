<?php

namespace App\Http\Controllers;

use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateRolePermissionsRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Mail\WelcomeStaff;
use App\Models\ActivityLog;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\StaffAiAgentsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StaffController extends Controller
{
    public function __construct(
        protected StaffAiAgentsService $staffAgents,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::with(['roleModel', 'locations'])->where('role', '!=', User::ROLE_SUPER_ADMIN);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        $staff = $query->get();
        $roles = Role::where('slug', '!=', 'super-administrator')->get();
        $locations = Location::where('is_active', true)->get();

        return view('pages.staff.index', compact('staff', 'roles', 'locations'), ['title' => 'Staff']);
    }

    public function create()
    {
        $this->authorize('create', User::class);

        $roles = Role::with('permissions')->where('slug', '!=', 'super-administrator')->get();
        $locations = Location::where('is_active', true)->get();
        $modules = Permission::all()->groupBy('module');

        return view('pages.staff.create', compact('roles', 'locations', 'modules'), ['title' => 'Add Staff']);
    }

    public function show($id)
    {
        $staff = User::findOrFail($id);
        $this->authorize('view', $staff);

        $modules = Permission::all()->groupBy('module');
        $activityLogs = ActivityLog::where('user_id', $id)->latest()->take(10)->get();
        $roles = Role::with('permissions')->where('slug', '!=', 'super-administrator')->get();
        $locations = Location::where('is_active', true)->get();

        return view('pages.staff.show', compact('staff', 'modules', 'activityLogs', 'roles', 'locations'), [
            'title' => \App\Support\TabbedPageTitle::staff((string) $staff->name, session('active_tab', 'profile')),
        ]);
    }

    public function store(StoreStaffRequest $request)
    {
        $validated = $request->validated();
        $token = Str::random(60);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'organization_id' => $this->staffAgents->organizationId(),
            'password' => Hash::make(Str::random(16)),
            'invite_token' => $token,
            'invite_expires_at' => now()->addHours(24),
            'is_active' => false,
        ]);

        $user->locations()->sync($validated['location_ids']);

        if ($request->has('permissions')) {
            $role = Role::where('name', $validated['role'])->first();
            if ($role) {
                $role->permissions()->sync($request->permissions);
            }
        }

        $url = route('setup-account', ['email' => $user->email, 'token' => $token]);
        Mail::to($user->email)->send(new WelcomeStaff($user, $url));

        $redirect = redirect()->route('staff.index', ['tab' => 'staff'])->with('success', 'Staff member added and welcome email sent.');

        if (config('app.debug')) {
            $redirect->with('debug_setup_url', $url);
        }

        return $redirect;
    }

    public function update(UpdateStaffRequest $request, $id)
    {
        $user = User::findOrFail($id);
        $validated = $request->validated();

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
        ]);

        $user->locations()->sync($validated['location_ids']);

        return back()->with('success', 'Staff member updated.')->with('active_tab', 'profile');
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        $this->authorize('toggleStatus', $user);

        $user->is_active = ! $user->is_active;
        $user->save();

        return redirect()->route('staff.index', ['tab' => 'staff'])->with('success', 'Staff status updated.');
    }

    public function permissions($id)
    {
        $user = User::findOrFail($id);
        $this->authorize('view', $user);

        $modules = Permission::all()->groupBy('module');

        return view('pages.staff.permissions', compact('user', 'modules'), ['title' => 'Staff Permissions']);
    }

    public function updateRolePermissions(UpdateRolePermissionsRequest $request, $roleId)
    {
        $role = Role::findOrFail($roleId);
        $role->permissions()->sync($request->input('permissions', []));

        return back()->with('success', 'Role permissions updated.')->with('active_tab', 'permission');
    }

    public function resetPassword($id)
    {
        $user = User::findOrFail($id);
        $this->authorize('resetPassword', $user);

        \Illuminate\Support\Facades\Password::sendResetLink(['email' => $user->email]);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'organization_id' => auth()->user()->organization_id ?? 1,
            'action' => 'Password Reset Triggered',
            'subject_type' => 'App\Models\User',
            'subject_id' => $id,
            'description' => "Password reset link sent to {$user->email}",
            'ip_address' => request()->ip(),
        ]);

        return redirect()->route('staff.show', $id)
            ->with('success', 'Password reset link sent successfully.')
            ->with('active_tab', 'profile');
    }

    public function revokeSessions($id)
    {
        $user = User::findOrFail($id);
        $this->authorize('revokeSessions', $user);

        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $id)->delete();

        $user->setRememberToken(Str::random(60));
        $user->save();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'organization_id' => auth()->user()->organization_id ?? 1,
            'action' => 'Sessions Revoked',
            'subject_type' => 'App\Models\User',
            'subject_id' => $id,
            'description' => "All active sessions revoked for {$user->name}",
            'ip_address' => request()->ip(),
        ]);

        return back()->with('success', 'All sessions revoked successfully.');
    }
}
