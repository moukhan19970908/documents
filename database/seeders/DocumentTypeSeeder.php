<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentType;
use App\Models\DocumentField;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name'   => 'Договор с клиентом',
                'slug'   => 'client-contract',
                'fields' => [
                    ['label' => 'Контрагент',      'field_key' => 'contractor',  'field_type' => 'text',   'is_required' => true,  'sort_order' => 1],
                    ['label' => 'Сумма договора',  'field_key' => 'amount',      'field_type' => 'number', 'is_required' => true,  'sort_order' => 2],
                    ['label' => 'Дата подписания', 'field_key' => 'sign_date',   'field_type' => 'date',   'is_required' => false, 'sort_order' => 3],
                    ['label' => 'Предмет договора','field_key' => 'subject',     'field_type' => 'textarea','is_required' => true, 'sort_order' => 4],
                ],
            ],
            [
                'name'   => 'Служебная записка',
                'slug'   => 'memo',
                'fields' => [
                    ['label' => 'Кому',    'field_key' => 'to',      'field_type' => 'text', 'is_required' => true,  'sort_order' => 1],
                    ['label' => 'Тема',    'field_key' => 'topic',   'field_type' => 'text', 'is_required' => true,  'sort_order' => 2],
                    ['label' => 'Срочно',  'field_key' => 'urgent',  'field_type' => 'select','is_required' => false, 'sort_order' => 3,
                     'options' => ['Да', 'Нет']],
                ],
            ],
            [
                'name'   => 'Заявка на отпуск',
                'slug'   => 'vacation-request',
                'fields' => [
                    ['label' => 'Дата начала', 'field_key' => 'date_from', 'field_type' => 'date',   'is_required' => true,  'sort_order' => 1],
                    ['label' => 'Дата окончания','field_key' => 'date_to', 'field_type' => 'date',   'is_required' => true,  'sort_order' => 2],
                    ['label' => 'Тип отпуска', 'field_key' => 'type',     'field_type' => 'select', 'is_required' => true,  'sort_order' => 3,
                     'options' => ['Ежегодный', 'Без сохранения зарплаты', 'Учебный']],
                ],
            ],
        ];

        foreach ($types as $typeData) {
            $fields = $typeData['fields'];
            unset($typeData['fields']);

            $type = DocumentType::firstOrCreate(['slug' => $typeData['slug']], $typeData);
            foreach ($fields as $f) {
                DocumentField::firstOrCreate(
                    ['document_type_id' => $type->id, 'field_key' => $f['field_key']],
                    array_merge($f, ['document_type_id' => $type->id])
                );
            }
        }
    }
}
