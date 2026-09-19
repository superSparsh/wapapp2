<?php

declare(strict_types=1);

namespace App\Domains\Operations\Services\MetaPricing;

class MetaPricingSyncMessageFormatter
{
    /**
     * @param  array{updated?: array, skipped?: array, errors?: array}  $import
     * @return array{title: string, message: string, detail: string, level: string}
     */
    public static function format(array $import, int $plansSynced = 0): array
    {
        $updated = count($import['updated'] ?? []);
        $skippedList = $import['skipped'] ?? [];
        $skippedCount = count($skippedList);
        $errors = count($import['errors'] ?? []);

        $alreadyCurrent = 0;
        $notFound = 0;
        foreach ($skippedList as $row) {
            $reason = (string) ($row['reason'] ?? '');
            if ($reason === 'No pricing changes detected') {
                $alreadyCurrent++;
            } elseif (str_contains($reason, 'not found')) {
                $notFound++;
            }
        }

        if ($errors > 0) {
            return [
                'title' => 'Meta sync completed with errors',
                'message' => "{$updated} countries updated, {$errors} errors.",
                'detail' => 'See Pricing change logs for details.',
                'level' => 'warning',
            ];
        }

        if ($updated === 0 && $alreadyCurrent > 0 && $alreadyCurrent === $skippedCount) {
            return [
                'title' => 'Meta pricing already up to date',
                'message' => "All {$alreadyCurrent} countries already match Meta's current USD rate card.",
                'detail' => 'No price changes were needed.',
                'level' => 'info',
            ];
        }

        if ($updated > 0) {
            $detail = 'See Pricing change logs for details.';
            if ($alreadyCurrent > 0) {
                $detail = "{$alreadyCurrent} countries unchanged (already current). ".$detail;
            }
            if ($notFound > 0) {
                $detail .= " {$notFound} CSV row(s) had no matching country.";
            }

            return [
                'title' => 'Meta pricing synced',
                'message' => "{$updated} countries updated from Meta's USD rate card.",
                'detail' => $detail,
                'level' => 'success',
            ];
        }

        return [
            'title' => 'Meta sync finished',
            'message' => "No countries were updated ({$skippedCount} skipped).",
            'detail' => $notFound > 0
                ? "{$notFound} markets not found in your country list."
                : 'No matching price changes.',
            'level' => 'notice',
        ];
    }

    public static function oneLine(array $import, int $plansSynced = 0): string
    {
        $formatted = self::format($import, $plansSynced);

        return $formatted['message'].' '.$formatted['detail'];
    }
}
