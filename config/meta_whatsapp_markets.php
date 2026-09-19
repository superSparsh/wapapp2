<?php

/**
 * Meta WhatsApp USD rate card: direct markets + regional buckets.
 * Regional rows in CSV apply to all countries mapped to that bucket.
 * Override per country via country_meta_market table or database/data/meta_market_overrides.php
 */
return [
    /** CSV "Market" names that are regional buckets (not a single country). */
    'regional_csv_names' => [
        'North America',
        'Rest of Africa',
        'Rest of Asia Pacific',
        'Rest of Central & Eastern Europe',
        'Rest of Latin America',
        'Rest of Middle East',
        'Rest of Western Europe',
        'Other',
    ],

    /**
     * Countries with their own row on Meta's USD rate card (ISO 3166-1 alpha-2 => CSV market name).
     */
    'direct_country_codes' => [
        'AR' => 'Argentina',
        'BR' => 'Brazil',
        'CL' => 'Chile',
        'CO' => 'Colombia',
        'EG' => 'Egypt',
        'FR' => 'France',
        'DE' => 'Germany',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'MY' => 'Malaysia',
        'MX' => 'Mexico',
        'NL' => 'Netherlands',
        'NG' => 'Nigeria',
        'PK' => 'Pakistan',
        'PE' => 'Peru',
        'RU' => 'Russia',
        'SA' => 'Saudi Arabia',
        'ZA' => 'South Africa',
        'ES' => 'Spain',
        'TR' => 'Turkey',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
    ],

    'north_america' => ['US', 'CA'],

    'rest_of_africa' => [
        'DZ', 'AO', 'BJ', 'BW', 'BF', 'BI', 'CV', 'CM', 'CF', 'TD', 'KM', 'CG', 'CD', 'CI', 'DJ', 'GQ', 'ER', 'SZ', 'ET', 'GA', 'GM', 'GH', 'GN', 'GW', 'KE', 'LS', 'LR', 'LY', 'MG', 'MW', 'ML', 'MR', 'MU', 'YT', 'MA', 'MZ', 'NA', 'NE', 'RE', 'RW', 'ST', 'SN', 'SC', 'SL', 'SO', 'SS', 'SD', 'TZ', 'TG', 'TN', 'UG', 'ZM', 'ZW', 'EH', 'SH',
    ],

    'rest_of_asia_pacific' => [
        'AF', 'AU', 'BD', 'BT', 'BN', 'KH', 'CN', 'TL', 'FJ', 'HK', 'JP', 'KI', 'KP', 'KR', 'LA', 'MO', 'MV', 'MH', 'FM', 'MN', 'MM', 'NR', 'NP', 'NC', 'NZ', 'PW', 'PG', 'PH', 'WS', 'SG', 'SB', 'LK', 'TW', 'TH', 'TO', 'TV', 'VU', 'VN', 'CX', 'CC', 'CK', 'GU', 'NF', 'NU', 'PN', 'TK',
    ],

    'rest_of_central_eastern_europe' => [
        'AL', 'AM', 'AZ', 'BY', 'BA', 'BG', 'HR', 'CZ', 'EE', 'GE', 'GR', 'HU', 'KZ', 'XK', 'KG', 'LV', 'LT', 'MK', 'MD', 'ME', 'PL', 'RO', 'RS', 'SK', 'SI', 'TJ', 'TM', 'UA', 'UZ',
    ],

    'rest_of_latin_america' => [
        'AI', 'AG', 'AW', 'BS', 'BB', 'BZ', 'BM', 'BO', 'BQ', 'VG', 'KY', 'CR', 'CU', 'CW', 'DM', 'DO', 'EC', 'SV', 'FK', 'GF', 'GD', 'GP', 'GT', 'GY', 'HT', 'HN', 'JM', 'MQ', 'MS', 'NI', 'PA', 'PY', 'PR', 'BL', 'KN', 'LC', 'MF', 'PM', 'VC', 'SX', 'SR', 'TT', 'TC', 'UY', 'VE', 'VI',
    ],

    'rest_of_middle_east' => [
        'BH', 'IQ', 'IR', 'JO', 'KW', 'LB', 'OM', 'PS', 'QA', 'SY', 'YE',
    ],

    'rest_of_western_europe' => [
        'AD', 'AT', 'BE', 'CY', 'DK', 'FI', 'FO', 'GI', 'GL', 'GG', 'IS', 'IE', 'IM', 'JE', 'LI', 'LU', 'MT', 'MC', 'NO', 'PT', 'SM', 'SJ', 'SE', 'CH', 'VA',
    ],
];
