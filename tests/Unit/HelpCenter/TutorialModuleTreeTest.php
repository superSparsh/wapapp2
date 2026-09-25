<?php

namespace Tests\Unit\HelpCenter;

use App\Domains\HelpCenter\Support\TutorialModuleTree;
use App\Models\TutorialVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TutorialModuleTreeTest extends TestCase
{
    use RefreshDatabase;

    private TutorialModuleTree $tree;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tree = app(TutorialModuleTree::class);
    }

    public function test_uses_exact_legacy_module_names_without_extra_headings(): void
    {
        $videos = collect([
            TutorialVideo::factory()->make([
                'id' => 1,
                'title' => 'Understanding the Chatbot Interface',
                'module_name' => 'Module 3: Automation - Sub-module 1: Chatbot',
            ]),
            TutorialVideo::factory()->make([
                'id' => 2,
                'title' => 'Inbox Basics',
                'module_name' => 'Module 2: Inbox',
            ]),
            TutorialVideo::factory()->make([
                'id' => 3,
                'title' => 'Dashboard Overview',
                'module_name' => 'Module 1: Dashboard',
            ]),
        ]);

        $categories = $this->tree->build($videos, 1);

        $this->assertCount(3, $categories);
        $this->assertSame('Module 3: Automation - Sub-module 1: Chatbot', $categories[0]['label']);
        $this->assertNull($categories[0]['children']);
        $this->assertSame('Module 2: Inbox', $categories[1]['label']);
        $this->assertSame('Module 1: Dashboard', $categories[2]['label']);
    }

    public function test_local_playback_url_uses_public_asset_when_file_exists(): void
    {
        $directory = (string) config('help-center.video_path');
        File::ensureDirectoryExists($directory);
        $filename = 'Video_1_Dashboard_Overview.mp4';
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        File::put($path, 'fake-video');

        try {
            $url = $this->tree->localPlaybackUrl($filename);
            $this->assertStringContainsString('/assets/videos/tutorials/'.$filename, $url);
        } finally {
            File::delete($path);
        }
    }

    public function test_navigation_returns_previous_and_next_ids(): void
    {
        $flat = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];

        $navigation = $this->tree->navigation($flat, 2);

        $this->assertSame(2, $navigation['index']);
        $this->assertSame(3, $navigation['total']);
        $this->assertSame(1, $navigation['previous_id']);
        $this->assertSame(3, $navigation['next_id']);
    }
}
