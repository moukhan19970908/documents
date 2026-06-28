<?php

namespace App\Services;

use App\Models\Department;
use App\Models\User;

/**
 * Per-diem (суточные) and accommodation (проживание) norms for business trips
 * within the Russian Federation, derived from the employee's position.
 *
 * Position is resolved from department headship:
 *  - gd       — head of the directorate directly under the holding (departments.parent_id = 6)
 *  - director — head of a directorate reporting to the GD (departments.parent_id = 7)
 *  - head     — head of any other department
 *  - employee — heads no department
 */
class TripNormService
{
    /** Daily per-diem (суточные), ₽/day. */
    private const DAILY = [
        'gd'       => 3000,
        'director' => 1500,
        'head'     => 1200,
        'employee' => 1200,
    ];

    /**
     * Nightly accommodation limit, ₽. null = "по фактическим расходам" (entered manually).
     * Tiers: moscow | spb | sochi | other_rf. Abroad is always manual.
     */
    private const ACCOMMODATION = [
        'gd'       => ['moscow' => null,  'spb' => null,  'sochi' => null,  'other_rf' => null],
        'director' => ['moscow' => 12000, 'spb' => 10000, 'sochi' => 10000, 'other_rf' => 8000],
        'head'     => ['moscow' => 12000, 'spb' => 10000, 'sochi' => 10000, 'other_rf' => 8000],
        'employee' => ['moscow' => 8000,  'spb' => 7000,  'sochi' => 7000,  'other_rf' => 5000],
    ];

    public function categoryFor(User $user): string
    {
        // Генеральный директор — head of the directorate whose parent is the holding root.
        $gdDept = Department::where('parent_id', 6)->first();
        if ($gdDept && (int) $gdDept->head_user_id === $user->id) {
            return 'gd';
        }

        // Руководители с линейным подчинением Генеральному директору.
        if (Department::where('parent_id', 7)->where('head_user_id', $user->id)->exists()) {
            return 'director';
        }

        // Руководители отделов и подразделений.
        if (Department::where('head_user_id', $user->id)->exists()) {
            return 'head';
        }

        // Остальные сотрудники.
        return 'employee';
    }

    public function dailyRate(string $category): float
    {
        return (float) (self::DAILY[$category] ?? self::DAILY['employee']);
    }

    /** Nightly accommodation limit, or null when it must be entered manually (GD / abroad). */
    public function accommodationLimit(string $category, string $locationType): ?float
    {
        if ($locationType === 'abroad') {
            return null;
        }

        $limit = self::ACCOMMODATION[$category][$locationType] ?? null;

        return $limit === null ? null : (float) $limit;
    }

    public function categoryLabel(string $category): string
    {
        return match ($category) {
            'gd'       => 'Генеральный директор',
            'director' => 'Руководитель с линейным подчинением ГД',
            'head'     => 'Руководитель отдела/подразделения',
            default    => 'Сотрудник',
        };
    }

    /** Payload consumed by the trip form (Alpine): position rate + per-tier nightly limits. */
    public function payloadFor(User $user): array
    {
        $category = $this->categoryFor($user);

        return [
            'category'  => $category,
            'label'     => $this->categoryLabel($category),
            'dailyRate' => $this->dailyRate($category),
            'nightly'   => [
                'moscow'   => $this->accommodationLimit($category, 'moscow'),
                'spb'      => $this->accommodationLimit($category, 'spb'),
                'sochi'    => $this->accommodationLimit($category, 'sochi'),
                'other_rf' => $this->accommodationLimit($category, 'other_rf'),
                'abroad'   => null,
            ],
        ];
    }
}
