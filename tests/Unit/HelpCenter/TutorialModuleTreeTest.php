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

    public function test_keeps_legacy_module_labels_with_submodules(): void
    {
        $videos = collect([
            TutorialVideo::factory()->make([
                'id' => 1,
                'title' => 'Understanding the Chatbot Interface',
                'module_name' => 'Module 3: Automation - Sub-module 1: Chatbot',
                'sort_order' => 20,
            ]),
            TutorialVideo::factory()->make([
                'id' => 2,
                'title' => 'Finding & Filtering Chats',
                'module_name' => 'Module 2: Inbox',
                'sort_order' => 10,
            ]),
            TutorialVideo::factory()->make([
                'id' => 3,
                'title' => 'Dashboard Overview',
                'module_name' => 'Module 1: Dashboard',
                'sort_order' => 1,
            ]),
        ]);

        $categories = $this->tree->build($videos, 1);

        $this->assertCount(3, $categories);
        $this->assertSame('Module 1: Dashboard', $categories[0]['label']);
        $this->assertNull($categories[0]['children']);
        $this->assertSame('Module 2: Inbox', $categories[1]['label']);
        $this->assertNull($categories[1]['children']);
        $this->assertSame('Module 3: Automation', $categories[2]['label']);
        $this->assertNotNull($categories[2]['children']);
        $this->assertSame('Chatbot', $categories[2]['children'][0]['label']);
    }

    public function test_sorts_modules_numerically_and_puts_product_demo_last(): void
    {
        $videos = collect([
            TutorialVideo::factory()->make([
                'id' => 1,
                'title' => 'Complete Overview',
                'module_name' => 'Product Demo',
                'sort_order' => 999,
            ]),
            TutorialVideo::factory()->make([
                'id' => 2,
                'title' => 'Drip Welcome',
                'module_name' => 'Module 3: Automation - Sub-module 2: Drip Marketing',
                'sort_order' => 30,
            ]),
            TutorialVideo::factory()->make([
                'id' => 3,
                'title' => 'Chatbot Intro',
                'module_name' => 'Module 3: Automation - Sub-module 1: Chatbot',
                'sort_order' => 20,
            ]),
            TutorialVideo::factory()->make([
                'id' => 4,
                'title' => 'Inbox Filters',
                'module_name' => 'Module 2: Inbox',
                'sort_order' => 10,
            ]),
            TutorialVideo::factory()->make([
                'id' => 5,
                'title' => 'Dashboard Overview',
                'module_name' => 'Module 1: Dashboard',
                'sort_order' => 1,
            ]),
            TutorialVideo::factory()->make([
                'id' => 6,
                'title' => 'Help Search',
                'module_name' => 'Module 16: Help & Search',
                'sort_order' => 160,
            ]),
        ]);

        $categories = $this->tree->build($videos);

        $this->assertSame([
            'Module 1: Dashboard',
            'Module 2: Inbox',
            'Module 3: Automation',
            'Module 16: Help & Search',
            'Product Demo',
        ], array_column($categories, 'label'));

        $this->assertSame(['Chatbot', 'Drip Marketing'], array_column($categories[2]['children'], 'label'));

        $flat = $this->tree->flat($videos);
        $this->assertSame([
            'Dashboard Overview',
            'Inbox Filters',
            'Chatbot Intro',
            'Drip Welcome',
            'Help Search',
            'Complete Overview',
        ], array_column($flat, 'title'));
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
