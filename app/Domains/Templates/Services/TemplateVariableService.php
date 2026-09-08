<?php

declare(strict_types=1);

namespace App\Domains\Templates\Services;

use App\Domains\Templates\Enums\VariableDataType;
use App\Domains\Templates\Enums\VariableType;
use App\Domains\Templates\Support\VariableActorContext;
use App\Models\Variable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateVariableService
{
    public function __construct(
        private readonly VariableActorContext $actorContext,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     data_type: VariableDataType|string,
     *     type?: VariableType|string,
     *     value?: string|null,
     * }  $data
     */
    public function create(array $data, ?UploadedFile $file = null): Variable
    {
        return DB::transaction(function () use ($data, $file): Variable {
            $dataType = $data['data_type'] instanceof VariableDataType
                ? $data['data_type']
                : VariableDataType::from((string) $data['data_type']);

            $type = isset($data['type'])
                ? ($data['type'] instanceof VariableType ? $data['type'] : VariableType::from((string) $data['type']))
                : VariableType::Dynamic;

            return Variable::query()->create([
                'type' => $type,
                'name' => $this->normalizeName((string) $data['name']),
                'data_type' => $dataType,
                'value' => $this->resolveValue($dataType, $data['value'] ?? null, $file),
                'whatsapp_line_id' => $this->actorContext->whatsappLineId(),
                'team_member_id' => $this->actorContext->teamMemberId(),
                'team_member_name' => $this->actorContext->teamMemberName(),
            ]);
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     data_type: VariableDataType|string,
     *     value?: string|null,
     * }  $data
     */
    public function update(Variable $variable, array $data, ?UploadedFile $file = null): Variable
    {
        return DB::transaction(function () use ($variable, $data, $file): Variable {
            $dataType = $data['data_type'] instanceof VariableDataType
                ? $data['data_type']
                : VariableDataType::from((string) $data['data_type']);

            $value = $this->resolveValue(
                $dataType,
                $data['value'] ?? $variable->value,
                $file,
                $variable->value,
            );

            $variable->fill([
                'name' => $this->normalizeName((string) $data['name']),
                'data_type' => $dataType,
                'value' => $value,
            ])->save();

            return $variable->refresh();
        });
    }

    public function delete(Variable $variable): void
    {
        DB::transaction(function () use ($variable): void {
            if ($variable->data_type->isMedia() && filled($variable->value)) {
                Storage::disk($this->mediaDisk())->delete((string) $variable->value);
            }

            $variable->delete();
        });
    }

    private function normalizeName(string $name): string
    {
        return Str::of($name)->trim()->lower()->replace(' ', '_')->toString();
    }

    private function resolveValue(
        VariableDataType $dataType,
        ?string $value,
        ?UploadedFile $file,
        ?string $previousPath = null,
    ): ?string {
        if ($dataType->isMedia()) {
            if ($file instanceof UploadedFile) {
                if (filled($previousPath)) {
                    Storage::disk($this->mediaDisk())->delete($previousPath);
                }

                return $file->store($this->mediaDirectory(), $this->mediaDisk());
            }

            return $previousPath;
        }

        return filled($value) ? trim($value) : null;
    }

    private function mediaDisk(): string
    {
        return (string) config('templates.media_disk', 'local');
    }

    private function mediaDirectory(): string
    {
        return (string) config('templates.media_directory', 'template-variables');
    }
}
