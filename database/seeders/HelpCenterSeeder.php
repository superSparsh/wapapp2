<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\TutorialVideo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class HelpCenterSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFaqs();
        $this->seedTutorials();
    }

    private function seedFaqs(): void
    {
        foreach ($this->legacyFaqRows() as $index => $faq) {
            $slug = trim((string) ($faq['slug'] ?? ''));
            $heading = trim((string) ($faq['heading'] ?? ''));
            $description = (string) ($faq['description'] ?? '');

            if ($slug === '' || $heading === '' || $description === '') {
                continue;
            }

            Faq::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'heading' => $heading,
                    'description' => $description,
                    'is_active' => (bool) ($faq['status'] ?? true),
                    'sort_order' => $index + 1,
                ],
            );
        }
    }

    /**
     * Prefer the exported legacy dump so customer-facing FAQs match production WapApp.
     *
     * @return list<array{heading: string, description: string, slug: string, status?: bool|int}>
     */
    private function legacyFaqRows(): array
    {
        $path = database_path('data/legacy-faqs.json');

        if (File::isFile($path)) {
            $decoded = json_decode(File::get($path), true);
            if (is_array($decoded) && $decoded !== []) {
                return array_values(array_filter(
                    $decoded,
                    static fn ($row): bool => is_array($row),
                ));
            }
        }

        return [
            [
                'heading' => 'Messaging Limits',
                'slug' => 'messaging-limits',
                'status' => 1,
                'description' => '<p>WhatsApp messaging limits depend on your phone number quality rating and display name verification status.</p>',
            ],
            [
                'heading' => 'Template Categorization',
                'slug' => 'template-categorization',
                'status' => 1,
                'description' => '<p>Marketing and utility templates are categorized by Meta based on intent and content.</p>',
            ],
        ];
    }

    private function seedTutorials(): void
    {
        // Remove old placeholder seed groups that conflicted with legacy imports.
        TutorialVideo::query()
            ->where(function ($query): void {
                $query->whereIn('module_name', [
                    'INBOX',
                    'DASHBOARD',
                    'AUTOMATION',
                    'ACCOUNTS',
                    'DASHBOARD - Sub-module 1: Getting Started',
                ])->orWhere('module_name', 'like', 'DASHBOARD - Sub-module%')
                    ->orWhere('youtube_id', 'dQw4w9WgXcQ');
            })
            ->delete();

        foreach ($this->legacyTutorialRows() as $video) {
            $title = trim((string) ($video['title'] ?? ''));
            $moduleName = trim((string) ($video['module_name'] ?? ''));
            $youtubeId = trim((string) ($video['youtube_id'] ?? ''));

            if ($title === '' || $moduleName === '' || $youtubeId === '') {
                continue;
            }

            TutorialVideo::query()->updateOrCreate(
                [
                    'title' => $title,
                    'module_name' => $moduleName,
                ],
                [
                    'youtube_id' => $youtubeId,
                    'description' => $video['description'] ?? null,
                    'duration' => $video['duration'] ?? null,
                    'sort_order' => (int) ($video['sort_order'] ?? 0),
                    'is_active' => (bool) ($video['is_active'] ?? true),
                ],
            );
        }
    }

    /**
     * Prefer the exported legacy dump so customer-facing tutorials match production WapApp.
     *
     * @return list<array{
     *     title: string,
     *     module_name: string,
     *     youtube_id: string,
     *     description?: string|null,
     *     duration?: string|null,
     *     sort_order?: int,
     *     is_active?: bool|int
     * }>
     */
    private function legacyTutorialRows(): array
    {
        $path = database_path('data/legacy-tutorials.json');

        if (File::isFile($path)) {
            $decoded = json_decode(File::get($path), true);
            if (is_array($decoded) && $decoded !== []) {
                return array_values(array_filter(
                    $decoded,
                    static fn ($row): bool => is_array($row),
                ));
            }
        }

        return [
            [
                'title' => 'Dashboard Overview',
                'module_name' => 'Module 1: Dashboard',
                'youtube_id' => 'Video_1_Dashboard_Overview.mp4',
                'description' => 'Learn how to navigate the dashboard and monitor key metrics.',
                'duration' => '2:00',
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'title' => 'Finding & Filtering Chats',
                'module_name' => 'Module 2: Inbox',
                'youtube_id' => 'Video_1_Finding_&_Filtering_Chats.mp4',
                'description' => 'Reply to customers, assign conversations, and use filters.',
                'duration' => '4:30',
                'sort_order' => 6,
                'is_active' => true,
            ],
        ];
    }
}
