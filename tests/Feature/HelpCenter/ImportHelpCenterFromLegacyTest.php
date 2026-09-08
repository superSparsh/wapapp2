<?php

namespace Tests\Feature\HelpCenter;

use App\Domains\HelpCenter\Services\HelpCenterLegacyImportService;
use App\Domains\HelpCenter\Support\HelpCenterCache;
use App\Models\Faq;
use App\Models\TutorialVideo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportHelpCenterFromLegacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        HelpCenterCache::flush();

        Config::set('database.connections.legacy', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        Schema::connection('legacy')->create('faq', function (Blueprint $table): void {
            $table->id();
            $table->string('heading');
            $table->text('description');
            $table->string('slug')->unique();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::connection('legacy')->create('tutorial_videos', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('module_name');
            $table->string('youtube_id');
            $table->text('description')->nullable();
            $table->string('duration')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_imports_faqs_and_tutorials_from_legacy_connection(): void
    {
        $this->seedLegacyData();

        $stats = app(HelpCenterLegacyImportService::class)->import(
            fresh: true,
            importFaqs: true,
            importTutorials: true,
            copyVideos: false,
            dryRun: false,
        );

        $this->assertSame(1, $stats['faqs']);
        $this->assertSame(1, $stats['tutorials']);
        $this->assertDatabaseHas('faqs', [
            'slug' => 'messaging-limits',
            'heading' => 'Messaging Limits',
            'is_active' => 1,
        ]);
        $this->assertDatabaseHas('tutorial_videos', [
            'title' => 'Dashboard Overview',
            'module_name' => 'DASHBOARD',
            'youtube_id' => 'abc123',
            'is_active' => 1,
        ]);
    }

    public function test_dry_run_does_not_write_records(): void
    {
        $this->seedLegacyData();

        $stats = app(HelpCenterLegacyImportService::class)->import(
            fresh: false,
            importFaqs: true,
            importTutorials: true,
            copyVideos: false,
            dryRun: true,
        );

        $this->assertSame(1, $stats['faqs']);
        $this->assertSame(1, $stats['tutorials']);
        $this->assertSame(0, Faq::query()->count());
        $this->assertSame(0, TutorialVideo::query()->count());
    }

    public function test_artisan_command_imports_legacy_content(): void
    {
        $this->seedLegacyData();

        $this->artisan('help-center:import-legacy', ['--fresh' => true, '--force' => true])
            ->assertSuccessful();

        $this->assertSame(1, Faq::query()->count());
        $this->assertSame(1, TutorialVideo::query()->count());
    }

    private function seedLegacyData(): void
    {
        DB::connection('legacy')->table('faq')->insert([
            'heading' => 'Messaging Limits',
            'description' => '<p>Daily limits depend on quality rating.</p>',
            'slug' => 'messaging-limits',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('legacy')->table('tutorial_videos')->insert([
            'title' => 'Dashboard Overview',
            'module_name' => 'DASHBOARD',
            'youtube_id' => 'abc123',
            'description' => 'Overview video',
            'duration' => '2:00',
            'sort_order' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
