<?php

namespace App\Http\Requests;

use App\Enums\ProblemReviewCommentVisibility;
use App\Services\ProblemReviewHistoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProblemReviewHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasPermission('issues.view');
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(ProblemReviewHistoryService::allowedStatusesForReviewEntry()),
            ],
            'comment' => ['nullable', 'string', 'max:10000'],
            'visibility' => [
                'required',
                'string',
                Rule::in(ProblemReviewCommentVisibility::values()),
            ],
        ];
    }
}
