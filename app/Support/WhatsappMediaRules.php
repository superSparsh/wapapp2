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
            'extensions:'.implode(',', $extensions),
            'max:'.self::maxKb($type),
        ];
    }

    /**
     * Human-friendly validation messages for a media type.
     *
     * @return array<string, string>
     */
    public static function validationMessages(string $type, string $attribute = 'file'): array
    {
        $label = match ($type) {
            'image' => 'image',
            'video' => 'video',
            'audio' => 'audio file',
            'document' => 'document',
            default => 'file',
        };
        $formats = strtoupper(implode(', ', self::extensions($type) ?: self::allExtensions()));
        $max = in_array($type, self::TYPES, true) ? self::maxMbLabel($type) : self::maxMbLabel('video');

        return [
            "{$attribute}.required" => 'Please choose a file to send.',
            "{$attribute}.file" => 'Please choose a valid file to send.',
            "{$attribute}.uploaded" => self::defaultUploadFailureMessage($type),
            "{$attribute}.mimes" => "This file type isn't allowed for {$label}s. Allowed formats: {$formats}.",
            "{$attribute}.extensions" => "This file type isn't allowed for {$label}s. Allowed formats: {$formats}.",
            "{$attribute}.max" => "This {$label} is too large. Maximum size is {$max}. Please compress it or choose a smaller file.",
        ];
    }

    /**
     * Human message when PHP rejects the upload (upload_max_filesize / post_max_size / partial).
     */
    public static function uploadFailureMessage(?UploadedFile $file, string $type = 'image'): ?string
    {
        if ($file === null) {
            return null;
        }

        if ($file->isValid()) {
            return null;
        }

        $label = $type === 'audio' ? 'audio file' : $type;
        $appMax = in_array($type, self::TYPES, true) ? self::maxMbLabel($type) : self::maxMbLabel('video');
        $phpMax = self::phpUploadMaxLabel();

        return match ($file->getError()) {
            \UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE => "This {$label} is too large for the server to accept (PHP limit {$phpMax}). App limit for {$label}s is {$appMax}. Please compress it or choose a smaller file.",
            \UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
            \UPLOAD_ERR_NO_FILE => 'Please choose a file to send.',
            \UPLOAD_ERR_NO_TMP_DIR, \UPLOAD_ERR_CANT_WRITE => 'The server could not save the upload. Please try again or contact support.',
            default => self::defaultUploadFailureMessage($type),
        };
    }

    public static function defaultUploadFailureMessage(string $type = 'image'): string
    {
        $label = $type === 'audio' ? 'audio file' : (in_array($type, self::TYPES, true) ? $type : 'file');
        $appMax = in_array($type, self::TYPES, true) ? self::maxMbLabel($type) : self::maxMbLabel('video');
        $phpMax = self::phpUploadMaxLabel();

        return "The {$label} failed to upload. Limits: {$label} {$appMax} (server upload cap {$phpMax}). Please try a smaller file.";
    }

    /**
     * True when the request body exceeded PHP post_max_size (POST/FILES empty but Content-Length set).
     */
    public static function requestExceededPostMaxSize(): bool
    {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLength <= 0) {
            return false;
        }

        return empty($_POST) && empty($_FILES);
    }

    public static function postMaxExceededMessage(): string
    {
        return 'This upload is too large for the server (PHP post_max_size '.self::phpUploadMaxLabel().'). Limits: image 5 MB; video, audio, and documents 14 MB. Please choose a smaller file.';
    }

    /**
     * Effective PHP upload ceiling in bytes (min of upload_max_filesize and post_max_size).
     */
    public static function phpUploadMaxBytes(): int
    {
        $upload = self::iniToBytes(ini_get('upload_max_filesize') ?: '0');
        $post = self::iniToBytes(ini_get('post_max_size') ?: '0');

        if ($upload <= 0 && $post <= 0) {
            return self::absoluteMaxKb() * 1024;
        }
        if ($upload <= 0) {
            return $post;
        }
        if ($post <= 0) {
            return $upload;
        }

        return min($upload, $post);
    }

    public static function phpUploadMaxLabel(): string
    {
        $bytes = self::phpUploadMaxBytes();
        if ($bytes <= 0) {
            return 'unknown';
        }

        $mb = $bytes / (1024 * 1024);
        if ($mb >= 1) {
            return rtrim(rtrim(number_format($mb, 1, '.', ''), '0'), '.').' MB';
        }

        return (string) max(1, (int) round($bytes / 1024)).' KB';
    }

    private static function iniToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '0') {
            return 0;
        }

        if (! preg_match('/^(\d+(?:\.\d+)?)([KMG])?$/i', $value, $matches)) {
            return (int) $value;
        }

        $num = (float) $matches[1];
        $unit = strtoupper($matches[2] ?? '');

        return (int) match ($unit) {
            'G' => $num * 1024 * 1024 * 1024,
            'M' => $num * 1024 * 1024,
            'K' => $num * 1024,
            default => $num,
        };
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
                $attribute => ['This file type is not supported. Please upload an image (JPEG/PNG/WEBP), video (MP4/3GP), audio (MP3/OGG/AMR/AAC/M4A), or document (PDF/DOCX/XLSX/PPTX/TXT).'],
            ]);
        }

        $extensions = self::extensions($type);
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($extensions !== [] && ($ext === '' || ! in_array($ext, $extensions, true))) {
            $formats = strtoupper(implode(', ', $extensions));
            throw ValidationException::withMessages([
                $attribute => ["This file type isn't allowed for {$type}s. Allowed formats: {$formats}."],
            ]);
        }

        $maxBytes = self::maxKb($type) * 1024;
        if ($file->getSize() > $maxBytes) {
            $label = $type === 'audio' ? 'audio file' : $type;
            throw ValidationException::withMessages([
                $attribute => ["This {$label} is too large. Maximum size is ".self::maxMbLabel($type).'. Please compress it or choose a smaller file.'],
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
            'php_upload_max_bytes' => self::phpUploadMaxBytes(),
            'php_upload_max_label' => self::phpUploadMaxLabel(),
        ];
    }
}
