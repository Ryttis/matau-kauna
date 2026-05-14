<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstitutionDvsConfig extends Model
{
    public const COMPLAINT_API_GAUNAMI_DOKUMENTAI = 'gaunami_dokumentai';
    public const COMPLAINT_API_PILIECIAI_LAISKAI = 'pilieciu_laiskai';

    protected $fillable = [
        'institution_id',
        'enabled',
        'complaint_api',
        'token_url',
        'username',
        'password',
        'client_id',
        'client_secret',
        'gaunami_dokumentai_url',
        'pilieciu_laiskai_url',
        'rusis_url',
        'rusis_kodas',
        'rusys_options',
        'rastines_id',
        'registras_id',
        'darbuotojo_kodas',
        'sudarytojas_kodas',
        'gaunami_registro_kodas',
        'gaunami_registro_id',
        'gaunami_nukreipto_id',
        'gaunami_sudarytojas_id',
        'pilieciu_registro_kodas',
        'pilieciu_registro_id',
        'pilieciu_nukreipto_id',
        'pilieciu_sudarytojas_id',
    ];

    protected $hidden = [
        'password',
        'client_secret',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'password' => 'encrypted',
            'client_secret' => 'encrypted',
            'rusys_options' => 'array',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function problemTransfers(): HasMany
    {
        return $this->hasMany(ProblemDvsTransfer::class);
    }
}
