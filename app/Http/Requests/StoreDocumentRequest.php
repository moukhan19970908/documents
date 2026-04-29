<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Document::class);
    }

    public function rules(): array
    {
        return [
            'title'            => ['required', 'string', 'max:255'],
            'document_type_id' => ['nullable', 'exists:document_types,id'],
            'data'             => ['nullable', 'array'],
            'deadline_at'      => ['nullable', 'date'],
            'file'             => ['nullable', 'file', 'max:51200'], // 50MB
            'approvers'        => ['nullable', 'array'],
            'approvers.*'      => ['integer', 'exists:users,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // "adhoc" is a UI-only value, not a real type ID
        if ($this->document_type_id === 'adhoc') {
            $this->merge(['document_type_id' => null]);
        }
    }
}
