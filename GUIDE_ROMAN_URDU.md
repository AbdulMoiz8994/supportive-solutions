# 📘 Beydoun Tech EMR/CRM — Complete Developer Guide (Roman Urdu)

> **System**: Laravel 11 | **Database**: MySQL | **Server**: XAMPP (localhost)
> **Project Path**: `C:\xampp\htdocs\beydountech`

---

## 📋 FEHRIST (Table of Contents)

1. [System Kya Hai?](#1-system-kya-hai)
2. [Setup Kaise Karo](#2-setup-kaise-karo)
3. [Database Structure](#3-database-structure)
4. [Data Kaise Add Hota Hai](#4-data-kaise-add-hota-hai)
5. [Modules Aur Unka Kaam](#5-modules-aur-unka-kaam)
6. [Dummy Data (Seed) Kaise Chalao](#6-dummy-data-seed-kaise-chalao)
7. [Routes — Kaun Sa Page Kahan Hai](#7-routes--kaun-sa-page-kahan-hai)
8. [Controllers — Logic Kahan Hai](#8-controllers--logic-kahan-hai)
9. [Naya Data Add Karne Ka Tarika](#9-naya-data-add-karne-ka-tarika)
10. [Common Commands](#10-common-commands)
11. [Troubleshooting](#11-troubleshooting)

---

## 1. System Kya Hai?

**Beydoun Tech** ek **Home Care Agency** ka management system hai.  
Yeh Laravel 11 pe bana hai aur XAMPP local server pe chalta hai.

### System Mein Kya Kya Hai:
| Module | Kaam |
|--------|------|
| **Clients** | Jo logon ko care milti hai unka record |
| **Employees / Caregivers** | Jo kaam karte hain unka record |
| **Intakes** | Naye clients jo abhi register ho rahe hain |
| **Schedules** | Caregiver ka visit schedule |
| **Billing** | Invoice aur payment tracking |
| **Directory** | Doctors, pharmacies, case coordinators |
| **Messages** | Staff ke beech messaging |
| **Documents** | Files aur forms upload |
| **Dashboard** | Sab cheez ka overview |

---

## 2. Setup Kaise Karo

### Step 1: XAMPP Start Karo
```
Apache → Start
MySQL  → Start
```

### Step 2: `.env` File Check Karo
File: `C:\xampp\htdocs\beydountech\.env`
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=healthcare
DB_USERNAME=root
DB_PASSWORD=
```
> ⚠️ Database ka naam **healthcare** hai, beydountech nahi!

### Step 3: Composer Dependencies Install Karo
```bash
cd C:\xampp\htdocs\beydountech
composer install
```

### Step 4: Migrations Chalao (Database Tables Banao)
```bash
php artisan migrate
```

### Step 5: Initial Data (Seeders) Chalao
```bash
php artisan db:seed
```

### Step 6: App Ko Browser Mein Kholo
```
http://localhost/beydountech/public
```

---

## 3. Database Structure

### Saari Tables Aur Unka Kaam:

```
organizations       ← Agency ka record (Beydoun Home Care)
users               ← Login karne wale (Admin, Staff, Caregiver)
statuses            ← Active/Inactive/Pending jaise statuses lookup
coverage_types      ← Medicaid coverage types

clients             ← Jo logon ko care milti hai
employees           ← Jo kaam karte hain (caregivers, nurses, staff)
intakes             ← Naye prospects/leads

client_employee     ← Kon caregiver kon client pe hai (assignment)
care_details        ← Prior Authorization (T019 billing code)
schedules           ← Har visit ka schedule (date, time, status)
billings            ← Invoice records
contacts            ← Directory entries (doctors, pharmacies, etc.)
messages            ← Staff messaging
documents           ← Uploaded files
activity_logs       ← System audit trail
```

### Organization ID Ka Concept:
Har record mein `organization_id` hota hai — yeh **Beydoun Home Care** ki ID hai.  
Jab bhi naya data add karo, `organization_id` zaroor dena.

---

## 4. Data Kaise Add Hota Hai

### 4 Tarike Hain Data Add Karne Ke:

---

### ① UI Se (Browser Se) — Sabse Aasaan

1. Browser mein jao: `http://localhost/beydountech/public`
2. Login karo:
   - Email: `admin@beydountech.com`
   - Password: (jo setup ke waqt set kiya)
3. Left sidebar se module choose karo
4. "Add New" button click karo
5. Form bharo aur save karo

---

### ② Seeder Se (Dummy/Bulk Data) — Developer Ke Liye

**File**: `database/seeders/DummyDataSeeder.php`

Yeh file mein pehle se data tayaar hai:
- 8 Clients
- 6 Employees  
- 6 Intakes
- Schedules, Billings, Documents, etc.

**Chalane Ka Tarika:**
```bash
php artisan db:seed --class=DummyDataSeeder
```

**Agar pehle sab clear karna ho:**
```bash
php artisan migrate:fresh --seed
```
> ⚠️ Yeh command **sab kuch delete** kar deta hai aur fresh start karta hai!

---

### ③ Tinker Se (Quick Testing) — Command Line

```bash
php artisan tinker
```

**Tinker mein Client add karo:**
```php
use App\Models\Client;

Client::create([
    'organization_id' => 1,
    'first_name'      => 'Ali',
    'last_name'       => 'Khan',
    'dob'             => '1955-06-15',
    'phone'           => '(313) 555-1234',
    'email'           => 'ali.khan@mail.com',
    'member_id'       => 'MD-200001',
    'address'         => '100 Main St, Detroit MI 48201',
    'county'          => 'Wayne',
    'billing_rate'    => 18.50,
    'status_id'       => \App\Models\Status::where('name','Active')->value('id'),
]);
```

**Tinker mein Employee add karo:**
```php
use App\Models\Employee;

Employee::create([
    'organization_id' => 1,
    'first_name'      => 'Fatima',
    'last_name'       => 'Ahmed',
    'email'           => 'fatima.a@agency.com',
    'phone'           => '(313) 555-5678',
    'position'        => 'Caregiver',
    'hire_date'       => '2026-01-01',
    'address'         => '200 Oak Ave, Detroit MI',
    'status_id'       => \App\Models\Status::where('name','Active')->value('id'),
]);
```

---

### ④ Direct DB::table Se — Advanced

```php
use Illuminate\Support\Facades\DB;

DB::table('schedules')->insert([
    'organization_id' => 1,
    'client_id'       => 1,
    'employee_id'     => 1,
    'date'            => '2026-04-15',
    'start_time'      => '09:00:00',
    'end_time'        => '13:00:00',
    'status'          => 'Scheduled',
    'evv_status'      => 0,
    'created_at'      => now(),
    'updated_at'      => now(),
]);
```

---

## 5. Modules Aur Unka Kaam

### 🏠 Clients Module
**Kya hai:** Jo logon ko home care milti hai unka poora record.

**Important Fields:**
- `member_id` — Medicaid member ID (unique honi chahiye)
- `billing_rate` — Per hour rate (e.g., 18.50)
- `status_id` — Active / Inactive / Hold
- `coverage_type_id` — Medicaid coverage type
- `county` — Wayne / Macomb / Oakland

**Files:**
- Controller: `app/Http/Controllers/ClientController.php`
- View: `resources/views/pages/clients/`
- Model: `app/Models/Client.php`

---

### 👷 Employees Module
**Kya hai:** Caregivers, nurses, office staff ka record.

**Important Fields:**
- `position` — Caregiver / Nurse / Office Staff
- `hire_date` — Jab se kaam kar rahe hain
- `status_id` — Active / On Leave / Terminated

**Files:**
- Controller: `app/Http/Controllers/EmployeeController.php`
- Model: `app/Models/Employee.php`

---

### 📋 Intakes Module
**Kya hai:** Naye potential clients (leads) jo abhi sign up kar rahe hain.

**Status Flow:**
```
New Lead → Contacted → Pending → Active Client
```

**Important Fields:**
- `source` — Referral / Walk-In / Hospital Discharge / Facebook Ad / Google
- `status` — New / Contacted / Pending

**Files:**
- Controller: `app/Http/Controllers/IntakeController.php`
- Model: `app/Models/Intake.php`

---

### 📅 Schedule Module
**Kya hai:** Caregiver ka daily visit schedule.

**Status Types:**
- `Scheduled` — Future visit
- `Completed` — Visit ho gayi, clock-in clock-out hua
- `Missed` — Visit nahi hui
- `Cancelled` — Cancel ho gayi

**EVV (Electronic Visit Verification):**
- `evv_status = 1` — GPS se verify hua
- `actual_clock_in` / `actual_clock_out` — Real time records
- `clock_in_latitude` / `clock_in_longitude` — GPS coordinates

**Files:**
- Controller: `app/Http/Controllers/ScheduleController.php`
- Caregiver View: `resources/views/pages/schedule/caregiver-index.blade.php`

---

### 💰 Billing Module
**Kya hai:** Client ko jo invoice milta hai uska record.

**Status Types:**
- `Pending` — Abhi submit nahi hua
- `Sent` — Medicaid ko bheja gaya
- `Paid` — Payment aa gayi
- `Denied` — Reject hua

**Invoice Number Format:** `INV-2026-0001`

**Files:**
- Controller: `app/Http/Controllers/BillingController.php`
- Model: `app/Models/Billing.php`

---

### 📁 Documents Module
**Kya hai:** Client aur employee ke documents upload hote hain.

**Document Types:**
- `ID` — Medicaid ID Card, Driver's License
- `Medical Form` — Plan of Care, Physician Order
- `Signed Agreement` — Care agreement
- `HR Document` — Background check
- `Certification` — CPR, Nursing License

**Files:**
- Controller: `app/Http/Controllers/DocumentController.php`
- Upload Path: `storage/app/public/documents/`

---

### 📞 Directory Module
**Kya hai:** Doctors, pharmacies, case coordinators ka contact database.

**Contact Types:**
- `Physician` — Doctor
- `Case Coordinator` — DHS coordinator
- `Pharmacy` — Dawakhana
- `Lab` — Lab facility
- `Hospice` — Hospice care

**Files:**
- Controller: `app/Http/Controllers/DirectoryController.php`
- Model: `app/Models/Contact.php`

---

### 💬 Messages Module
**Kya hai:** Admin, Staff aur Caregivers ke beech internal messaging.

**Files:**
- Controller: `app/Http/Controllers/MessageController.php`
- Model: `app/Models/Message.php`
- View: `resources/views/pages/messages/chat.blade.php`

---

## 6. Dummy Data (Seed) Kaise Chalao

### Pehli Baar (Fresh Setup):
```bash
# Database reset karo aur sab seeders chalao
php artisan migrate:fresh --seed
```

### Sirf DummyDataSeeder Chalana Ho:
```bash
php artisan db:seed --class=DummyDataSeeder
```

### Seeders Ka Order:
`DatabaseSeeder.php` mein yeh order hona chahiye:
```php
$this->call([
    StatusSeeder::class,        // Pehle statuses
    CoverageTypeSeeder::class,  // Coverage types
    UserSeeder::class,          // Admin/Staff/Caregiver users
    DummyDataSeeder::class,     // Baaki sab data
]);
```

### DummyDataSeeder Mein Kya Data Hai:
| Section | Records |
|---------|---------|
| Clients | 8 clients (Wayne, Macomb, Oakland counties) |
| Employees | 6 (4 Caregivers, 1 Nurse, 1 Office Staff) |
| Intakes | 6 leads (New, Contacted, Pending status) |
| Care Details | 5 prior authorizations (T019) |
| Assignments | 6 client-caregiver assignments |
| Schedules | 11 visits (5 completed, 1 missed, 5 upcoming) |
| Billings | 7 invoices |
| Directory | 8 contacts |
| Messages | 7 messages |
| Documents | 9 documents |

---

## 7. Routes — Kaun Sa Page Kahan Hai

File: `routes/web.php`

### Main Routes:
```
GET  /                          → Dashboard
GET  /clients                   → Clients list
GET  /clients/create            → Naya client form
POST /clients                   → Client save karo
GET  /clients/{id}              → Client detail
GET  /clients/{id}/edit         → Edit form

GET  /employees                 → Employees list
GET  /employees/create          → Naya employee form
POST /employees                 → Employee save

GET  /intakes                   → Intakes/Leads list
GET  /intakes/create            → Naya intake form

GET  /schedule                  → Schedule calendar
GET  /schedule/caregiver        → Caregiver ka schedule

GET  /billing                   → Billing list
GET  /billing/create            → Naya invoice

GET  /directory                 → Directory list

GET  /messages                  → Messages inbox
GET  /messages/{userId}         → Specific user ke messages

GET  /documents                 → Documents list
```

---

## 8. Controllers — Logic Kahan Hai

| Controller | Kaam |
|-----------|------|
| `DashboardController.php` | Home page stats aur overview |
| `ClientController.php` | Client CRUD operations |
| `EmployeeController.php` | Employee CRUD operations |
| `IntakeController.php` | Intake/Lead management |
| `ScheduleController.php` | Visit scheduling |
| `BillingController.php` | Invoice management |
| `DirectoryController.php` | Contact directory |
| `MessageController.php` | Internal messaging |
| `DocumentController.php` | File upload/download |
| `AuthController.php` | Login/Logout |
| `ComplianceController.php` | Compliance reports |
| `CalendarController.php` | Calendar view |

---

## 9. Naya Data Add Karne Ka Tarika

### ➕ Naya Client Add Karna (UI Se):
1. `http://localhost/beydountech/public/clients/create`
2. Form mein yeh fields zaroori hain:
   - First Name, Last Name
   - Date of Birth
   - Phone Number
   - Member ID (Medicaid number — unique hona chahiye)
   - Address, County
   - Billing Rate (per hour)
3. "Save" click karo

---

### ➕ Naya Employee Add Karna (UI Se):
1. `http://localhost/beydountech/public/employees/create`
2. Zaroori fields:
   - First Name, Last Name
   - Email (unique hona chahiye)
   - Phone, Position
   - Hire Date
3. "Save" click karo

---

### ➕ Seeder Mein Naya Data Add Karna:
File: `database/seeders/DummyDataSeeder.php`

**Client Add Karne Ke Liye** — `$clientsData` array mein add karo:
```php
['first_name'=>'Ahmad',   'last_name'=>'Siddiqui', 'dob'=>'1952-03-10',
 'phone'=>'(313) 555-9999','email'=>'ahmad.s@mail.com',
 'county'=>'Wayne','member_id'=>'MD-100099','status'=>'Active',
 'billing_rate'=>19.00,'address'=>'500 New St, Detroit MI 48201'],
```

**Employee Add Karne Ke Liye** — `$employeesData` array mein add karo:
```php
['first_name'=>'Zainab', 'last_name'=>'Ali', 'email'=>'zainab.a@agency.com',
 'phone'=>'(313) 555-8888','position'=>'Caregiver','status'=>'Active',
 'hire_date'=>'2026-04-01','address'=>'300 Park Ave, Detroit MI'],
```

Phir seed command chalao:
```bash
php artisan db:seed --class=DummyDataSeeder
```

---

### ➕ Schedule (Visit) Add Karna:
1. `http://localhost/beydountech/public/schedule`
2. Calendar pe date choose karo
3. Client aur Caregiver select karo
4. Time set karo
5. Save karo

---

### ➕ Document Upload Karna:
1. Client profile page pe jao
2. "Documents" tab click karo
3. "Upload Document" button
4. File select karo (PDF, images)
5. Document type aur category set karo
6. Save karo

---

## 10. Common Commands

### Laravel Commands:
```bash
# Database reset aur fresh seed
php artisan migrate:fresh --seed

# Sirf specific seeder chalao
php artisan db:seed --class=DummyDataSeeder

# Cache clear karo
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear

# All cache clear ek saath
php artisan optimize:clear

# Routes list dekho
php artisan route:list

# Models list dekho
php artisan model:show Client

# Interactive shell (testing ke liye)
php artisan tinker

# Storage link banao (documents ke liye)
php artisan storage:link

# App key generate karo (fresh install pe)
php artisan key:generate
```

### Composer Commands:
```bash
# Dependencies install karo
composer install

# Autoload refresh karo
composer dump-autoload
```

### npm Commands (CSS/JS build):
```bash
# Development mode
npm run dev

# Production build
npm run build
```

---

## 11. Troubleshooting

### ❌ Problem: Page nahi khul raha (500 Error)
**Hal:**
```bash
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear
```

---

### ❌ Problem: Database Table nahi mili
**Hal:** Migrations dobara chalao
```bash
php artisan migrate
# Ya fresh start karo:
php artisan migrate:fresh --seed
```

---

### ❌ Problem: Documents upload nahi ho rahe
**Hal:** Storage link banao
```bash
php artisan storage:link
```
Phir check karo ke `storage/app/public` folder exist karta hai.

---

### ❌ Problem: Seeder fail ho raha hai (Status ID null)
**Wajah:** Status seeder pehle nahi chala.
**Hal:**
```bash
php artisan db:seed --class=StatusSeeder
php artisan db:seed --class=DummyDataSeeder
```

---

### ❌ Problem: Login kaam nahi kar raha
**Hal:**
1. `.env` file mein `APP_KEY` check karo
2. Agar khali hai: `php artisan key:generate`
3. `Users` table mein admin account check karo: `php artisan tinker` → `User::all()`

---

### ❌ Problem: localhost pe kuch nahi dikh raha
**Check karo:**
1. XAMPP mein Apache aur MySQL dono running hain?
2. URL sahi hai? → `http://localhost/beydountech/public`
3. `.htaccess` file exist karti hai project mein?

---

## 📂 Important File Locations

```
beydountech/
├── .env                          ← Database credentials
├── app/
│   ├── Http/Controllers/         ← Business logic
│   └── Models/                   ← Database models
├── database/
│   ├── migrations/               ← Table definitions
│   └── seeders/
│       └── DummyDataSeeder.php   ← Test data ← YEH FILE
├── resources/
│   └── views/
│       └── pages/                ← Blade templates (HTML)
│           ├── clients/
│           ├── employees/
│           ├── schedule/
│           ├── billing/
│           ├── messages/
│           └── directory/
├── routes/
│   └── web.php                   ← URL routing
└── storage/
    └── app/public/documents/     ← Uploaded files
```

---

## 🔑 Default Login Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@beydountech.com | (seeder se set hota hai) |
| Staff | staff@beydountech.com | (seeder se set hota hai) |
| Caregiver | caregiver@beydountech.com | (seeder se set hota hai) |

> Password check karne ke liye: `php artisan tinker` → `User::all(['email','name'])`

---

## 💡 Quick Tips

1. **Har record mein `organization_id` = 1** — Beydoun Home Care ki ID
2. **`firstOrCreate` use karo** — Duplicate records se bachne ke liye
3. **`insertOrIgnore` use karo** — Bulk insert mein duplicate skip karne ke liye
4. **Status IDs dynamic hain** — Hard-code mat karo, `Status::where('name','Active')->value('id')` use karo
5. **`php artisan optimize:clear`** — Jab bhi koi cheez kaam na kare, yeh pehle chalao

---

*Guide Version: 1.0 | Banaya: April 2026 | Project: Beydoun Tech EMR/CRM*
