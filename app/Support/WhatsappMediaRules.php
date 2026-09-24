<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class WhatsappMediaRules
{
    public const TYPES = ['image', 'video', 'audio', 'document'];

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return self::TYPES;
    }

    /**
     * Absolute ceiling across all media types (KB). Useful for early reject.
     */
    public static function absoluteMaxKb(): int
    {
        $max = 0;

        foreach (self::TYPES as $type) {
            $max = max($max, self::maxKb($type));
        }

        return $max > 0 ? $max : (int) config('whatsapp.media.max_size_kb', 14336);
    }

    public static function maxKb(string $type): int
    {
        return (int) config("whatsapp.media.types.{$type}.max_kb", config('whatsapp.media.max_size_kb', 14336));
    }

    public static function maxMbLabel(string $type): string
    {
        $kb = self::maxKb($type);

        return rtrim(rtrim(number_format($kb / 1024, 1, '.', ''), '0'), '.').' MB';
    }

    /**
     * @return list<string>
     */
    public static function extensions(string $type): array
    {
        $ext = config("whatsapp.media.types.{$type}.extensions", []);

        return is_array($ext) ? array_values(array_map('strval', $ext)) : [];
    }

    public static function accept(string $type): string
    {
        return (string) config("whatsapp.media.types.{$type}.accept", '');
    }

    public static function hint(string $type): string
    {
        $configured = config("whatsapp.media.types.{$type}.hint");
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $exts = strtoupper(implode(', ', self::extensions($type)));

        return "{$exts} (max ".self::maxMbLabel($type).')';
    }

    /**
     * Laravel validation rule fragments for a given media type (without required/nullable).
     *
     * @return list<string>
     */
    public static function constraintRules(string $type): array
    {
        if (! in_array($type, self::TYPES, true)) {
            return ['mimes:'.implode(',', self::allExtensions()), 'max:'.self::absoluteMaxKb()];
        }

        $extensions = self::extensions($type);
        if ($extensions === []) {
            return ['max:'.self::maxKb($type)];
        }

        return [
            'mimes:'.implode(',', $extensions),
            'max:'.self::maxKb($type),
        ];
    }

    /**
     * Full file field rules for an explicit media type.
     *
     * @return list<string>
     */
    public static function fileRules(string $type, bool $required = true): array
    {
        return array_merge(
            [$required ? 'required' : 'nullable', 'file'],
            self::constraintRules($type),
        );
    }

    /**
     * Accept any supported WhatsApp media type (detect size per upload in after()).
     *
     * @return list<string>
     */
    public static function anyFileRules(bool $required = true): array
    {
        return array_merge(
            [$required ? 'required' : 'nullable', 'file'],
            [
                'mimes:'.implode(',', self::allExtensions()),
                'max:'.self::absoluteMaxKb(),
            ],
        );
    }

    /**
     * @return list<string>
     */
    public static function allExtensions(): array
    {
        $all = [];

        foreach (self::TYPES as $type) {
            foreach (self::extensions($type) as $ext) {
                $all[$ext] = $ext;
            }
        }

        return array_values($all);
    }

    public static function detectType(?UploadedFile $file): ?string
    {
        if ($file === null) {
            return null;
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        $mime = strtolower((string) ($file->getMimeType() ?? ''));

        foreach (self::TYPES as $type) {
            if ($ext !== '' && in_array($ext, self::extensions($type), true)) {
                return $type;
            }
        }

        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/')) {
            return 'audio';
        }
        if (
            $mime === 'application/pdf'
            || $mime === 'text/plain'
            || str_contains($mime, 'word')
            || str_contains($mime, 'excel')
            || str_contains($mime, 'powerpoint')
            || str_contains($mime, 'spreadsheet')
            || str_contains($mime, 'presentation')
            || str_contains($mime, 'officedocument')
        ) {
            return 'document';
        }

        return null;
    }

    /**
     * Enforce per-type size+mime when the upload endpoint only receives a file.
     *
     * @throws ValidationException
     */
    public static function assertValid(?UploadedFile $file, ?string $expectedType = null, string $attribute = 'file'): void
    {
        if ($file === null) {
            throw ValidationException::withMessages([
                $attribute => ['Please choose a file to upload.'],
            ]);
        }

        $type = $expectedType ?: self::detectType($file);

        if ($type === null || ! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages([
                $attribute => ['Unsupported file type. Use image, video, audio, or document formats allowed for WhatsApp.'],
            ]);
        }

        $extensions = self::extensions($type);
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($extensions !== [] && ($ext === '' || ! in_array($ext, $extensions, true))) {
            throw ValidationException::withMessages([
                $attribute => ['Invalid '.$type.' format. Allowed: '.strtoupper(implode(', ', $extensions)).'.'],
            ]);
        }

        $maxBytes = self::maxKb($type) * 1024;
        if ($file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                $attribute => [ucfirst($type).' must be '.self::maxMbLabel($type).' or smaller.'],
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function clientConfig(): array
    {
        $types = [];

        foreach (self::TYPES as $type) {
            $types[$type] = [
                'max_kb' => self::maxKb($type),
                'max_bytes' => self::maxKb($type) * 1024,
                'extensions' => self::extensions($type),
                'accept' => self::accept($type),
                'hint' => self::hint($type),
            ];
        }

        return [
            'types' => $types,
            'absolute_max_bytes' => self::absoluteMaxKb() * 1024,
        ];
    }
}
