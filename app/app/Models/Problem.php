<?php

namespace App\Models;

use App\Enums\ProblemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Problem extends Model
{
    use HasFactory;

    public const SORTABLE_COLUMNS = [
        'created_at',
        'internal_number',
        'status',
        'category',
        'address',
        'description',
        'updated_at',
    ];

    protected $fillable = [
        'internal_number',
        'status',
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
                    ->orderByRaw('CAST(SUBSTRING(internal_number FROM 10) AS INTEGER) DESC')
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
                $q->where('address', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('internal_number', 'like', "%{$search}%");
            });
        }

        if (isset($filters['report_number']) && $filters['report_number']) {
            $query->where('internal_number', 'like', "%{$filters['report_number']}%");
        }

        static::applyDashboardFacetFilters($query, $filters);

        return $query;
    }

    private static function applyDashboardFacetFilters(Builder $query, array $filters): void
    {
        if (isset($filters['status']) && $filters['status']) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['category']) && $filters['category']) {
            $query->where('category', $filters['category']);
        }
        if (isset($filters['date_from']) && $filters['date_from']) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to']) && $filters['date_to']) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    public static function mergeGroupRootIdsFromMatchingMergedChildren(array $filters): array
    {
        $reportNumber = isset($filters['report_number']) ? trim((string) $filters['report_number']) : '';
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        if ($reportNumber === '' && $search === '') {
            return [];
        }

        $query = static::query()
            ->whereNotNull('merged_into_problem_id')
            ->notArchived();

        if ($reportNumber !== '') {
            $query->where('internal_number', 'like', '%'.$reportNumber.'%');
        }
        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('address', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('internal_number', 'like', "%{$search}%");
            });
        }

        static::applyDashboardFacetFilters($query, $filters);

        return $query->pluck('merged_into_problem_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter()
            ->values()
            ->all();
    }

    public static function descriptionPreview(?string $description, int $limit = 200): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $description)));

        return Str::limit($text, $limit, '…');
    }

    public function scopeSorted($query, $sortBy = 'created_at', $sortDirection = 'desc')
    {
        $dir = strtolower((string) $sortDirection) === 'asc' ? 'asc' : 'desc';
        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'created_at';

        return $query->orderBy($column, $dir);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeForReporter($query, int $userId)
    {
        return $query
            ->whereIn('status', ProblemStatus::staffMapStatusValues())
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

    public static function expandIdsToMergeGroups(array $ids): array
    {
        $unique = collect($ids)->map(fn ($id) => (int) $id)->unique()->filter()->values();
        if ($unique->isEmpty()) {
            return [];
        }
        $out = collect();
        foreach ($unique as $id) {
            $problem = static::query()->find($id);
            if ($problem !== null) {
                $out->push(...$problem->getMergeGroupIds());
            }
        }

        return $out->unique()->values()->map(fn ($id) => (int) $id)->all();
    }
}
