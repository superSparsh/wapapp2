<?php

declare(strict_types=1);

namespace App\Domains\Drip\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class DripNodeValidator
{
    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, list<string>>
     */
    public static function validateNodes(array $nodes): array
    {
        $errors = [];

        foreach ($nodes as $index => $node) {
            $type = (string) ($node['type'] ?? '');
            $data = is_array($node['data'] ?? null) ? $node['data'] : [];
            $label = (string) ($data['label'] ?? $type ?: 'Step '.($index + 1));

            $validator = Validator::make(
                [
                    'id' => $node['id'] ?? null,
                    'type' => $type,
                    'data' => $data,
                ],
                self::rulesFor($type, $data),
                self::messages($label),
            );

            if ($validator->fails()) {
                $errors['nodes.'.$index] = $validator->errors()->all();
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function rulesFor(string $type, array $data = []): array
    {
        $base = [
            'id' => ['required', 'string', 'max:64'],
            'type' => ['required', 'string', Rule::in(array_keys(config('drip-nodes.types', [])))],
            'data.label' => ['required', 'string', 'max:191'],
        ];

        $specific = match ($type) {
            'welcomeMessage' => [
                'data.message' => ['required', 'string', 'max:4096'],
            ],
            'templateMessage' => [
                'data.template_name' => ['required', 'string', 'max:191'],
            ],
            'interactiveMessage' => [
                'data.message' => ['required', 'string', 'max:4096'],
                'data.options' => ['required', 'array', 'min:1'],
            ],
            'mediaMessage' => [
                'data.media_url' => ['required', 'url', 'max:2048'],
                'data.media_type' => ['required', Rule::in(['image', 'video', 'document', 'audio'])],
            ],
            'condition', 'enhancedCondition' => self::conditionRules($data),
            'contactOperation' => self::contactOperationRules($data),
            'waitForResponse' => [
                'data.variable_name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
                'data.timeout' => ['required', 'integer', 'min:1', 'max:86400'],
            ],
            'delay', 'typingIndicator' => [
                'data.delay_seconds' => ['required', 'integer', 'min:1', 'max:2592000'],
            ],
            'httpRequest' => [
                'data.url' => ['required', 'url', 'max:2048'],
                'data.method' => ['required', Rule::in(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'])],
            ],
            'functionCall' => [
                'data.function_name' => ['required', 'string', 'max:128'],
            ],
            'jumpToStep' => [
                'data.target_node' => ['required', 'string', 'max:64'],
            ],
            default => [],
        };

        return array_merge($base, $specific);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function conditionRules(array $data): array
    {
        $conditionType = (string) ($data['condition_type'] ?? '');
        $hasVariable = ! empty($data['condition_variable']);

        if ($conditionType === 'custom_variable' || ($conditionType === '' && $hasVariable)) {
            return [
                'data.condition_variable' => ['required', 'string', 'max:128'],
                'data.condition_operator' => ['required', 'string', 'max:32'],
            ];
        }

        return [
            'data.condition_type' => [
                'sometimes',
                'string',
                Rule::in([
                    'whatsapp_read',
                    'whatsapp_delivered',
                    'whatsapp_unread',
                    'whatsapp_failed',
                    'whatsapp_reply',
                    'custom_variable',
                    'open',
                    'click',
                ]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function contactOperationRules(array $data): array
    {
        $operation = (string) ($data['operation_type'] ?? 'tag');

        $rules = [
            'data.operation_type' => ['required', 'string', Rule::in(['tag', 'copy', 'move', 'delete', 'update'])],
        ];

        if ($operation === 'tag') {
            $rules['data.tags'] = ['required', function ($attribute, $value, $fail) {
                if (is_array($value) && count($value) === 0) {
                    $fail('At least one tag is required.');
                }
                if (! is_array($value) && trim((string) $value) === '') {
                    $fail('At least one tag is required.');
                }
            }];
        } elseif ($operation === 'copy' || $operation === 'move') {
            $rules['data.mail_list_id'] = [
                Rule::requiredIf(fn () => empty($data['target_list_id'])),
            ];
        } elseif ($operation === 'update') {
            $rules['data.field_name'] = ['required', 'string', 'max:128'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private static function messages(string $label): array
    {
        return [
            'data.label.required' => $label.': step label is required.',
            'data.message.required' => $label.': message is required.',
            'data.template_name.required' => $label.': template is required.',
            'data.options.required' => $label.': add at least one option.',
            'data.options.min' => $label.': add at least one option.',
            'data.media_url.required' => $label.': media URL is required.',
            'data.media_url.url' => $label.': enter a valid media URL.',
            'data.condition_variable.required' => $label.': condition variable is required.',
            'data.operation_type.required' => $label.': operation type is required.',
            'data.tags.required' => $label.': tags are required for tag operation.',
            'data.mail_list_id.required' => $label.': target audience list is required.',
            'data.field_name.required' => $label.': field name is required.',
            'data.variable_name.required' => $label.': variable name is required.',
            'data.variable_name.regex' => $label.': variable name may only contain letters, numbers, and underscores.',
            'data.delay_seconds.required' => $label.': delay duration is required.',
            'data.delay_seconds.min' => $label.': delay must be at least 1 second.',
            'data.url.required' => $label.': URL is required.',
            'data.url.url' => $label.': enter a valid URL.',
            'data.function_name.required' => $label.': function name is required.',
            'data.target_node.required' => $label.': target step is required.',
        ];
    }
}
