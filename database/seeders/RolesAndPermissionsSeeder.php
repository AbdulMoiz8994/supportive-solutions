<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Location;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Default Location
        $michigan = Location::updateOrCreate(
            ['name' => 'Michigan Main'],
            ['state' => 'Michigan', 'is_active' => true]
        );

        // 2. Define Roles
        $roles = [
            'Super Administrator' => 'super-administrator',
            'Administrator'       => 'administrator',
            'Operations Staff'    => 'operations-staff',
            'Employee'            => 'employee',
            'AI Agent'            => 'ai-agent',
        ];

        $roleModels = [];
        foreach ($roles as $name => $slug) {
            $roleModels[$slug] = Role::updateOrCreate(['slug' => $slug], ['name' => $name]);
        }

        // 3. Define Permissions
        $modules = [
            'Dashboard' => ['view_dashboard'],
            'Workflow Queue' => ['approve_queue_items'],
            'Staff'     => ['view_staff', 'add_staff', 'edit_staff', 'delete_staff', 'manage_permissions', 'manage_ai_agents'],
            'Clients'   => ['view_clients', 'add_clients', 'edit_clients', 'delete_clients', 'send_client_requests', 'view_client_ssn'],
            'Requests'  => ['manage_request_templates'],
            'Billing'   => ['view_billing', 'run_billing', 'manage_invoices'],
            'Billing Claims Audit' => ['view_billing_claims_audit', 'edit_billing_claims_audit', 'override_billing_claims_audit'],
            'Payroll' => ['view_payroll', 'edit_payroll', 'run_payroll', 'release_payroll_hold', 'export_payroll'],
            'Locations' => ['view_locations', 'add_locations', 'edit_locations', 'delete_locations', 'switch_locations'],
            'Calendar'  => ['view_calendar', 'manage_schedules'],
            'Communications' => [
                'view_communications',
                'send_communications',
                'manage_communication_templates',
                'manage_secure_messages',
                'view_notifications',
                'manage_notifications',
            ],
            'Visit Reports' => ['view_visit_reports', 'manage_visit_reports'],
            'Tasks' => ['view_tasks', 'manage_tasks'],
            'Forms' => ['view_forms', 'manage_forms'],
            'Data Exploration' => ['view_data_exploration'],
        ];

        $allPermissions = [];
        foreach ($modules as $module => $perms) {
            foreach ($perms as $slug) {
                $permission = Permission::updateOrCreate(
                    ['slug' => $slug],
                    ['name' => ucwords(str_replace('_', ' ', $slug)), 'module' => $module]
                );
                $allPermissions[$slug] = $permission;
            }
        }

        // 4. Assign Permissions to Roles
        
        // Super Admin gets everything
        $roleModels['super-administrator']->permissions()->sync(array_column($allPermissions, 'id'));

        // Admin gets most things
        $adminPerms = array_filter($allPermissions, function($slug) {
            return !in_array($slug, ['delete_locations', 'manage_permissions', 'run_payroll']);
        }, ARRAY_FILTER_USE_KEY);
        $roleModels['administrator']->permissions()->sync(array_column($adminPerms, 'id'));

        // Operations Staff
        $staffPerms = array_filter($allPermissions, function($slug) {
            return in_array($slug, [
                'view_dashboard', 'view_clients', 'view_staff', 'view_calendar', 'manage_schedules',
                'send_client_requests', 'view_billing_claims_audit',
                'view_communications', 'send_communications', 'view_notifications',
                'view_visit_reports', 'manage_visit_reports',
                'view_tasks', 'manage_tasks',
                'view_forms', 'manage_forms',
                'view_data_exploration',
            ]);
        }, ARRAY_FILTER_USE_KEY);
        $roleModels['operations-staff']->permissions()->sync(array_column($staffPerms, 'id'));

        // Employee
        $employeePerms = array_filter($allPermissions, function($slug) {
            return in_array($slug, [
                'view_dashboard', 'view_calendar',
                'view_communications', 'manage_secure_messages', 'view_notifications',
            ]);
        }, ARRAY_FILTER_USE_KEY);
        $roleModels['employee']->permissions()->sync(array_column($employeePerms, 'id'));

        // AI Agent role — baseline; per-agent permissions live on ai_agents.permission_slugs
        $aiAgentPerms = array_filter($allPermissions, function ($slug) {
            return in_array($slug, ['view_dashboard'], true);
        }, ARRAY_FILTER_USE_KEY);
        $roleModels['ai-agent']->permissions()->sync(array_column($aiAgentPerms, 'id'));

    }
}
