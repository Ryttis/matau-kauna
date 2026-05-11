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
            'items' => ['nullable', 'array'],
            'items.*.question' => ['required_with:items.*', 'string', 'max:1000'],
            'items.*.answer' => ['required_with:items.*', 'string', 'max:10000'],
        ];
    }
}
