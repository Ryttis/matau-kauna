<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ContentVersionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.question' => ['required_with:items.*', 'string', 'max:1000'],
            'items.*.answer' => ['required_with:items.*', 'string', 'max:10000'],
        ];
    }
}
