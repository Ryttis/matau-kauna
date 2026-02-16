<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassifierValue extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'is_active',
        'sort_order',
        'archived_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'archived_at' => 'datetime',
    ];

    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ClassifierValue::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function activeChildren(): HasMany
    {
        return $this->hasMany(ClassifierValue::class, 'parent_id')
            ->whereNull('archived_at')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function problemsAsType(): HasMany
    {
        return $this->hasMany(Problem::class, 'problem_type_id');
    }

    public function problemsAsNature(): HasMany
    {
        return $this->hasMany(Problem::class, 'problem_nature_id');
    }

    public static function getActiveTypeNames(): \Illuminate\Support\Collection
    {
        return static::notArchived()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name');
    }
}
