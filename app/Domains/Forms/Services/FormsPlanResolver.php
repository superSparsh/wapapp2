<?php

declare(strict_types=1);

namespace App\Domains\Forms\Services;

use App\Models\Plan;

class FormsPlanResolver
{
    /**
     * @param  array<string, mixed>  $validated
     */
    public function resolvePlanName(array $validated, ?string $rawInput = null): ?string
    {
        $rawPlan = strtolower(trim((string) ($rawInput ?? '')));
        $validatedPlan = trim((string) ($validated['tittu_plan_type'] ?? ''));

        $planSlugMap = [
            'ginger_basic' => 'Ginger Basic',
            'ginger_advanced' => 'Ginger Advance',
            'tittu_basic' => 'Ginger Basic',
            'tittu_advanced' => 'Ginger Advance',
        ];

        if (in_array($validatedPlan, ['Ginger Basic', 'Ginger Advance', 'Ginger Advanced'], true)) {
            return $validatedPlan === 'Ginger Advanced' ? 'Ginger Advance' : $validatedPlan;
        }

        if (isset($planSlugMap[$rawPlan])) {
            return $planSlugMap[$rawPlan];
        }

        $norm = strtolower((string) preg_replace('/[\s\-]+/', '_', $validatedPlan));
        if (isset($planSlugMap[$norm])) {
            return $planSlugMap[$norm];
        }

        if (stripos($validatedPlan, 'Ginger Basic') !== false || stripos($validatedPlan, 'tittu basic') !== false) {
            return 'Ginger Basic';
        }

        if (
            stripos($validatedPlan, 'Ginger Advance') !== false
            || stripos($validatedPlan, 'Ginger Advanced') !== false
            || stripos($validatedPlan, 'tittu advanced') !== false
        ) {
            return 'Ginger Advance';
        }

        return null;
    }

    public function findActivePlan(string $targetPlanName): ?Plan
    {
        $plan = Plan::query()
            ->where('is_active', true)
            ->where(function ($q) use ($targetPlanName): void {
                $q->where('name', $targetPlanName)
                    ->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower(str_replace('advanced', 'advance', $targetPlanName))])
                    ->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower(str_replace('advance', 'advanced', $targetPlanName))])
                    ->orWhereRaw('LOWER(TRIM(name)) = ?', [strtolower($targetPlanName)]);
            })
            ->first();

        if ($plan instanceof Plan) {
            return $plan;
        }

        $escaped = addcslashes($targetPlanName, '%_\\');

        return Plan::query()
            ->where('is_active', true)
            ->where('name', 'like', '%'.$escaped.'%')
            ->first();
    }
}
