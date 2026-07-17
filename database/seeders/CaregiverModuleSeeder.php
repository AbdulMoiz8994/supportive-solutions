<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsOrganizationContext;
use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Status;
use App\Models\BackgroundCheck;
use App\Models\CaregiverAssignment;
use App\Models\ComplianceForm;
use App\Models\PayRecord;
use App\Models\CaregiverCommunication;
use App\Models\CaregiverNote;
use App\Models\CaregiverAuditLog;
use Carbon\Carbon;

class CaregiverModuleSeeder extends Seeder
{
    use SeedsOrganizationContext;

    public function run(): void
    {
        $org   = $this->organization();
        $orgId = $org->id;
        $s     = fn ($entity, $name) => Status::where('entity_type', $entity)->where('name', $name)->value('id');

        // ── Client served: Maria Hassan ───────────────────────────────
        $maria = Client::updateOrCreate(
            ['member_id' => 'MD-200042', 'organization_id' => $orgId],
            [
                'first_name'   => 'Maria',
                'last_name'    => 'Hassan',
                'dob'          => '1948-09-12',
                'phone'        => '(313) 555-0142',
                'email'        => 'maria.hassan@example.com',
                'county'       => 'Wayne',
                'status'       => 'Active',
                'status_id'    => $s('Client', 'Active'),
                'billing_rate' => 30.00,
                'address'      => '15230 Michigan Ave, Dearborn, MI 48126',
            ]
        );

        // ── The fully-detailed demo caregiver: Yousef Hassan ──────────
        $yousef = Employee::updateOrCreate(
            ['email' => 'yousef.hassan@example.com', 'organization_id' => $orgId],
            [
                'first_name'              => 'Yousef',
                'last_name'               => 'Hassan',
                'position'                => 'Caregiver',
                'phone'                   => '(313) 555-0142',
                'address'                 => '15230 Michigan Ave, Dearborn, MI 48126',
                'city'                    => 'Dearborn',
                'state'                   => 'MI',
                'zip_code'                => '48126',
                'county'                  => 'Wayne',
                'gender'                  => 'Male',
                'date_of_birth'           => '1981-05-09',
                'ssn_last4'               => '4729',
                'preferred_language'      => 'Arabic',
                'status'                  => 'Active',
                'status_id'               => $s('Employee', 'Active'),
                // eligibility / disclosures
                'is_18_plus'              => true,
                'is_work_eligible'        => true,
                'has_background_check'    => true,
                'needs_accommodations'    => false,
                // employment & services
                'caregiver_type'          => 'Family',
                'relationship_to_client'  => 'Son',
                'how_recruited'           => 'Family of client (onboarded with Maria\'s intake)',
                'prior_experience'        => true,
                'years_experience'        => '3+',
                'services'                => [
                    'Eating', 'Bathing', 'Toileting', 'Dressing', 'Grooming', 'Mobility',
                    'Transferring', 'Meal Preparation', 'Housework', 'Laundry',
                    'Shopping (food / meds)', 'Taking Medication',
                ],
                'hourly_wage'             => 15.00,
                'pay_type'                => 'W-2 · hourly',
                'pay_schedule'            => 'Monthly · 1st Tue batch',
                'w4_filing_status'        => 'Single',
                'direct_deposit_last4'    => '4821',
                'insurance_coverage'      => 'Waived — covered under spouse\'s plan',
                'classification'          => 'W-2 employee · non-exempt',
                'payroll_system'          => 'AccountantsWorld',
                'hire_date'               => '2026-02-01',
                'pay_eligibility_start'   => '2026-01-28',
                // live-in / EVV
                'lives_with_client'       => true,
                'live_in'                 => true,
                'evv_exempt'              => true,
                'attestation_status'      => 'Approved through Nov 2026 (renew yearly)',
                'attestation_expires_at'  => '2026-11-30',
                // CHAMPS / access
                'champs_provider_id'      => '30xxxxx91',
                'champs_status'           => 'Approved & associated to SSHC',
                'champs_association_date' => '2026-01-26',
                'milogin_user_id'         => 'yhassan-mi',
                // onboarding
                'onboarding_status'       => 'Active',
                'onboarded_by'            => 'R. Saleh (front desk)',
                'application_signed_at'   => '2026-01-22',
                'activated_at'            => '2026-02-01',
                // emergency contact
                'emergency_contact_name'         => 'Layla Hassan',
                'emergency_contact_relationship' => 'Spouse (wife)',
                'emergency_contact_phone'        => '(313) 555-0188',
                'emergency_contact_email'        => 'l.hassan@example.com',
                'notes'                   => 'Has cared for his mother at home for several years; no formal employment history required for a family caregiver.',
            ]
        );

        // Wipe & re-seed the demo caregiver's child records (idempotent re-runs)
        BackgroundCheck::where('employee_id', $yousef->id)->delete();
        CaregiverAssignment::where('employee_id', $yousef->id)->delete();
        ComplianceForm::where('employee_id', $yousef->id)->delete();
        PayRecord::where('employee_id', $yousef->id)->delete();
        CaregiverCommunication::where('employee_id', $yousef->id)->delete();
        CaregiverNote::where('employee_id', $yousef->id)->delete();
        CaregiverAuditLog::where('employee_id', $yousef->id)->delete();

        // keep the pivot in sync too
        $yousef->clients()->syncWithoutDetaching([$maria->id]);

        // ── Background checks ─────────────────────────────────────────
        $checks = [
            ['type'=>'CHAMPS','label'=>'CHAMPS','cadence'=>'One-time at hiring + ongoing monitor','status'=>'Clear','result'=>'Enrolled & associated','last_run'=>'2026-01-26','next_due'=>null,'source'=>'CHAMPS portal (RPA)','provider_id'=>'30xxxxx91','monitoring'=>'Active (continuous)'],
            ['type'=>'ICHAT','label'=>'ICHAT','cadence'=>'Annual','status'=>'Clear','result'=>'No record','last_run'=>'2026-01-27','next_due'=>'2027-01-27','source'=>'ICHAT portal (RPA)'],
            ['type'=>'SAM','label'=>'SAM.gov','cadence'=>'Monthly','status'=>'Clear','result'=>'No exclusion','last_run'=>'2026-05-01','next_due'=>'2026-06-01','source'=>'SAM.gov API (free)'],
            ['type'=>'OIG','label'=>'OIG LEIE','cadence'=>'Monthly','status'=>'Clear','result'=>'Not excluded','last_run'=>'2026-01-27','next_due'=>'2027-01-27','source'=>'OIG LEIE download (free)'],
            ['type'=>'TB','label'=>'TB Test','cadence'=>'Annual (optional)','status'=>'On file','result'=>'Negative','last_run'=>'2026-01-24','source'=>'Custom · uploaded','is_custom'=>true],
            ['type'=>'MVR','label'=>'Driving Record (MVR)','status'=>'Exempted','is_custom'=>true,'is_exempt'=>true,'exempt_reason'=>'Live-in; no transportation duties','approved_by'=>'Ali Beydoun','approved_at'=>'2026-02-01'],
        ];
        foreach ($checks as $c) {
            BackgroundCheck::create(array_merge(['organization_id'=>$orgId,'employee_id'=>$yousef->id], $c));
        }

        // ── Assignment ────────────────────────────────────────────────
        CaregiverAssignment::create([
            'organization_id'   => $orgId,
            'employee_id'       => $yousef->id,
            'client_id'         => $maria->id,
            'relationship'      => 'Mother',
            'program'           => 'MICH · Aetna Better Health',
            'authorized_hours'  => 120,
            'scheduled_hours'   => 28,
            'authorization_no'  => 'PA-2026-0042',
            'live_in'           => true,
            'evv_status'        => 'Exempt (live-in)',
            'compliance_status' => 'May due',
            'status'            => 'Active',
            'assigned_since'    => '2026-02-01',
        ]);

        // ── Compliance forms (Feb–May) ────────────────────────────────
        $aprDays = [];
        for ($d = 1; $d <= 30; $d++) {
            $aprDays[] = ['day' => $d, 'state' => in_array($d, [10, 11, 12]) ? 'excluded' : 'worked'];
        }
        $forms = [
            ['period'=>'2026-02','period_label'=>'Feb 2026','status'=>'Submitted','delivered_hours'=>120,'authorized_hours'=>120,'submitted_at'=>'2026-02-28 16:00:00','submitted_via'=>'Caregiver app','acknowledgments_initialed'=>true,'service_start'=>'2026-02-01','service_end'=>'2026-02-28'],
            ['period'=>'2026-03','period_label'=>'Mar 2026','status'=>'Submitted','delivered_hours'=>120,'authorized_hours'=>120,'submitted_at'=>'2026-03-31 16:00:00','submitted_via'=>'Caregiver app','acknowledgments_initialed'=>true,'service_start'=>'2026-03-01','service_end'=>'2026-03-31'],
            ['period'=>'2026-04','period_label'=>'Apr 2026','status'=>'Submitted','delivered_hours'=>108,'authorized_hours'=>120,'excluded_days'=>3,'exclusion_note'=>'Apr 10–12 excluded — client hospitalized (not claimed). Yousef still met his required 5 days in every week, so there\'s no shortfall.','days'=>$aprDays,'submitted_at'=>'2026-04-30 17:55:00','submitted_via'=>'Caregiver app','acknowledgments_initialed'=>true,'wellness_call_note'=>'Wellness call Apr 30 (AI · Arabic): confirmed services; client recovering after hospital stay.','service_start'=>'2026-04-01','service_end'=>'2026-04-30'],
            ['period'=>'2026-05','period_label'=>'May 2026','status'=>'Due','authorized_hours'=>120,'service_start'=>'2026-05-01','service_end'=>'2026-05-31'],
        ];
        foreach ($forms as $f) {
            ComplianceForm::create(array_merge([
                'organization_id'=>$orgId,'employee_id'=>$yousef->id,'client_id'=>$maria->id,'required_days_per_week'=>5,
            ], $f));
        }

        // ── Pay history ───────────────────────────────────────────────
        $pays = [
            ['period'=>'Feb 2026','hours'=>120,'rate'=>15,'gross'=>1800,'status'=>'Paid','paid_date'=>'2026-03-15'],
            ['period'=>'Mar 2026','hours'=>120,'rate'=>15,'gross'=>1800,'status'=>'Paid','paid_date'=>'2026-04-15'],
            ['period'=>'Apr 2026','hours'=>108,'rate'=>15,'gross'=>1620,'status'=>'Paid','paid_date'=>'2026-05-15'],
            ['period'=>'May 2026','rate'=>15,'status'=>'Awaiting form'],
        ];
        foreach ($pays as $p) {
            PayRecord::create(array_merge(['organization_id'=>$orgId,'employee_id'=>$yousef->id,'client_id'=>$maria->id], $p));
        }

        // ── Communications ────────────────────────────────────────────
        $comms = [
            ['title'=>'App reminder','channel'=>'App','direction'=>'Push + SMS','tag'=>'Automated','body'=>'"Your May compliance form for Maria is now available — please complete it after the wellness call." (Arabic + English)','meta'=>'SSHC app','occurred_at'=>'2026-05-17 08:00:00'],
            ['title'=>'Pay stub ready','channel'=>'App','direction'=>'Push','tag'=>'Automated','body'=>'Your April pay stub ($1,620.00) is available in My Pay. Direct deposit sent.','meta'=>'SSHC app','occurred_at'=>'2026-05-17 08:00:00'],
            ['title'=>'Wellness call','channel'=>'Wellness','direction'=>'Outbound','tag'=>'AI Secretary · Arabic','body'=>'April end-of-month call. Confirmed services; client home and recovering after the Apr 10–12 hospital stay; reminded to log the hospital days.','meta'=>'RingCentral · 4m 12s','occurred_at'=>'2026-05-17 08:00:00'],
            ['title'=>'Compliance form submitted','channel'=>'App','direction'=>'From app','tag'=>'Automated','body'=>'Yousef submitted April\'s compliance form for Maria — 108 hrs, 3 hospital days excluded. Received & verified.','meta'=>'SSHC app','occurred_at'=>'2026-05-17 08:00:00'],
            ['title'=>'Yousef Hassan','channel'=>'SMS','direction'=>'Outbound SMS','tag'=>'AI Secretary','body'=>'Glad Maria is home. Please mark the hospital days (Apr 10–12) on this month\'s form so they aren\'t billed.','meta'=>'RingCentral SMS','occurred_at'=>'2026-05-17 08:00:00'],
            ['title'=>'Yousef Hassan','channel'=>'Call','direction'=>'Inbound call','tag'=>'Automated','body'=>'Reported Maria admitted to the hospital (Apr 10). AI logged the hospitalization on her chart and noted the billing exclusion.','meta'=>'RingCentral · 3m 05s','occurred_at'=>'2026-04-10 09:30:00'],
            ['title'=>'App reminder','channel'=>'Email','direction'=>'Push + SMS','tag'=>'Automated','body'=>'Welcome & app invite — activation code, how to clock-in is N/A (live-in), and how to submit the monthly form. (Arabic + English)','meta'=>'Google Workspace','occurred_at'=>'2026-02-01 09:00:00'],
        ];
        foreach ($comms as $c) {
            CaregiverCommunication::create(array_merge(['organization_id'=>$orgId,'employee_id'=>$yousef->id], $c));
        }

        // ── Notes & activity ──────────────────────────────────────────
        $notes = [
            ['author_name'=>'Ali Beydoun','author_role'=>'Owner','author_type'=>'human','tag'=>'General','pinned'=>true,'body'=>'Arabic-only — reach via the app or SMS. Reliable family caregiver (son), lives with Maria. Prefers end-of-day contact.','noted_at'=>'2026-02-02 10:00:00'],
            ['author_name'=>'Background Checks Agent','author_role'=>'AI','author_type'=>'ai','tag'=>'Reminder','pinned'=>true,'body'=>'ICHAT renews Jan 2027. Agent will re-run automatically ~30 days before; SAM & OIG re-run monthly. No action needed now.','noted_at'=>'2026-01-27 09:00:00'],
            ['author_name'=>'Payroll Agent','author_role'=>'AI','author_type'=>'ai','tag'=>'Activity','body'=>'April pay $1,620 (108 hrs × $15) released after the grace window — direct deposit ••••4821. Stub posted to the app.','noted_at'=>'2026-05-15 09:08:40'],
            ['author_name'=>'Compliance Agent','author_role'=>'AI','author_type'=>'ai','tag'=>'Activity','body'=>'April compliance form submitted via app for Maria — 108 hrs, 3 hospital days excluded. Verified.','noted_at'=>'2026-04-30 17:55:00'],
            ['author_name'=>'AI Secretary','author_role'=>'AI','author_type'=>'ai','tag'=>'Concern','body'=>'April wellness call — Yousef reported Maria recovering after the hospital stay. Recommend confirming follow-up; watch May attendance. Routed to task queue.','noted_at'=>'2026-04-30 18:10:00'],
            ['author_name'=>'AI Secretary','author_role'=>'AI','author_type'=>'ai','tag'=>'Activity','body'=>'Logged inbound call — reported Maria\'s hospitalization (Apr 10); flagged billing exclusion on her chart.','noted_at'=>'2026-04-10 09:35:00'],
            ['author_name'=>'Payroll Agent','author_role'=>'AI','author_type'=>'ai','tag'=>'Pay','body'=>'Feb & Mar pay released — $1,800 each (120 hrs × $15). No holds.','noted_at'=>'2026-04-15 09:00:00'],
            ['author_name'=>'Authorizations Agent','author_role'=>'AI','author_type'=>'ai','tag'=>'Activity','body'=>'Live-In Attestation (BPHASA-2421) approved through Nov 2026 → EVV exempt.','noted_at'=>'2026-01-30 15:12:03'],
            ['author_name'=>'Background Checks Agent','author_role'=>'AI','author_type'=>'ai','tag'=>'Checks','body'=>'CHAMPS approved & associated to SSHC (Provider ID 30xxxxx91); ICHAT, SAM, OIG all clear → eligible to work.','noted_at'=>'2026-01-26 14:40:55'],
            ['author_name'=>'R. Saleh','author_role'=>'Front desk','author_type'=>'human','tag'=>'Activity','body'=>'Application & policies signed; caregiver verbal review completed (8 acknowledgments initialed).','noted_at'=>'2026-01-22 14:22:08'],
            ['author_name'=>'Ali Beydoun','author_role'=>'Owner','author_type'=>'human','tag'=>'Approval','body'=>'Activated & assigned to Maria Hassan. Caregiver profile created → active.','noted_at'=>'2026-02-01 10:06:02'],
        ];
        foreach ($notes as $n) {
            CaregiverNote::create(array_merge(['organization_id'=>$orgId,'employee_id'=>$yousef->id], $n));
        }

        // ── Audit trail ───────────────────────────────────────────────
        $audits = [
            ['occurred_at'=>'2026-05-15 09:08:40','actor_name'=>'Payroll Agent','actor_role'=>'AI','actor_type'=>'ai','action'=>'Pay released','entity'=>'Pay & Payroll › Apr 2026','detail'=>'108 hrs × $15 = $1,620.00 · direct deposit ••••4821','value_after'=>'$1,620.00','source'=>'AccountantsWorld'],
            ['occurred_at'=>'2026-05-10 11:22:31','actor_name'=>'Ali Beydoun','actor_role'=>'Owner','actor_type'=>'human','action'=>'Field edited','entity'=>'Pay & Payroll › Hourly wage','value_before'=>'$14.50 / hr','value_after'=>'$15.00 / hr','source'=>'App (web) · 10.0.4.21'],
            ['occurred_at'=>'2026-04-30 17:55:10','actor_name'=>'Compliance Agent','actor_role'=>'AI','actor_type'=>'ai','action'=>'Compliance form received','entity'=>'Compliance › Apr 2026 (Maria)','detail'=>'108 hrs · 3 hospital days excluded · required days met','source'=>'Caregiver app'],
            ['occurred_at'=>'2026-04-22 13:08:40','actor_name'=>'Ali Beydoun','actor_role'=>'Owner','actor_type'=>'human','action'=>'PHI accessed (view)','entity'=>'Viewed › Personal & Employment','source'=>'App (web) · 10.0.4.21'],
            ['occurred_at'=>'2026-04-12 09:30:11','actor_name'=>'Background Checks Agent','actor_role'=>'AI','actor_type'=>'ai','action'=>'Check re-run','entity'=>'Background Checks › SAM.gov & OIG','detail'=>'Monthly re-run','value_after'=>'Clear','source'=>'SAM API / OIG download'],
            ['occurred_at'=>'2026-02-01 10:06:02','actor_name'=>'Ali Beydoun','actor_role'=>'Owner','actor_type'=>'human','action'=>'Activated & assigned','entity'=>'Status','value_before'=>'Pending onboarding','value_after'=>'Active','detail'=>'assigned to Maria Hassan','source'=>'Approval Queue'],
            ['occurred_at'=>'2026-02-01 10:04:18','actor_name'=>'Ali Beydoun','actor_role'=>'Owner','actor_type'=>'human','action'=>'Check exemption added','entity'=>'Background Checks › Driving Record (MVR)','value_after'=>'Exempted','detail'=>'reason: live-in, no transportation duties','source'=>'App (web)'],
            ['occurred_at'=>'2026-01-30 15:12:03','actor_name'=>'Authorizations Agent','actor_role'=>'AI','actor_type'=>'ai','action'=>'Attestation recorded','entity'=>'Live-In Attestation (BPHASA-2421)','value_after'=>'Approved through Nov 2026','detail'=>'EVV exempt','source'=>'Upload'],
            ['occurred_at'=>'2026-01-26 14:40:55','actor_name'=>'CHAMPS Agent','actor_role'=>'AI','actor_type'=>'ai','action'=>'CHAMPS approved & associated','entity'=>'CHAMPS & Provider','value_after'=>'+ Provider ID 30xxxxx91','detail'=>'associated to SSHC','source'=>'CHAMPS portal (RPA)'],
            ['occurred_at'=>'2026-01-26 14:22:09','actor_name'=>'CHAMPS Agent','actor_role'=>'AI','actor_type'=>'ai','action'=>'Credential access','entity'=>'Apps & Access › CHAMPS / MILogin','detail'=>'Agent signed in with stored credentials to submit enrollment','source'=>'Credential vault'],
            ['occurred_at'=>'2026-01-24 11:15:30','actor_name'=>'R. Saleh','actor_role'=>'Front desk','actor_type'=>'human','action'=>'Custom check added','entity'=>'Background Checks › TB Test','value_after'=>'+ TB Test (uploaded)','detail'=>'result: Negative','source'=>'App (web)'],
            ['occurred_at'=>'2026-01-22 14:30:12','actor_name'=>'R. Saleh','actor_role'=>'Front desk','actor_type'=>'human','action'=>'Field set','entity'=>'Compliance › Required days / week','value_after'=>'5 days / week (any days)','detail'=>'from Time & Task','source'=>'App (web)'],
            ['occurred_at'=>'2026-01-22 14:22:08','actor_name'=>'R. Saleh','actor_role'=>'Front desk','actor_type'=>'human','action'=>'Record created','entity'=>'Caregiver profile','detail'=>'Application signed · verbal review · W-4 Single · direct deposit · checks consent · Program set: MICH','source'=>'App (web)'],
        ];
        foreach ($audits as $a) {
            CaregiverAuditLog::create(array_merge(['organization_id'=>$orgId,'employee_id'=>$yousef->id], $a));
        }

        // ── Extra caregivers so the registry looks populated ──────────
        $extra = [
            ['Layla','Ahmed','Female','Family','Arabic','Active','all_clear',true],
            ['Marcus','Rivera','Male','Family','English','Pending onboarding','enrolling',false],
            ['Fatima','Saleh','Female','Family','Arabic','Active','all_clear',true],
            ['James','Miller','Male','Agency','English','Pending onboarding','in_progress',false],
            ['Aisha','Khan','Female','Family','Arabic','Active','ichat_due',true],
            ['Omar','Haddad','Male','Family','Arabic','Active','all_clear',true],
            ['Nadia','Farah','Female','Agency','English','On Hold','flagged',false],
            ['Hassan','Nasser','Male','Family','Arabic','Active','all_clear',true],
            ['Mona','Yousef','Female','Family','Arabic','Active','ichat_due',true],
            ['Sami','Darwish','Male','Family','Arabic','Active','all_clear',false],
        ];
        $checkLabels = [
            'all_clear' => ['All clear','Clear'],
            'ichat_due' => ['ICHAT due 12d','Submitted'],
            'enrolling' => ['Enrolling','Enrolling'],
            'in_progress' => ['In progress','Enrolling'],
            'flagged'   => ['Flag — verify','Flagged'],
        ];
        foreach ($extra as $i => [$fn, $ln, $gender, $type, $lang, $status, $checkKey, $liveIn]) {
            $cg = Employee::updateOrCreate(
                ['email' => strtolower($fn.'.'.$ln).'@example.com', 'organization_id' => $orgId],
                [
                    'first_name'=>$fn,'last_name'=>$ln,'position'=>'Caregiver','gender'=>$gender,
                    'phone'=>'(313) 555-0'.str_pad((string)(200+$i), 3, '0', STR_PAD_LEFT),
                    'date_of_birth'=>'1980-0'.(($i%9)+1).'-1'.(($i%9)+1),
                    'county'=>'Wayne','preferred_language'=>$lang,'caregiver_type'=>$type,
                    'status'=>in_array($status,['Active'])?'Active':$status,
                    'status_id'=>$s('Employee','Active'),
                    'onboarding_status'=>$status,'live_in'=>$liveIn,'evv_exempt'=>$liveIn,
                    'hourly_wage'=>15.00,'ssn_last4'=>(string)(3300+$i),
                    'is_18_plus'=>true,'is_work_eligible'=>true,'has_background_check'=>($checkKey==='all_clear'),
                    'champs_provider_id'=>'30xxxxx'.str_pad((string)($i+10),2,'0',STR_PAD_LEFT),
                    'hire_date'=>'2026-02-01',
                ]
            );
            $cg->clients()->syncWithoutDetaching([$maria->id]);
            BackgroundCheck::where('employee_id', $cg->id)->delete();
            [$lbl, $st] = $checkLabels[$checkKey];
            BackgroundCheck::create(['organization_id'=>$orgId,'employee_id'=>$cg->id,'type'=>'CHAMPS','label'=>'CHAMPS','status'=>$st,'last_run'=>'2026-01-26','source'=>'CHAMPS portal (RPA)']);
            CaregiverAssignment::where('employee_id', $cg->id)->delete();
            CaregiverAssignment::create([
                'organization_id'=>$orgId,'employee_id'=>$cg->id,'client_id'=>$maria->id,
                'relationship'=>$liveIn?'Family':'Caregiver','program'=>'MICH','authorized_hours'=>120,
                'scheduled_hours'=>28,'live_in'=>$liveIn,'evv_status'=>$liveIn?'Exempt (live-in)':'Active',
                'status'=>'Active','assigned_since'=>'2026-02-01','compliance_status'=>'May due',
            ]);
        }

        $this->command->info('✅ Caregiver module seeded: Yousef Hassan (full demo) + '.count($extra).' caregivers, client Maria Hassan, checks, assignments, compliance, pay, comms, notes, audit.');
    }
}
