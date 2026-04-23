<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DelegateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delegated_to' => ['required', 'exists:users,id'],
            'comment'      => ['nullable', 'string', 'max:2000'],
        ];
    }
}
