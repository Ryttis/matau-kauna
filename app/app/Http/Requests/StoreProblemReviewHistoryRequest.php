<?php

namespace App\Http\Requests;

use App\Enums\ProblemReviewCommentVisibility;
use App\Enums\ProblemStatus;
use App\Models\ProblemReviewHistory;
use App\Services\ProblemHttpSupport;
use App\Services\ProblemReviewHistoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProblemReviewHistoryRequest extends FormRequest
{
    public function validationData(): array
    {
        $http = app(ProblemHttpSupport::class);
        $otherFiles = [];
        foreach ($this->allFiles() as $key => $value) {
            if (! $http->isMultipartFilesFieldKey((string) $key)) {
                $otherFiles[$key] = $value;
            }
        }

        $data = array_replace_recursive($this->input(), $otherFiles);
        $data['files'] = $http->uploadedFilesForStorage($this);

        return $data;
    }

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasPermission('issues.view');
    }

    public function rules(): array
    {
        $isUpdate = $this->route('entry') instanceof ProblemReviewHistory;

        $statusRules = $isUpdate
            ? ['nullable', 'string', Rule::in(ProblemReviewHistoryService::allowedStatusesForReviewEntry())]
            : ['required', 'string', Rule::in(ProblemReviewHistoryService::allowedStatusesForReviewEntry())];

        $visibilityRules = $isUpdate
            ? ['nullable', 'string', Rule::in(ProblemReviewCommentVisibility::values())]
            : ['required', 'string', Rule::in(ProblemReviewCommentVisibility::values())];

        $commentRules = ['nullable', 'string', 'max:10000'];
        if ($this->input('status') === ProblemStatus::INACTIVE->value) {
            $commentRules = ['required', 'string', 'max:10000'];
        }

        return [
            'status' => $statusRules,
            'comment' => $commentRules,
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'mimes:jpg,jpeg,png'],
            'visibility' => $visibilityRules,
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Pasirinkite būseną.',
            'status.in' => 'Netinkama būsena.',
            'visibility.required' => 'Pasirinkite matomumą.',
            'visibility.in' => 'Netinkamas matomumas.',
            'comment.required' => 'Pasirinkus „Neaktuali" privaloma įrašyti priežastį.',
            'comment.max' => 'Komentaras per ilgas.',
            'files.array' => 'Netinkami priedai.',
            'files.*.file' => 'Failas per didelis arba nepriimtas.',
            'files.*.mimes' => 'Leidžiamos tik JPEG arba PNG nuotraukos.',
            'files.*.uploaded' => 'Nepavyko įkelti failo. Patikrinkite failo dydį ir bandykite dar kartą.',
        ];
    }
}
