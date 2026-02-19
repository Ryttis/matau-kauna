<?php

namespace App\Support;

use App\Models\ClassifierValue;

class ClassifierValueMapper
{
    public static function toArray(ClassifierValue $value): array
    {
        return [
            'id' => $value->id,
            'name' => $value->name,
            'is_active' => $value->is_active,
            'sort_order' => $value->sort_order,
        ];
    }
}
