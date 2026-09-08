<?php

declare(strict_types=1);

namespace App\Domains\HelpCenter\Services;

use App\Models\Faq;
use App\Models\TutorialVideo;
use App\Domains\HelpCenter\Support\HelpCenterCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class HelpCenterLegacyImportService
{
    /**
     * @return array{faqs: int, tutorials: int, videos_copied: int}
     */
    public function import(
        bool $fresh = false,
        bool $importFaqs = true,
        bool $importTutorials = true,
        bool $copyVideos = false,
        bool $dryRun = false,
    ): array {
        $this->assertLegacyConnection();

        $stats = [
            'faqs' => 0,
            'tutorials' => 0,
            'videos_copied' => 0,
        ];

        if ($dryRun) {
            return [
                'faqs' => $importFaqs ? $this->legacyFaqs()->count() : 0,
                'tutorials' => $importTutorials ? $this->legacyTutorials()->count() : 0,
                'videos_copied' => $copyVideos ? $this->localVideoFilenames()->count() : 0,
            ];
        }

        if ($fresh) {
            if ($importFaqs) {
                Faq::query()->delete();
            }

            if ($importTutorials) {
                TutorialVideo::query()->delete();
            }
        }

        if ($importFaqs) {
            $stats['faqs'] = $this->importFaqs();
        }

        if ($importTutorials) {
            $stats['tutorials'] = $this->importTutorials();
        }

        if ($copyVideos && $importTutorials) {
            $stats['videos_copied'] = $this->copyLocalTutorialVideos();
        }

        $this->clearCache();

        return $stats;
    }

    public function assertLegacyConnection(): void
    {
        $connection = (string) config('help-center.legacy.connection', 'legacy');

        try {
            DB::connection($connection)->getPdo();
        } catch (\Throwable $exception) {
            throw new RuntimeException('Legacy database connection failed: '.$exception->getMessage(), previous: $exception);
        }

        $faqTable = (string) config('help-center.legacy.faq_table', 'faq');
        $tutorialTable = (string) config('help-center.legacy.tutorial_videos_table', 'tutorial_videos');

        if (! Schema::connection($connection)->hasTable($faqTable)) {
            throw new RuntimeException("Legacy table [{$faqTable}] was not found.");
        }

        if (! Schema::connection($connection)->hasTable($tutorialTable)) {
            throw new RuntimeException("Legacy table [{$tutorialTable}] was not found.");
        }
    }

    private function importFaqs(): int
    {
        $imported = 0;

        foreach ($this->legacyFaqs() as $row) {
            $slug = trim((string) ($row->slug ?? ''));

            if ($slug === '') {
                continue;
            }

            Faq::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'heading' => (string) $row->heading,
                    'description' => (string) $row->description,
                    'is_active' => (bool) ($row->status ?? true),
                    'sort_order' => (int) ($row->id ?? 0),
                ],
            );

            $imported++;
        }

        return $imported;
    }

    private function importTutorials(): int
    {
        $imported = 0;

        foreach ($this->legacyTutorials() as $row) {
            $title = trim((string) ($row->title ?? ''));
            $moduleName = trim((string) ($row->module_name ?? ''));

            if ($title === '' || $moduleName === '') {
                continue;
            }

            TutorialVideo::query()->updateOrCreate(
                [
                    'title' => $title,
                    'module_name' => $moduleName,
                ],
                [
                    'youtube_id' => (string) $row->youtube_id,
                    'description' => $row->description,
                    'duration' => $row->duration,
                    'sort_order' => (int) ($row->sort_order ?? 0),
                    'is_active' => (bool) ($row->is_active ?? true),
                ],
            );

            $imported++;
        }

        return $imported;
    }

    private function copyLocalTutorialVideos(): int
    {
        $sourcePath = (string) config('help-center.legacy.video_source_path');
        $targetPath = (string) config('help-center.video_path');

        if (! File::isDirectory($sourcePath)) {
            return 0;
        }

        File::ensureDirectoryExists($targetPath);

        $copied = 0;

        foreach ($this->localVideoFilenames() as $filename) {
            $source = $sourcePath.DIRECTORY_SEPARATOR.$filename;
            $target = $targetPath.DIRECTORY_SEPARATOR.$filename;

            if (! File::isFile($source)) {
                continue;
            }

            if (! File::exists($target) || File::lastModified($source) > File::lastModified($target)) {
                File::copy($source, $target);
                $copied++;
            }
        }

        return $copied;
    }

    /**
     * @return Collection<int, object>
     */
    private function legacyFaqs(): Collection
    {
        $connection = (string) config('help-center.legacy.connection', 'legacy');
        $table = (string) config('help-center.legacy.faq_table', 'faq');

        return DB::connection($connection)
            ->table($table)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function legacyTutorials(): Collection
    {
        $connection = (string) config('help-center.legacy.connection', 'legacy');
        $table = (string) config('help-center.legacy.tutorial_videos_table', 'tutorial_videos');

        return DB::connection($connection)
            ->table($table)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, string>
     */
    private function localVideoFilenames(): Collection
    {
        return $this->legacyTutorials()
            ->pluck('youtube_id')
            ->filter(fn ($value): bool => is_string($value) && str_contains($value, '.'))
            ->map(fn (string $value): string => basename($value))
            ->unique()
            ->values();
    }

    private function clearCache(): void
    {
        HelpCenterCache::flush();
    }
}
