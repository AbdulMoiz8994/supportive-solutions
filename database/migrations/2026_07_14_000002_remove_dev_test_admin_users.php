<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Security cleanup: delete the throwaway dev test-admin logins that were seeded
 * for live testing (they bypassed OTP via a per-account 2FA exemption, now also
 * removed from config/two_factor.php). Removing the seeder alone leaves the rows
 * on production, so delete them explicitly here.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('email', [
                'laxedo1673@fisedo.com',
                'codewithumair867@gmail.com',
            ])
            ->delete();
    }

    public function down(): void
    {
        // Intentionally irreversible — we do not recreate throwaway test logins.
    }
};
