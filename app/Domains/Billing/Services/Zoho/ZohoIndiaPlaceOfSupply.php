<?php

declare(strict_types=1);

namespace App\Domains\Billing\Services\Zoho;

/**
 * Maps Indian state names to Zoho Books place-of-supply codes (legacy parity).
 */
final class ZohoIndiaPlaceOfSupply
{
    private const STATE_CODE_MAP = [
        'andaman and nicobar islands' => 'AN',
        'andhra pradesh' => 'AP',
        'arunachal pradesh' => 'AR',
        'assam' => 'AS',
        'bihar' => 'BR',
        'chandigarh' => 'CH',
        'chhattisgarh' => 'CT',
        'dadra and nagar haveli' => 'DN',
        'daman and diu' => 'DD',
        'delhi' => 'DL',
        'goa' => 'GA',
        'gujarat' => 'GJ',
        'haryana' => 'HR',
        'himachal pradesh' => 'HP',
        'jammu and kashmir' => 'JK',
        'jharkhand' => 'JH',
        'karnataka' => 'KA',
        'kerala' => 'KL',
        'ladakh' => 'LA',
        'lakshadweep' => 'LD',
        'madhya pradesh' => 'MP',
        'maharashtra' => 'MH',
        'manipur' => 'MN',
        'meghalaya' => 'ML',
        'mizoram' => 'MZ',
        'nagaland' => 'NL',
        'odisha' => 'OR',
        'puducherry' => 'PY',
        'punjab' => 'PB',
        'rajasthan' => 'RJ',
        'sikkim' => 'SK',
        'tamil nadu' => 'TN',
        'telangana' => 'TG',
        'tripura' => 'TR',
        'uttar pradesh' => 'UP',
        'uttarakhand' => 'UT',
        'west bengal' => 'WB',
    ];

    public static function fromStateName(?string $stateName): string
    {
        if ($stateName === null || $stateName === '') {
            return 'OT';
        }

        $key = strtolower(trim($stateName));
        if (isset(self::STATE_CODE_MAP[$key])) {
            return self::STATE_CODE_MAP[$key];
        }

        $words = preg_split('/\s+/', $key, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }

        return $initials !== '' ? $initials : 'OT';
    }
}
