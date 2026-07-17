<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Extend employees with caregiver onboarding / employment fields ──
        Schema::table('employees', function (Blueprint $table) {
            $addString = function ($col) use ($table) {
                if (!Schema::hasColumn('employees', $col)) {
                    $table->string($col)->nullable();
                }
            };

            // Personal
            foreach ([
                'gender', 'ssn_last4', 'county', 'preferred_language_extra',
                'emergency_contact_email', 'profile_photo',
            ] as $c) { $addString($c); }
            if (!Schema::hasColumn('employees', 'needs_accommodations')) {
                $table->boolean('needs_accommodations')->default(false);
            }

            // Employment & services
            foreach ([
                'caregiver_type', 'relationship_to_client', 'how_recruited',
                'pay_type', 'pay_schedule', 'w4_filing_status', 'direct_deposit_last4',
                'insurance_coverage', 'classification', 'payroll_system',
                'onboarding_status', 'onboarded_by',
                'champs_provider_id', 'champs_status', 'milogin_user_id',
                'attestation_status',
            ] as $c) { $addString($c); }

            if (!Schema::hasColumn('employees', 'years_experience')) {
                $table->string('years_experience')->nullable();
            }
            if (!Schema::hasColumn('employees', 'prior_experience')) {
                $table->boolean('prior_experience')->default(false);
            }
            if (!Schema::hasColumn('employees', 'services')) {
                $table->json('services')->nullable();
            }
            if (!Schema::hasColumn('employees', 'hourly_wage')) {
                $table->decimal('hourly_wage', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('employees', 'lives_with_client')) {
                $table->boolean('lives_with_client')->default(false);
            }
            if (!Schema::hasColumn('employees', 'live_in')) {
                $table->boolean('live_in')->default(false);
            }
            if (!Schema::hasColumn('employees', 'evv_exempt')) {
                $table->boolean('evv_exempt')->default(false);
            }
            if (!Schema::hasColumn('employees', 'notes')) {
                $table->text('notes')->nullable();
            }
            foreach (['activated_at', 'pay_eligibility_start', 'attestation_expires_at', 'application_signed_at'] as $d) {
                if (!Schema::hasColumn('employees', $d)) {
                    $table->date($d)->nullable();
                }
            }
        });

        // ── Background checks (CHAMPS / ICHAT / SAM / OIG / custom) ──
        Schema::create('background_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type');                 // CHAMPS, ICHAT, SAM, OIG, TB, MVR, Custom
            $table->string('label')->nullable();     // display label
            $table->string('cadence')->nullable();   // "Annual", "Monthly", etc.
            $table->string('status')->default('Enrolling'); // Clear, Enrolling, Submitted, Flagged, Exempted, On file
            $table->string('result')->nullable();
            $table->date('last_run')->nullable();
            $table->date('next_due')->nullable();
            $table->string('source')->nullable();
            $table->string('provider_id')->nullable();
            $table->string('monitoring')->nullable();
            $table->boolean('is_exempt')->default(false);
            $table->boolean('is_custom')->default(false);
            $table->string('exempt_reason')->nullable();
            $table->string('approved_by')->nullable();
            $table->date('approved_at')->nullable();
            $table->timestamps();
        });

        // ── Rich caregiver ↔ client assignments ──
        Schema::create('caregiver_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('relationship')->nullable();    // Mother, Wife, Uncle...
            $table->string('program')->nullable();         // MICH, DHS Home Help
            $table->integer('authorized_hours')->nullable();   // hrs/mo
            $table->decimal('scheduled_hours', 6, 2)->nullable(); // hrs/wk
            $table->string('authorization_no')->nullable();
            $table->boolean('live_in')->default(false);
            $table->string('evv_status')->nullable();      // "Exempt (live-in)", "Active"
            $table->string('compliance_status')->nullable();
            $table->string('status')->default('Active');   // Active, Ended
            $table->date('assigned_since')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();
        });

        // ── Monthly compliance forms (per caregiver, per client, per month) ──
        Schema::create('compliance_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period');             // 2026-04
            $table->string('period_label')->nullable(); // Apr 2026
            $table->date('service_start')->nullable();
            $table->date('service_end')->nullable();
            $table->string('status')->default('Due'); // Submitted, Due, Awaiting
            $table->integer('required_days_per_week')->default(5);
            $table->json('days')->nullable();      // [{day:1,state:'worked'}...]
            $table->decimal('delivered_hours', 8, 2)->nullable();
            $table->integer('authorized_hours')->nullable();
            $table->integer('excluded_days')->default(0);
            $table->string('exclusion_note')->nullable();
            $table->string('submitted_via')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->boolean('acknowledgments_initialed')->default(false);
            $table->string('wellness_call_note')->nullable();
            $table->timestamps();
        });

        // ── Pay history ──
        Schema::create('pay_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period');             // May 2026
            $table->decimal('hours', 8, 2)->nullable();
            $table->decimal('rate', 8, 2)->nullable();
            $table->decimal('gross', 10, 2)->nullable();
            $table->string('status')->default('Pending'); // Paid, Awaiting form, Pending
            $table->date('paid_date')->nullable();
            $table->string('stub_path')->nullable();
            $table->timestamps();
        });

        // ── Communications log ──
        Schema::create('caregiver_communications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('channel')->default('App');     // Call, SMS, Email, App, Wellness
            $table->string('direction')->nullable();        // Inbound, Outbound, Push, From app
            $table->text('body')->nullable();
            $table->string('tag')->nullable();              // Automated, AI Secretary
            $table->string('meta')->nullable();             // "RingCentral · 4m 12s"
            $table->dateTime('occurred_at')->nullable();
            $table->timestamps();
        });

        // ── Notes & activity feed ──
        Schema::create('caregiver_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('author_name');
            $table->string('author_role')->nullable();      // Owner, Front desk, AI
            $table->string('author_type')->default('human'); // human, ai, agent
            $table->string('tag')->nullable();              // General, Reminder, Concern, Activity, Pay, Checks, Approval
            $table->text('body');
            $table->boolean('pinned')->default(false);
            $table->dateTime('noted_at')->nullable();
            $table->timestamps();
        });

        // ── Tamper-proof audit log ──
        Schema::create('caregiver_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id')->index();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('actor_name');
            $table->string('actor_role')->nullable();
            $table->string('actor_type')->default('human'); // human, ai
            $table->string('action');
            $table->string('entity')->nullable();
            $table->string('value_before')->nullable();
            $table->string('value_after')->nullable();
            $table->text('detail')->nullable();
            $table->string('source')->nullable();
            $table->dateTime('occurred_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caregiver_audit_logs');
        Schema::dropIfExists('caregiver_notes');
        Schema::dropIfExists('caregiver_communications');
        Schema::dropIfExists('pay_records');
        Schema::dropIfExists('compliance_forms');
        Schema::dropIfExists('caregiver_assignments');
        Schema::dropIfExists('background_checks');

        Schema::table('employees', function (Blueprint $table) {
            foreach ([
                'gender', 'ssn_last4', 'county', 'preferred_language_extra',
                'emergency_contact_email', 'profile_photo', 'needs_accommodations',
                'caregiver_type', 'relationship_to_client', 'how_recruited',
                'pay_type', 'pay_schedule', 'w4_filing_status', 'direct_deposit_last4',
                'insurance_coverage', 'classification', 'payroll_system',
                'onboarding_status', 'onboarded_by', 'champs_provider_id',
                'champs_status', 'milogin_user_id', 'attestation_status',
                'years_experience', 'prior_experience', 'services', 'hourly_wage',
                'lives_with_client', 'live_in', 'evv_exempt', 'notes',
                'activated_at', 'pay_eligibility_start', 'attestation_expires_at',
                'application_signed_at',
            ] as $col) {
                if (Schema::hasColumn('employees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
