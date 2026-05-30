<?php

namespace App\Http\Controllers;

use App\Enums\ProblemStatus;
use App\Models\ClassifierValue;
use App\Models\FileUploadSettings;
use App\Models\Problem;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ProblemAutoAssignmentService;
use App\Services\ProblemAutoPublishService;
use App\Services\ProblemHttpSupport;
use App\Services\ReporterFileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReporterProblemController extends Controller
{
    public function __construct(
        private ProblemHttpSupport $httpSupport,
        private ProblemAutoAssignmentService $autoAssignmentService,
        private ProblemAutoPublishService $autoPublishService,
        private ReporterFileUploadService $reporterFileUploadService,
    ) {}

    private function isReporterOnly(?User $user): bool
    {
        return $user
            && $user->hasAssignedRole(UserRole::ROLE_REPORTER)
            && ! $user->hasPermission('issues.view');
    }

    public function reporterSarasas(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        if (! $this->isReporterOnly($user)) {
            return redirect()->route('dashboard');
        }

        $reporterStatuses = $this->requestedReporterStatuses($request);

        $filters = [
            'search' => $request->get('search'),
            'report_number' => $request->get('report_number'),
            'category' => $request->get('category'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'statuses' => ProblemStatus::rawValuesForReporterStatuses($reporterStatuses),
            'only_mine' => $request->boolean('only_mine'),
        ];

        $problems = $this->httpSupport->reporterProblemsFlatOrderedForList($user, $filters, true, $filters['only_mine'])
            ->map(fn ($problem) => [
                'id' => $problem->id,
                'internal_number' => $problem->internal_number,
                'status' => $problem->status,
                'reporter_status' => ProblemStatus::reporterStatusValue((string) $problem->status),
                'reporter_status_label' => ProblemStatus::reporterStatusLabel((string) $problem->status),
                'created_at' => $problem->created_at?->toIso8601String(),
                'category' => $problem->category,
                'nature' => $problem->problemNature?->name,
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
            'filters' => array_merge($filters, ['statuses' => $reporterStatuses]),
        ]);
    }

    private function requestedReporterStatuses(Request $request): array
    {
        $statuses = $request->input('statuses', []);
        if (! is_array($statuses)) {
            $statuses = [$statuses];
        }
        $valid = [
            ProblemStatus::NEW->value,
            ProblemStatus::IN_REVIEW->value,
            ProblemStatus::RESOLVED->value,
        ];

        return array_values(array_filter(
            array_map('strval', $statuses),
            fn (string $status) => in_array($status, $valid, true),
        ));
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
        $solvedCount = $problems
            ->filter(fn ($problem) => ProblemStatus::reporterStatusValue((string) $problem->status) === ProblemStatus::RESOLVED->value)
            ->count();

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

        $nagrinejamosProblemos = $problems
            ->filter(fn ($problem) => ProblemStatus::reporterStatusValue((string) $problem->status) === ProblemStatus::IN_REVIEW->value)
            ->sortByDesc('created_at')
            ->values()
            ->map(fn ($problem) => $this->httpSupport->mapProblemToStatisticsListItem($problem))
            ->all();

        $issprestosProblemos = $problems
            ->filter(fn ($problem) => ProblemStatus::reporterStatusValue((string) $problem->status) === ProblemStatus::RESOLVED->value)
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

        if (! $user?->hasAssignedRole(UserRole::ROLE_REPORTER)) {
            return response()->json(['message' => 'Nėra leidimo.'], 403);
        }

        $channel = FileUploadSettings::resolveUploadChannel($request->header('X-Upload-Channel'));
        $normalizedFiles = $this->httpSupport->uploadedFilesForStorage($request);

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
            ],
            [
                'problem_type_id.required' => 'Pasirinkite problemos rūšį.',
                'problem_type_id.integer' => 'Netinkama problemos rūšis.',
                'problem_type_id.exists' => 'Netinkama problemos rūšis.',
                'problem_nature_id.integer' => 'Netinkamas problemos pobūdis.',
                'problem_nature_id.exists' => 'Netinkamas problemos pobūdis arba jis neatitinka pasirinktos rūšies. Jei pobūdis nereikalingas, palikite lauką tuščią.',
                'description.required' => 'Įveskite aprašymą.',
                'description.max' => 'Aprašymas per ilgas.',
                'address.required' => 'Įveskite adresą.',
                'address.max' => 'Adresas per ilgas.',
                'lat.numeric' => 'Netinkama vieta žemėlapyje.',
                'lng.numeric' => 'Netinkama vieta žemėlapyje.',
            ]
        );

        Validator::make(
            ['files' => $normalizedFiles],
            [
                'files' => 'nullable|array',
                'files.*' => 'file',
            ],
            [
                'files.array' => 'Netinkami priedai.',
                'files.*.file' => 'Failas per didelis arba nepriimtas.',
                'files.*.uploaded' => 'Nepavyko įkelti failo. Patikrinkite failo dydį ir bandykite dar kartą.',
            ]
        )->validate();

        $this->reporterFileUploadService->validateForChannel($normalizedFiles, $channel);

        $typeValue = ClassifierValue::query()->findOrFail($validated['problem_type_id']);
        [$lat, $lng] = $this->resolveCoordinates(
            (string) ($validated['address'] ?? ''),
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lng']) ? (float) $validated['lng'] : null
        );

        $user->update(['reporter_report_terms_accepted_at' => now()]);
        $categoryName = $typeValue->name;

        $problem = Problem::create([
            'category' => $categoryName,
            'problem_type_id' => $validated['problem_type_id'],
            'problem_nature_id' => $validated['problem_nature_id'] ?? null,
            'address' => $validated['address'],
            'lat' => $lat,
            'lng' => $lng,
            'description' => $validated['description'],
            'reported_by_user_id' => $user->id,
        ]);

        $problem->refresh();

        $createdAttachments = $this->reporterFileUploadService->validateAndStore(
            $problem,
            $normalizedFiles,
            $channel,
            false,
            false
        );

        $this->autoAssignmentService->applyRules($problem);
        $this->autoPublishService->applyRules($problem);
        $problem->refresh();

        return response()->json([
            'ok' => true,
            'problem_id' => $problem->id,
            'internal_number' => $problem->internal_number,
            'attachments' => collect($createdAttachments)
                ->map(fn ($attachment) => $attachment->toPublicPayload())
                ->values()
                ->all(),
        ]);
    }

    private function resolveCoordinates(string $address, ?float $lat, ?float $lng): array
    {
        if ($lat !== null && $lng !== null) {
            return [$lat, $lng];
        }

        $query = trim($address);
        if ($query === '') {
            return [$lat, $lng];
        }

        try {
            $response = Http::timeout(6)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Accept-Language' => 'lt',
                    'User-Agent' => 'MatauKaune/1.0',
                ])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query.', Kaunas',
                    'format' => 'json',
                    'limit' => 1,
                ]);

            $rows = $response->json();
            $first = is_array($rows) ? ($rows[0] ?? null) : null;
            $resolvedLat = isset($first['lat']) ? (float) $first['lat'] : null;
            $resolvedLng = isset($first['lon']) ? (float) $first['lon'] : null;

            return [
                $lat ?? $resolvedLat,
                $lng ?? $resolvedLng,
            ];
        } catch (\Throwable) {
            return [$lat, $lng];
        }
    }
}
