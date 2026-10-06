<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\TutorialVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class AdminTutorialVideoUploadTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    private Admin $admin;

    private string $videoDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();

        $this->admin = Admin::query()->create([
            'name' => 'Tutorial Admin',
            'email' => 'admin-tutorials-upload@wapapp.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->videoDir = storage_path('framework/testing/tutorial-videos-'.uniqid());
        File::ensureDirectoryExists($this->videoDir);
        config(['help-center.video_path' => $this->videoDir]);
    }

    protected function tearDown(): void
    {
        if (isset($this->videoDir) && File::isDirectory($this->videoDir)) {
            File::deleteDirectory($this->videoDir);
        }

        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_admin_can_upload_video_when_creating_tutorial(): void
    {
        $file = UploadedFile::fake()->create('Dashboard_Overview.mp4', 1024, 'video/mp4');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.tutorials.store'), [
                'title' => 'Dashboard Overview',
                'module_name' => 'Module 1: Dashboard',
                'youtube_id' => 'Video_1_Dashboard_Overview.mp4',
                'duration' => '1:30',
                'sort_order' => 1,
                'is_active' => '1',
                'video' => $file,
            ])
            ->assertRedirect(route('admin.tutorials.index'));

        $this->assertTrue(File::isFile($this->videoDir.'/Video_1_Dashboard_Overview.mp4'));
        $this->assertDatabaseHas('tutorial_videos', [
            'title' => 'Dashboard Overview',
            'youtube_id' => 'Video_1_Dashboard_Overview.mp4',
        ], config('tenancy.database.central_connection'));
    }

    public function test_admin_can_replace_existing_video_file(): void
    {
        $path = $this->videoDir.'/Video_1_Dashboard_Overview.mp4';
        File::put($path, 'old-video-bytes');

        $tutorial = TutorialVideo::factory()->create([
            'title' => 'Dashboard Overview',
            'module_name' => 'Module 1: Dashboard',
            'youtube_id' => 'Video_1_Dashboard_Overview.mp4',
        ]);

        $replacement = UploadedFile::fake()->create('Dashboard_Overview.mp4', 2048, 'video/mp4');

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.tutorials.update', $tutorial), [
                'title' => 'Dashboard Overview',
                'module_name' => 'Module 1: Dashboard',
                'youtube_id' => 'Video_1_Dashboard_Overview.mp4',
                'duration' => '1:30',
                'sort_order' => 1,
                'is_active' => '1',
                'video' => $replacement,
            ])
            ->assertRedirect(route('admin.tutorials.index'))
            ->assertSessionHas('status', 'Tutorial updated and video replaced.');

        $this->assertTrue(File::isFile($path));
        $this->assertNotSame('old-video-bytes', File::get($path));
    }

    public function test_admin_can_delete_video_file_without_deleting_tutorial_row(): void
    {
        $path = $this->videoDir.'/Video_1_Dashboard_Overview.mp4';
        File::put($path, 'delete-me');

        $tutorial = TutorialVideo::factory()->create([
            'youtube_id' => 'Video_1_Dashboard_Overview.mp4',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.tutorials.destroy-video', $tutorial))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertFalse(File::isFile($path));
        $this->assertDatabaseHas('tutorial_videos', [
            'id' => $tutorial->id,
            'youtube_id' => 'Video_1_Dashboard_Overview.mp4',
        ], config('tenancy.database.central_connection'));
    }

    public function test_deleting_tutorial_also_removes_video_file(): void
    {
        $path = $this->videoDir.'/Video_to_remove.mp4';
        File::put($path, 'gone');

        $tutorial = TutorialVideo::factory()->create([
            'youtube_id' => 'Video_to_remove.mp4',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.tutorials.destroy', $tutorial))
            ->assertRedirect();

        $this->assertFalse(File::isFile($path));
        $this->assertDatabaseMissing('tutorial_videos', [
            'id' => $tutorial->id,
        ], config('tenancy.database.central_connection'));
    }

    public function test_replace_with_new_filename_keeps_previous_file_as_fallback(): void
    {
        $oldPath = $this->videoDir.'/old_name.mp4';
        File::put($oldPath, 'old');

        $tutorial = TutorialVideo::factory()->create([
            'youtube_id' => 'old_name.mp4',
        ]);

        $replacement = UploadedFile::fake()->create('new_name.mp4', 1024, 'video/mp4');

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.tutorials.update', $tutorial), [
                'title' => $tutorial->title,
                'module_name' => $tutorial->module_name,
                'youtube_id' => 'new_name.mp4',
                'sort_order' => 0,
                'is_active' => '1',
                'video' => $replacement,
            ])
            ->assertRedirect(route('admin.tutorials.index'));

        $this->assertTrue(File::isFile($oldPath));
        $this->assertTrue(File::isFile($this->videoDir.'/new_name.mp4'));
        $this->assertDatabaseHas('tutorial_videos', [
            'id' => $tutorial->id,
            'youtube_id' => 'new_name.mp4',
            'previous_youtube_id' => 'old_name.mp4',
        ], config('tenancy.database.central_connection'));
    }

    public function test_frontend_falls_back_to_previous_file_when_new_missing(): void
    {
        File::put($this->videoDir.'/old_name.mp4', 'old-bytes');

        $tutorial = TutorialVideo::factory()->create([
            'title' => 'Fallback Tutorial',
            'module_name' => 'Module 1: Dashboard',
            'youtube_id' => 'new_missing.mp4',
            'previous_youtube_id' => 'old_name.mp4',
            'video_updated_at' => now(),
            'is_active' => true,
        ]);

        $tree = app(\App\Domains\HelpCenter\Support\TutorialModuleTree::class);
        $row = $tree->videoRow($tutorial, $tutorial->id);

        $this->assertTrue($row['has_file']);
        $this->assertTrue($row['using_fallback']);
        $this->assertFalse($row['is_updated']);
        $this->assertSame('old_name.mp4', $row['playback_filename']);
    }

    public function test_can_upload_replacement_after_deleting_old_file(): void
    {
        $path = $this->videoDir.'/Video_1_Dashboard_Overview.mp4';
        File::put($path, 'old');

        $tutorial = TutorialVideo::factory()->create([
            'youtube_id' => 'Video_1_Dashboard_Overview.mp4',
            'title' => 'Dashboard Overview',
            'module_name' => 'Module 1: Dashboard',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.tutorials.destroy-video', $tutorial))
            ->assertRedirect();

        $this->assertFalse(File::isFile($path));

        $replacement = UploadedFile::fake()->create('Dashboard_Overview.mp4', 2048, 'video/mp4');

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.tutorials.update', $tutorial), [
                'title' => 'Dashboard Overview',
                'module_name' => 'Module 1: Dashboard',
                'youtube_id' => 'Video_1_Dashboard_Overview.mp4',
                'sort_order' => 1,
                'is_active' => '1',
                'video' => $replacement,
            ])
            ->assertRedirect(route('admin.tutorials.index'))
            ->assertSessionHas('status', 'Tutorial updated and video replaced.');

        $this->assertTrue(File::isFile($path));
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.tutorials.index'))
            ->assertOk()
            ->assertSee('Updated');
    }

    public function test_pointing_filename_at_existing_disk_file_clears_missing(): void
    {
        File::put($this->videoDir.'/fresh_upload.mp4', 'bytes');

        $tutorial = TutorialVideo::factory()->create([
            'youtube_id' => 'old_deleted.mp4',
            'title' => 'Fresh',
            'module_name' => 'Module 1: Dashboard',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.tutorials.update', $tutorial), [
                'title' => 'Fresh',
                'module_name' => 'Module 1: Dashboard',
                'youtube_id' => 'fresh_upload.mp4',
                'sort_order' => 0,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.tutorials.index'));

        $this->assertDatabaseHas('tutorial_videos', [
            'id' => $tutorial->id,
            'youtube_id' => 'fresh_upload.mp4',
        ], config('tenancy.database.central_connection'));

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.tutorials.index'))
            ->assertOk()
            ->assertSee('Updated');
    }
}
