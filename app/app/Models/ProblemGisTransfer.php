<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProblemGisTransfer extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_FEATURE_CREATED = 'feature_created';

    public const STATUS_SENT = 'sent';

    public const STATUS_ATTACHMENT_FAILED = 'attachment_failed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'problem_id',
        'institution_gis_config_id',
        'external_object_id',
        'external_global_id',
        'send_status',
        'last_error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(InstitutionGisConfig::class, 'institution_gis_config_id');
    }

    public function attachmentTransfers(): HasMany
    {
        return $this->hasMany(ProblemGisTransferAttachment::class);
    }
}
