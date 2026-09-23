<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripTask extends Model
{
    protected $fillable = [
        'trip_request_id', 'vacation_request_id', 'target', 'title', 'assignee_id',
        'status', 'result_comment', 'done_by', 'done_at',
    ];

    protected $casts = ['done_at' => 'datetime'];

    /** Каталог заданий (ТЗ 18.3): условие → задание → кто исполняет (ключ настройки). */
    public const TARGETS = [
        'money'       => ['title' => 'Выписать деньги, зафиксировать', 'who' => 'Отдел кадров',        'setting' => 'hr_user_id'],
        'tour'        => ['title' => 'Подобрать тур: бронь, билеты',    'who' => 'Офис-менеджер',       'setting' => 'office_manager_id'],
        'fuel_card'   => ['title' => 'Выдать топливную карту',          'who' => 'Директор по логистике','setting' => 'logistics_id'],
        'service_car' => ['title' => 'Выделить служебный автомобиль',   'who' => 'Транспортный отдел',  'setting' => 'transport_id'],
    ];

    public const STATUSES = [
        'pending'     => 'Назначено',
        'in_progress' => 'В работе',
        'done'        => 'Выполнено',
    ];

    public function trip(): BelongsTo     { return $this->belongsTo(TripRequest::class, 'trip_request_id'); }
    public function vacation(): BelongsTo { return $this->belongsTo(VacationRequest::class, 'vacation_request_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assignee_id'); }
    public function doneBy(): BelongsTo   { return $this->belongsTo(User::class, 'done_by'); }
    public function files(): HasMany      { return $this->hasMany(TripTaskFile::class)->latest('id'); }
    public function messages(): HasMany   { return $this->hasMany(TripTaskMessage::class)->oldest('id'); }

    /** Родительская заявка: командировка либо отпуск — заполнена ровно одна ссылка. */
    public function request(): TripRequest|VacationRequest|null
    {
        return $this->trip ?? $this->vacation;
    }

    public function isVacationTask(): bool { return $this->vacation_request_id !== null; }

    /** Человек, которому уходит результат задания, — инициатор родительской заявки. */
    public function initiator(): ?User { return $this->request()?->user; }

    /** Номер заявки так, как он показан в её карточке: T-12 / V-25. */
    public function requestNumber(): string
    {
        return $this->isVacationTask() ? 'V-' . $this->vacation_request_id : 'T-' . $this->trip_request_id;
    }

    public function requestLabel(): string { return $this->isVacationTask() ? 'отпуск' : 'командировка'; }

    public function requestUrl(): string
    {
        return $this->isVacationTask()
            ? route('vacations.show', $this->vacation_request_id)
            : route('trips.show', $this->trip_request_id);
    }

    public function whoLabel(): string { return self::TARGETS[$this->target]['who'] ?? ''; }
    public function statusLabel(): string { return self::STATUSES[$this->status] ?? $this->status; }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending'     => 'bg-gray-100 text-gray-600',
            'in_progress' => 'bg-blue-50 text-blue-600',
            'done'        => 'bg-emerald-50 text-emerald-600',
            default       => 'bg-gray-100 text-gray-600',
        };
    }

    public function isDone(): bool { return $this->status === 'done'; }
}
