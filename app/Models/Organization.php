<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'contact_info',
        'status',
        'agency_npi',
        'tax_id_ein',
        'medicaid_provider_id',
        'legal_business_name',
        'legal_address_street',
        'legal_address_city',
        'legal_address_state',
        'legal_address_zip',
        'main_phone',
        'efax_number',
        'service_state',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function clients()
    {
        return $this->hasMany(Client::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function intakes()
    {
        return $this->hasMany(Intake::class);
    }

    public function requestTemplates()
    {
        return $this->hasMany(RequestTemplate::class);
    }
}
