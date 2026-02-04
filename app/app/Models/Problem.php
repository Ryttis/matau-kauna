<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Problem extends Model
{
    use HasFactory;

    protected $fillable = [
        'internal_number',
        'status',
        'title',
        'category',
        'address',
        'lat',
        'lng',
        'description',
        'status_updated_at',
        'institution_id',
        'assigned_employee_id',
    ];

    protected $casts = [
        'status_updated_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($problem) {
            if (empty($problem->status)) {
                $problem->status = 'neuzregistruota';
            }

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
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('internal_number', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status']) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['category']) && $filters['category']) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['report_number']) && $filters['report_number']) {
            $query->where('internal_number', 'like', "%{$filters['report_number']}%");
        }

        if (isset($filters['date_from']) && $filters['date_from']) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to']) && $filters['date_to']) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    public function scopeSorted($query, $sortBy = 'created_at', $sortDirection = 'desc')
    {
        return $query->orderBy($sortBy, $sortDirection);
    }

    public function reviewHistory(): HasMany
    {
        return $this->hasMany(ProblemReviewHistory::class, 'problem_id')->orderBy('created_at', 'desc');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }
}
