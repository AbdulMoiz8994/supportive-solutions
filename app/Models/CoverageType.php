<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoverageType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'plan_name',
        'description',
    ];

    public function clients()
    {
        return $this->hasMany(Client::class);
    }
}
