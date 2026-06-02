<?php

namespace App\Models;

use App\Enums\ProblemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Problem extends Model
{
    use HasFactory;

    public const SORTABLE_COLUMNS = [
        'created_at',
        'internal_number',
        'status',
        'status_updated_at',
        'category',
        'address',
        'description',
        'nature',
        'is_published_publicly',
        'updated_at',
    ];

    public const AUTOMATION_RESOLVED_LINE_MAX_LENGTH = 200;

    public const AUTOMATION_RESOLVED_LINE_DESC_APPEND = 160;

    public const AUTOMATION_RESOLVED_SEARCH_PREVIEW = 120;

    private const AUTOMATION_RESOLVED_LINE_MIN_BASE_BEFORE_DESC = 8;

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
        'assigned_institution_team_id',
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
                    ->whereRaw('internal_number ~ ?', ["^KP-{$yearFromDate}-[0-9]{6}$"])
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
                if ($problem->status === ProblemStatus::RESOLVED->value) {
                    $problem->deadline_status = null;
                }
            }
            if ($problem->isDirty('completion_deadline') && $problem->completion_deadline === null) {
                $problem->deadline_status = null;
                $problem->deadline_reminder_sent_at = null;
            }
        });
    }

    public function scopeFiltered($query, $filters, bool $includeReporterSearch = false)
    {
        if (isset($filters['search']) && $filters['search']) {
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                $query->where(function (Builder $q) use ($search, $includeReporterSearch) {
                    static::applyDashboardKeywordSearch($q, $search, $includeReporterSearch);
                });
            }
        }

        if (isset($filters['report_number']) && $filters['report_number']) {
            $query->where('internal_number', 'like', "%{$filters['report_number']}%");
        }

        static::applyDashboardFacetFilters($query, $filters);

        return $query;
    }

    private static function applyDashboardKeywordSearch(Builder $query, string $search, bool $includeReporterSearch = false): void
    {
        $lowerLike = '%'.mb_strtolower($search).'%';
        $query->whereRaw("LOWER(COALESCE(address, '')) LIKE ?", [$lowerLike])
            ->orWhereRaw("LOWER(COALESCE(description, '')) LIKE ?", [$lowerLike])
            ->orWhereRaw("LOWER(COALESCE(internal_number, '')) LIKE ?", [$lowerLike]);

        if ($includeReporterSearch) {
            $query->orWhereHas('reportedByUser', function (Builder $userQuery) use ($search) {
                static::applyReporterKeywordSearch($userQuery, $search);
            });
        }
    }

    private static function applyReporterKeywordSearch(Builder $query, string $search): void
    {
        $lowerLike = '%'.strtolower($search).'%';

        $query->where(function (Builder $userQuery) use ($lowerLike) {
            $userQuery->whereRaw("LOWER(COALESCE(name, '')) LIKE ?", [$lowerLike])
                ->orWhereRaw("LOWER(COALESCE(surname, '')) LIKE ?", [$lowerLike])
                ->orWhereRaw("LOWER(TRIM(COALESCE(name, '') || ' ' || COALESCE(surname, ''))) LIKE ?", [$lowerLike]);
        });
    }

    private static function applyDashboardFacetFilters(Builder $query, array $filters): void
    {
        $statuses = $filters['statuses'] ?? null;
        if (is_array($statuses)) {
            $statuses = array_values(array_filter($statuses, fn ($value) => filled($value)));
        }
        if (is_array($statuses) && $statuses !== []) {
            $query->whereIn('status', $statuses);
        } elseif (isset($filters['status']) && $filters['status']) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['category']) && $filters['category']) {
            $query->where('category', $filters['category']);
        }
        if (isset($filters['problem_nature_id']) && $filters['problem_nature_id']) {
            $query->where('problem_nature_id', (int) $filters['problem_nature_id']);
        }
        if (isset($filters['institution_id']) && $filters['institution_id'] !== null && $filters['institution_id'] !== '') {
            if ((string) $filters['institution_id'] === 'none') {
                $query->whereNull('institution_id');
            } else {
                $query->where('institution_id', (int) $filters['institution_id']);
            }
        }
        if (isset($filters['date_from']) && $filters['date_from']) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to']) && $filters['date_to']) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }

    public static function mergeGroupRootIdsFromMatchingMergedChildren(array $filters, bool $includeReporterSearch = false): array
    {
        $reportNumber = isset($filters['report_number']) ? trim((string) $filters['report_number']) : '';
        $search = isset($filters['search']) ? trim((string) $filters['search']) : '';
        $hasFacetFilters = collect(['status', 'statuses', 'category', 'problem_nature_id', 'institution_id', 'date_from', 'date_to'])
            ->contains(fn ($key) => filled($filters[$key] ?? null));

        if ($reportNumber === '' && $search === '' && ! $hasFacetFilters) {
            return [];
        }

        $query = static::query()
            ->whereNotNull('merged_into_problem_id')
            ->notArchived();

        if ($reportNumber !== '') {
            $query->where('internal_number', 'like', '%'.$reportNumber.'%');
        }
        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search, $includeReporterSearch) {
                static::applyDashboardKeywordSearch($sub, $search, $includeReporterSearch);
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
        $column = in_array($sortBy, self::SORTABLE_COLUMNS, true) ? $sortBy : 'created_at';
        $dir = $column === 'created_at' && $sortBy !== 'created_at'
            ? 'desc'
            : (strtolower((string) $sortDirection) === 'asc' ? 'asc' : 'desc');
        $table = $query->getModel()->getTable();

        if ($column === 'nature') {
            return $query
                ->select("{$table}.*")
                ->leftJoin('classifier_values as sort_natures', "{$table}.problem_nature_id", '=', 'sort_natures.id')
                ->orderByRaw('CASE WHEN sort_natures.name IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('sort_natures.name', $dir)
                ->orderBy("{$table}.id");
        }

        return $query->orderBy("{$table}.{$column}", $dir);
    }

    public function scopeNotArchived($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeResolvedPublicRoot(Builder $query): Builder
    {
        return $query->notArchived()
            ->where('status', ProblemStatus::RESOLVED->value)
            ->where('is_published_publicly', true)
            ->whereNull('merged_into_problem_id');
    }

    public function automationResolvedListingLine(int $maxLength = self::AUTOMATION_RESOLVED_LINE_MAX_LENGTH): string
    {
        $addr = trim((string) ($this->address ?? ''));
        $desc = $this->description !== null ? trim((string) $this->description) : '';
        $num = trim((string) ($this->internal_number ?? ''));
        $base = $num;
        if ($addr !== '') {
            $base = $base !== '' ? $base.' - '.$addr : $addr;
        }
        if (mb_strlen($base) < self::AUTOMATION_RESOLVED_LINE_MIN_BASE_BEFORE_DESC && $desc !== '') {
            $suffix = mb_substr($desc, 0, self::AUTOMATION_RESOLVED_LINE_DESC_APPEND);
            $base = $base !== '' ? $base.' - '.$suffix : mb_substr($desc, 0, $maxLength);
        }

        return mb_strlen($base) > $maxLength ? mb_substr($base, 0, $maxLength) : $base;
    }

    public function scopeForReporter($query, int $userId)
    {
        return $query
            ->whereIn('status', ProblemStatus::reporterOwnedProblemsQueryStatusValues())
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

    public function assignedInstitutionTeam(): BelongsTo
    {
        return $this->belongsTo(InstitutionTeam::class, 'assigned_institution_team_id');
    }

    public function scopeWhereVisibleToEmployee(Builder $query, int $employeeId): void
    {
        $table = $query->getModel()->getTable();
        $query->where(function (Builder $inner) use ($employeeId, $table) {
            $inner->where("{$table}.assigned_employee_id", $employeeId)
                ->orWhereExists(function ($sub) use ($employeeId, $table) {
                    $sub->selectRaw('1')
                        ->from('employee_institution_team')
                        ->whereColumn('employee_institution_team.institution_team_id', "{$table}.assigned_institution_team_id")
                        ->where('employee_institution_team.employee_id', $employeeId);
                });
        });
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

    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'problem_watches')->withTimestamps();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProblemAttachment::class, 'problem_id')->orderBy('id');
    }

    public function gisTransfer(): HasOne
    {
        return $this->hasOne(ProblemGisTransfer::class);
    }

    public function dvsTransfer(): HasOne
    {
        return $this->hasOne(ProblemDvsTransfer::class);
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

    public function mergeGroupRootId(): int
    {
        $current = $this;
        for ($i = 0; $i < 32; $i++) {
            if ($current->merged_into_problem_id === null) {
                return (int) $current->id;
            }
            $parent = static::query()->find($current->merged_into_problem_id);
            if ($parent === null) {
                return (int) $current->id;
            }
            $current = $parent;
        }

        return (int) $current->id;
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

    public static function rootIdsMapFromProblemIds(array $problemIds): array
    {
        $map = [];
        foreach ($problemIds as $pid) {
            $p = static::query()->find((int) $pid);
            if ($p === null) {
                continue;
            }
            $map[$p->merged_into_problem_id ?? $p->id] = true;
        }

        return $map;
    }

    public static function statusesByRootId(array $rootIdsMap): array
    {
        $out = [];
        foreach (array_keys($rootIdsMap) as $mainId) {
            $out[$mainId] = static::query()->where('id', $mainId)->value('status');
        }

        return $out;
    }
}
