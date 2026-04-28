<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProblemGisTransferAttachment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const DIRECTION_OUTBOUND = 'outbound';

    public const DIRECTION_INBOUND = 'inbound';

    protected $fillable = [
        'problem_gis_transfer_id',
        'problem_attachment_id',
        'external_attachment_id',
        'external_global_id',
        'status',
        'direction',
        'last_error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(ProblemGisTransfer::class, 'problem_gis_transfer_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(ProblemAttachment::class, 'problem_attachment_id');
    }
}
