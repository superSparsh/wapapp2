<?php

declare(strict_types=1);

return [
    // Listing & pagination
    'per_page' => (int) env('FORM_BUILDER_PER_PAGE', 10),
    'public_cache_seconds' => (int) env('FORM_BUILDER_PUBLIC_CACHE_SECONDS', 600),

    // Field constraints
    'max_fields' => (int) env('FORM_BUILDER_MAX_FIELDS', 20),
    'max_dropdown_options' => (int) env('FORM_BUILDER_MAX_DROPDOWN_OPTIONS', 10),
    'field_label_limit' => (int) env('FORM_BUILDER_FIELD_LABEL_LIMIT', 255),
    'field_text_limit' => (int) env('FORM_BUILDER_FIELD_TEXT_LIMIT', 1024),

    // Logo upload limits
    'logo_max_kb' => (int) env('FORM_BUILDER_LOGO_MAX_KB', 2048),
    'logo_mimes' => ['png', 'jpg', 'jpeg'],
    'logo_disk' => env('FORM_BUILDER_LOGO_DISK', 'local'),
    'logo_directory' => 'form-logos',

    // Public form slug length
    'slug_length' => (int) env('FORM_BUILDER_SLUG_LENGTH', 20),

    // Supported field types (order matters for UI rendering)
    'field_types' => [
        'logo' => [
            'label' => 'Logo Field',
            'always_top' => true,
            'required' => true,
        ],
        'header' => [
            'label' => 'Header Field',
            'always_top' => true,
            'required' => true,
        ],
        'paragraph' => [
            'label' => 'Paragraph Field',
            'always_top' => false,
            'required' => false,
        ],
        'input' => [
            'label' => 'Input Field',
            'always_top' => false,
            'required' => false,
        ],
        'dropdown' => [
            'label' => 'Dropdown Field',
            'always_top' => false,
            'required' => false,
        ],
        'checkbox' => [
            'label' => 'Checkbox Field',
            'always_top' => false,
            'required' => false,
        ],
        'phone' => [
            'label' => 'WhatsApp Number',
            'always_top' => true,
            'required' => true,
        ],
    ],
];
