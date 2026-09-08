<?php

namespace Tests\Unit\HelpCenter;

use App\Domains\HelpCenter\Support\TutorialModuleTree;
use App\Models\TutorialVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_builds_parent_and_submodule_structure(): void
    {
        $videos = collect([
            TutorialVideo::factory()->make([
                'id' => 1,
                'title' => 'Dashboard Overview',
                'module_name' => 'DASHBOARD - Sub-module 1: Getting Started',
            ]),
            TutorialVideo::factory()->make([
                'id' => 2,
                'title' => 'Inbox Basics',
                'module_name' => 'INBOX',
            ]),
        ]);

        $categories = $this->tree->build($videos, 1);

        $this->assertCount(2, $categories);
        $this->assertSame('DASHBOARD', $categories[0]['label']);
        $this->assertNotNull($categories[0]['children']);
        $this->assertSame('INBOX', $categories[1]['label']);
        $this->assertNotNull($categories[1]['videos']);
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
