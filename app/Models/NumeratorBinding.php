<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NumeratorBinding extends Model
{
    protected $fillable = ['numerator_id', 'classifier_type', 'classifier_id'];

    /** Типы классификаторов, к которым можно привязать нумерацию. */
    public const TYPES = [
        'document_type'    => 'Тип документа',
        'document_subtype' => 'Подтип документа',
        'order_kind'       => 'Вид приказа',
    ];

    public function numerator(): BelongsTo
    {
        return $this->belongsTo(Numerator::class);
    }
}
