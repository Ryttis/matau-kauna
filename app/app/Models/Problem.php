<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Problem extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_number',
        'status',
        'title',
        'category',
        'address',
        'description',
        'status_updated_at',
    ];

    protected $casts = [
        'status_updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($problem) {
            if (empty($problem->internal_number)) {
                $year = date('Y');
                $createdAt = $problem->created_at ?? now();
                $yearFromDate = $createdAt instanceof \DateTime ? $createdAt->format('Y') : date('Y', strtotime($createdAt));

                $lastProblem = static::where('internal_number', 'like', "KP-{$yearFromDate}-%")
                    ->orderByRaw("CAST(SUBSTRING(internal_number FROM 10) AS INTEGER) DESC")
                    ->first();

                if ($lastProblem) {
                    $lastNumber = (int) substr($lastProblem->internal_number, -6);
                    $nextNumber = $lastNumber + 1;
                } else {
                    $nextNumber = 1;
                }

                $problem->internal_number = sprintf('KP-%s-%06d', $yearFromDate, $nextNumber);
            }

            if (empty($problem->status_updated_at)) {
                $problem->status_updated_at = now();
            }
        });

        static::updating(function ($problem) {
            if ($problem->isDirty('status')) {
                $problem->status_updated_at = now();
            }
        });
    }

    public function scopeFiltered($query, $filters)
    {
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status']) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['category']) && $filters['category']) {
            $query->where('category', $filters['category']);
        }

        return $query;
    }

    public function scopeSorted($query, $sortBy = 'created_at', $sortDirection = 'desc')
    {
        return $query->orderBy($sortBy, $sortDirection);
    }
}
