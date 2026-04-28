<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstitutionGisConfig extends Model
{
    protected $fillable = [
        'institution_id',
        'enabled',
        'generate_token_url',
        'technical_username',
        'technical_password',
        'client_type',
        'token_expiration_minutes',
        'feature_server_url',
        'attachment_url',
        'wkid',
        'active_token',
        'active_token_expires_at',
    ];

    protected $hidden = [
        'technical_password',
        'active_token',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'technical_password' => 'encrypted',
            'active_token' => 'encrypted',
            'active_token_expires_at' => 'datetime',
            'token_expiration_minutes' => 'integer',
            'wkid' => 'integer',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(ProblemGisTransfer::class);
    }
}
