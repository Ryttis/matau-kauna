<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstitutionGisConfig extends Model
{
    public const FIELD_MAPPING_SOURCES = [
        'status',
        'category',
        'created_at',
        'completion_deadline',
        'address',
        'reporter',
        'internal_number',
        'description',
    ];

    public const DEFAULT_FIELD_MAPPING = [
        'status' => 'busena',
        'category' => 'pranesimo_tipas',
        'created_at' => 'registravimo_data',
        'completion_deadline' => 'vykdymo_terminas',
        'address' => 'adresas',
        'reporter' => 'pranese',
        'internal_number' => '',
        'description' => '',
    ];

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
        'metadata_url',
        'metadata_layer_id',
        'wkid',
        'use_field_mapping',
        'field_mapping',
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
            'use_field_mapping' => 'boolean',
            'field_mapping' => 'array',
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
