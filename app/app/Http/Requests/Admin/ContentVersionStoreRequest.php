<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ContentVersionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:terms,privacy,faq,cookies'],
            'title' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
        ];
    }
}
