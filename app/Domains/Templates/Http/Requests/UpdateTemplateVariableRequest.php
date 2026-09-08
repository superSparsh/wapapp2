<?php

declare(strict_types=1);

namespace App\Domains\Templates\Http\Requests;

use App\Domains\Templates\Enums\VariableDataType;
use App\Models\Variable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateTemplateVariableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('variable') instanceof Variable;
    }

    public function rules(): array
    {
        /** @var Variable $variable */
        $variable = $this->route('variable');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_\s-]+$/',
                Rule::unique('variables', 'name')
                    ->ignore($variable->id)
                    ->where(function ($query) use ($variable) {
                        if ($variable->whatsapp_line_id !== null) {
                            $query->where('whatsapp_line_id', $variable->whatsapp_line_id);
                        } else {
                            $query->whereNull('whatsapp_line_id');
                        }

                        if ($variable->team_member_id !== null) {
                            $query->where('team_member_id', $variable->team_member_id);
                        } else {
                            $query->whereNull('team_member_id');
                        }
                    }),
            ],
            'data_type' => ['required', Rule::enum(VariableDataType::class)],
            'value' => ['nullable', 'string', 'max:2000'],
            'file' => $this->fileRules(),
        ];
    }

    /** @return array<int, mixed> */
    private function fileRules(): array
    {
        $dataType = VariableDataType::tryFrom((string) $this->input('data_type'));

        if ($dataType === null || ! $dataType->isMedia()) {
            return ['nullable'];
        }

        return match ($dataType) {
            VariableDataType::Image => ['nullable', File::image()->max(5120)],
            VariableDataType::Video => ['nullable', File::types(['mp4', 'mov'])->max(20480)],
            VariableDataType::Pdf => ['nullable', File::types(['pdf'])->max(10240)],
            default => ['nullable'],
        };
    }
}
