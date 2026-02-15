<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassifierValue extends Model
{
    protected $fillable = [
        'classifier_category_id',
        'parent_id',
        'name',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ClassifierCategory::class, 'classifier_category_id');
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
}
