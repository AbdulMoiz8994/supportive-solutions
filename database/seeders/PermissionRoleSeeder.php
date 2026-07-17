<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Ensure all standard permissions exist
        $permissions = [
            // Dashboard
            ['name' => 'View Dashboard', 'slug' => 'view_dashboard', 'module' => 'Dashboard'],
            
            // Staff
            ['name' => 'View Staff', 'slug' => 'view_staff', 'module' => 'Staff'],
            ['name' => 'Add Staff', 'slug' => 'add_staff', 'module' => 'Staff'],
            ['name' => 'Edit Staff', 'slug' => 'edit_staff', 'module' => 'Staff'],
            ['name' => 'Delete Staff', 'slug' => 'delete_staff', 'module' => 'Staff'],
            ['name' => 'Manage Permissions', 'slug' => 'manage_permissions', 'module' => 'Staff'],
            ['name' => 'Manage AI Agents', 'slug' => 'manage_ai_agents', 'module' => 'Staff'],
            
            // Clients
            ['name' => 'View Clients', 'slug' => 'view_clients', 'module' => 'Clients'],
            ['name' => 'Add Clients', 'slug' => 'add_clients', 'module' => 'Clients'],
            ['name' => 'Edit Clients', 'slug' => 'edit_clients', 'module' => 'Clients'],
            ['name' => 'Delete Clients', 'slug' => 'delete_clients', 'module' => 'Clients'],
            
            // Billing
            ['name' => 'View Billing', 'slug' => 'view_billing', 'module' => 'Billing'],
            ['name' => 'Run Billing', 'slug' => 'run_billing', 'module' => 'Billing'],
            ['name' => 'Manage Invoices', 'slug' => 'manage_invoices', 'module' => 'Billing'],
            
            // Locations
            ['name' => 'View Locations', 'slug' => 'view_locations', 'module' => 'Locations'],
            ['name' => 'Add Locations', 'slug' => 'add_locations', 'module' => 'Locations'],
            ['name' => 'Edit Locations', 'slug' => 'edit_locations', 'module' => 'Locations'],
            ['name' => 'Delete Locations', 'slug' => 'delete_locations', 'module' => 'Locations'],
            ['name' => 'Switch Locations', 'slug' => 'switch_locations', 'module' => 'Locations'],

            // Calendar
            ['name' => 'View Calendar', 'slug' => 'view_calendar', 'module' => 'Calendar'],
            ['name' => 'Manage Schedules', 'slug' => 'manage_schedules', 'module' => 'Calendar'],
        ];

        foreach ($permissions as $p) {
            Permission::updateOrCreate(['slug' => $p['slug']], $p);
        }

        // 2. Assign Permissions to Roles
        $admin = Role::where('name', 'Administrator')->first();
        $staff = Role::where('name', 'Operations Staff')->first();
        $employee = Role::where('name', 'Employee')->first();

        $allPermissionIds = Permission::all()->pluck('id')->toArray();

        if ($admin) {
            // Admin gets everything except maybe "Manage Permissions" if that's Super Admin only? 
            // Usually Admin gets almost everything.
            $admin->permissions()->sync($allPermissionIds);
        }

        if ($staff) {
            // Staff gets a subset (Dashboard, Clients, simple Staff view, Calendar)
            $staffPermissionSlugs = [
                'view_dashboard',
                'view_staff',
                'view_clients',
                'add_clients',
                'edit_clients',
                'view_billing',
                'view_locations',
                'switch_locations',
                'view_calendar',
                'manage_schedules'
            ];
            $staffIds = Permission::whereIn('slug', $staffPermissionSlugs)->pluck('id')->toArray();
            $staff->permissions()->sync($staffIds);
        }

        if ($employee) {
            // Employees (Caregivers) only get Calendar usually
            $employeePermissionSlugs = [
                'view_calendar'
            ];
            $employeeIds = Permission::whereIn('slug', $employeePermissionSlugs)->pluck('id')->toArray();
            $employee->permissions()->sync($employeeIds);
        }
    }
}
