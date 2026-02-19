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
        ];
    }
}
