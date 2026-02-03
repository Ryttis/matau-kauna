<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'contact_phone',
        'contact_email',
        'contact_address',
        'integration_email',
        'integration_dvs',
        'integration_gis',
        'is_active',
        'created_by',
        'updated_by',
        'is_email_only',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_email_only' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
