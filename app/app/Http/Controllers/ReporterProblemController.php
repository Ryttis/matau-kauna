<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Models\ClassifierValue;
use App\Models\FileUploadSettings;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ProblemAutoAssignmentService;
use App\Services\ProblemHttpSupport;
use App\Services\ReporterFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReporterProblemController extends Controller
{
    public function __construct(
        private ProblemHttpSupport $httpSupport,
        private ProblemAutoAssignmentService $autoAssignmentService,
        private ReporterFileUploadService $reporterFileUploadService,
    ) {}

    private function isReporterOnly(?User $user): bool
    {
        return $user
            && $user->hasRole(UserRole::ROLE_REPORTER)
            && ! $user->hasPermission('issues.view');
    }

    public function reporterSarasas(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $this->isReporterOnly($user)) {
            return redirect()->route('dashboard');
        }

        $filters = [
            'search' => $request->get('search'),
            'report_number' => $request->get('report_number'),
            'category' => $request->get('category'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        $problems = $this->httpSupport->reporterProblemsFlatOrderedForList($user, $filters)
            ->map(fn ($problem) => [
                'id' => $problem->id,
                'internal_number' => $problem->internal_number,
                'status' => $problem->status,
                'created_at' => $problem->created_at?->toIso8601String(),
                'category' => $problem->category,
                'address' => $problem->address,
                'description' => $problem->description ?? '',
                'is_published_publicly' => $problem->is_published_publicly,
                'merged_into_problem_id' => $problem->merged_into_problem_id,
            ])
            ->values()
            ->all();

        $categories = ClassifierValue::getActiveTypeNames()->values()->all();

        return Inertia::render('Reporter/Sarasas', [
            'problems' => $problems,
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    public function reporterStatistics(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $this->isReporterOnly($user)) {
            return redirect()->route('dashboard');
        }

        $filters = [
            'period' => $request->get('period'),
            'category' => $request->get('category'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ];

        $problems = $this->httpSupport->reporterProblemsFlatOrderedForList($user, $filters);

        $total = $problems->count();
        $solvedCount = $problems->where('status', ProblemStatus::RESOLVED->value)->count();

        $byCategory = $problems->groupBy('category')
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->all();
        $byCategory = array_map(
            fn ($cat, $cnt) => ['category' => $cat, 'count' => $cnt],
            array_keys($byCategory),
            $byCategory
        );

        $monthlyReports = $this->httpSupport->buildReporterMonthlyReports($problems);

        $categories = ClassifierValue::getActiveTypeNames()->values()->all();

        $nagrinejamosProblemos = $problems->where('status', ProblemStatus::IN_REVIEW->value)
            ->sortByDesc('created_at')
            ->values()
            ->map(fn ($problem) => $this->httpSupport->mapProblemToStatisticsListItem($problem))
            ->all();

        $issprestosProblemos = $problems->where('status', ProblemStatus::RESOLVED->value)
            ->sortByDesc('created_at')
            ->values()
            ->map(fn ($problem) => $this->httpSupport->mapProblemToStatisticsListItem($problem))
            ->all();

        return Inertia::render('Reporter/Statistics', [
            'categories' => $categories,
            'filters' => $filters,
            'total' => $total,
            'solvedCount' => $solvedCount,
            'byCategory' => array_values($byCategory),
            'monthlyReports' => $monthlyReports,
            'nagrinejamosProblemos' => $nagrinejamosProblemos,
            'issprestosProblemos' => $issprestosProblemos,
        ]);
    }

    public function reporterReport(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $this->isReporterOnly($user)) {
            return redirect()->route('dashboard');
        }

        $types = ClassifierValue::notArchived()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with('activeChildren')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $typeValues = $types->map(fn (ClassifierValue $type) => [
            'id' => $type->id,
            'name' => $type->name,
            'natures' => $type->activeChildren->map(fn (ClassifierValue $nature) => ['id' => $nature->id, 'name' => $nature->name])->values()->all(),
        ])->values()->all();

        return Inertia::render('Reporter/Report', [
            'typeValues' => $typeValues,
            'showReporterWizard' => ! $user->reporter_wizard_completed_at,
            'hasAcceptedReportTerms' => $user->reporter_report_terms_accepted_at !== null,
            'fileUpload' => FileUploadSettings::getReporterReportChannelForClient(),
        ]);
    }

    public function reporterWizardDismissed(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->isReporterOnly($user)) {
            return response()->json(['message' => 'Nėra leidimo.'], 403);
        }
        $user->update(['reporter_wizard_completed_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function reporterTermsAccepted(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->isReporterOnly($user)) {
            return response()->json(['message' => 'Nėra leidimo.'], 403);
        }
        $user->update(['reporter_report_terms_accepted_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function reporterReportStore(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user?->hasRole(UserRole::ROLE_REPORTER)) {
            return response()->json(['message' => 'Nėra leidimo.'], 403);
        }

        $channel = FileUploadSettings::resolveUploadChannel($request->header('X-Upload-Channel'));

        $uploadConfig = FileUploadSettings::getChannel($channel);
        $maxDesc = (int) $uploadConfig['description_max_chars'];

        if ($request->input('problem_nature_id') === '' || $request->input('problem_nature_id') === null) {
            $request->merge(['problem_nature_id' => null]);
        }

        $validated = $request->validate(
            [
                'problem_type_id' => [
                    'required',
                    'integer',
                    Rule::exists('classifier_values', 'id')
                        ->whereNull('parent_id')
                        ->whereNull('archived_at')
                        ->where('is_active', true),
                ],
                'problem_nature_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('classifier_values', 'id')->where(function ($query) use ($request) {
                        $query->where('parent_id', (int) $request->input('problem_type_id'))
                            ->whereNull('archived_at')
                            ->where('is_active', true);
                    }),
                ],
                'description' => 'required|string|max:'.$maxDesc,
                'address' => 'required|string|max:500',
                'lat' => 'nullable|numeric',
                'lng' => 'nullable|numeric',
                'files' => 'nullable|array',
                'files.*' => 'file',
            ],
            [
                'problem_type_id.required' => 'Pasirinkite problemos rūšį.',
                'problem_type_id.integer' => 'problem_type_id turi būti sveikasis skaičius.',
                'problem_type_id.exists' => 'problem_type_id turi būti aktyvios rūšies id (classifier_values.parent_id yra NULL). Tikrinkite GET /api/reporter/report-meta arba SQL: SELECT id, name FROM classifier_values WHERE parent_id IS NULL AND archived_at IS NULL;',
                'problem_nature_id.integer' => 'problem_nature_id turi būti sveikasis skaičius.',
                'problem_nature_id.exists' => 'problem_nature_id turi būti tos rūšies pobūdis (classifier_values.parent_id = problem_type_id). Jei nereikia — palikite tuščią / Send empty value.',
                'description.required' => 'Įveskite aprašymą.',
                'description.max' => 'Aprašymas per ilgas.',
                'address.required' => 'Įveskite adresą.',
                'address.max' => 'Adresas per ilgas.',
                'lat.numeric' => 'Netinkama vieta žemėlapyje.',
                'lng.numeric' => 'Netinkama vieta žemėlapyje.',
                'files.array' => 'Netinkami priedai.',
                'files.*.file' => 'Failas per didelis arba nepriimtas.',
            ]
        );

        $typeValue = ClassifierValue::query()->findOrFail($validated['problem_type_id']);

        $user->update(['reporter_report_terms_accepted_at' => now()]);
        $categoryName = $typeValue->name;

        $problem = Problem::create([
            'category' => $categoryName,
            'problem_type_id' => $validated['problem_type_id'],
            'problem_nature_id' => $validated['problem_nature_id'] ?? null,
            'address' => $validated['address'],
            'lat' => $validated['lat'] ?? null,
            'lng' => $validated['lng'] ?? null,
            'description' => $validated['description'],
            'reported_by_user_id' => $user->id,
        ]);

        $problem->refresh();

        $this->reporterFileUploadService->validateAndStore(
            $problem,
            $this->httpSupport->normalizedUploadedFiles($request),
            $channel,
            false,
            true
        );

        $this->autoAssignmentService->applyRules($problem);
        $problem->refresh();

        return response()->json(['ok' => true, 'problem_id' => $problem->id]);
    }
}
