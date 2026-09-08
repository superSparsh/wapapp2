<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

final class PublicId
{
    /**
     * Resolve a model by public uuid (never by numeric id from the request).
     *
     * @template T of Model
     * @param  class-string<T>  $modelClass
     * @return T|null
     */
    public static function find(string $modelClass, ?string $uuid): ?Model
    {
        $uuid = trim((string) $uuid);
        if ($uuid === '' || ! preg_match('/^[0-9a-fA-F-]{36}$/', $uuid)) {
            return null;
        }

        return $modelClass::query()->where('uuid', $uuid)->first();
    }

    /**
     * @template T of Model
     * @param  class-string<T>  $modelClass
     * @return T
     */
    public static function findOrFail(string $modelClass, ?string $uuid): Model
    {
        $model = self::find($modelClass, $uuid);
        abort_if($model === null, 404);

        return $model;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return list<\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    public static function uuidExistsRules(string $modelClass, bool $nullable = true): array
    {
        $rules = [
            $nullable ? 'nullable' : 'required',
            'uuid',
            Rule::exists($modelClass, 'uuid'),
        ];

        return $rules;
    }
}
