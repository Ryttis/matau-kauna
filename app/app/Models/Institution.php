<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'archived_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
        'is_active' => 'boolean',
        'is_email_only' => 'boolean',
    ];

    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(InstitutionTeam::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
