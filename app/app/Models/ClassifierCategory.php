<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassifierCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(ClassifierValue::class, 'classifier_category_id')->orderBy('sort_order')->orderBy('name');
    }

    public function activeValues(): HasMany
    {
        return $this->hasMany(ClassifierValue::class, 'classifier_category_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
