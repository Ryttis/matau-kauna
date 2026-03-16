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
        'problem_type_id',
        'problem_nature_id',
        'address',
        'lat',
        'lng',
        'description',
        'status_updated_at',
        'institution_id',
        'assigned_employee_id',
        'completion_deadline',
        'deadline_status',
        'deadline_reminder_sent_at',
        'reported_by_user_id',
        'merged_into_problem_id',
        'is_published_publicly',
        'is_archived',
        'archived_at',
    ];

    protected $casts = [
        'status_updated_at' => 'datetime',
        'completion_deadline' => 'date',
        'deadline_reminder_sent_at' => 'datetime',
        'is_published_publicly' => 'boolean',
        'is_archived' => 'boolean',
        'archived_at' => 'datetime',
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
                if ($problem->status === 'isspresta') {
                    $problem->deadline_status = null;
                }
            }
            if ($problem->isDirty('completion_deadline') && $problem->completion_deadline === null) {
                $problem->deadline_status = null;
                $problem->deadline_reminder_sent_at = null;
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

    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeForReporter($query, int $userId)
    {
        return $query
            ->whereIn('status', ['nauja', 'nagrinejama', 'isspresta'])
            ->notArchived()
            ->whereNull('merged_into_problem_id')
            ->where(function ($q) use ($userId) {
                $q->where('reported_by_user_id', $userId)
                    ->orWhereHas('mergedProblems', fn ($q2) => $q2->where('reported_by_user_id', $userId));
            });
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

    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function problemType(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class, 'problem_type_id');
    }

    public function problemNature(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class, 'problem_nature_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Problem::class, 'merged_into_problem_id');
    }

    public function mergedProblems(): HasMany
    {
        return $this->hasMany(Problem::class, 'merged_into_problem_id');
    }

    public function getMergeGroupIds(): array
    {
        $mainId = $this->merged_into_problem_id ?? $this->id;
        $ids = static::where('id', $mainId)
            ->orWhere('merged_into_problem_id', $mainId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $ids;
    }
}
