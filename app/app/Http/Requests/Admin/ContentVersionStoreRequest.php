<?php

namespace App\Http\Requests\Admin;

use App\Models\ContentVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContentVersionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(ContentVersion::administrableContentTypes())],
            'title' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
        ];
    }
}
