<?php

namespace App\Http\Requests;

use App\Models\DocumentSubtype;
use App\Models\DocumentType;
use App\Models\Workflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Document::class);
    }

    public function rules(): array
    {
        return [
            'title'               => ['required', 'string', 'max:255'],
            'workflow_id'         => ['nullable', 'exists:workflows,id'],
            'document_type_id'    => ['nullable', 'exists:document_types,id'],
            'document_subtype_id' => ['nullable', 'exists:document_subtypes,id'],
            'manual_number'       => ['nullable', 'string', 'max:64'],
            'custom_fields'       => ['nullable', 'array'],
            'custom_fields.*'     => ['nullable', 'string', 'max:2048'],
            'data'                => ['nullable', 'array'],
            'parameters'          => ['nullable', 'array'],
            'parameters.*'        => ['nullable', 'string', 'max:255'],
            'deadline_at'         => ['nullable', 'date'],
            'file'                => ['nullable', 'file', 'max:51200'], // 50MB
            'approvers'           => ['nullable', 'array'],
            'approvers.*'         => ['integer', 'exists:users,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // "adhoc" is a UI-only value, not a real type ID
        if ($this->document_type_id === 'adhoc') {
            $this->merge(['document_type_id' => null]);
        }
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $type = $this->document_type_id ? DocumentType::with('fields')->find($this->document_type_id) : null;

                if (!$type) {
                    return;
                }

                $subtype = $this->document_subtype_id
                    ? DocumentSubtype::with('fields')->find($this->document_subtype_id)
                    : null;

                if ($subtype && $subtype->document_type_id !== $type->id) {
                    $validator->errors()->add('document_subtype_id', 'Подтип не принадлежит выбранному типу.');
                    return;
                }

                if (!$subtype && $type->subtypes()->where('is_active', true)->exists()) {
                    $validator->errors()->add('document_subtype_id', 'Выберите подтип.');
                }

                $data = $this->input('data', []);

                foreach ($type->fields->merge($subtype?->fields ?? collect()) as $field) {
                    if ($field->is_required && blank($data[$field->field_key] ?? null)) {
                        $validator->errors()->add("data.{$field->field_key}", "Поле «{$field->label}» обязательно.");
                    }
                }

                // Launch parameters of the chosen scenario — their answers shape the route.
                $workflow = $this->workflow_id ? Workflow::with('parameters')->find($this->workflow_id) : null;
                $answers = $this->input('parameters', []);

                foreach ($workflow?->parameters ?? [] as $parameter) {
                    if ($parameter->is_required && blank($answers[$parameter->key] ?? null)) {
                        $validator->errors()->add("parameters.{$parameter->key}", "Параметр «{$parameter->label}» обязателен.");
                    }
                }
            },
        ];
    }
}
