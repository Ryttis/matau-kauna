<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProblemDvsTransfer extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'problem_id',
        'institution_dvs_config_id',
        'complaint_api',
        'external_object_id',
        'external_reg_nr',
        'send_status',
        'last_error',
        'sent_at',
        'last_checked_at',
        'answer_date',
        'answer_notes',
        'last_response_json',
    ];

    /**
     * DVS atsakymo kopija saugoma tik auditui – ji gali būti dešimčių MB dydžio
     * (dokumentų turinys base64), todėl niekada nekeliaujam į JSON/Inertia atsaką.
     */
    protected $hidden = [
        'last_response_json',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'answer_date' => 'datetime',
            'last_response_json' => 'array',
        ];
    }

    public function problem(): BelongsTo
    {
        return $this->belongsTo(Problem::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(InstitutionDvsConfig::class, 'institution_dvs_config_id');
    }
}
