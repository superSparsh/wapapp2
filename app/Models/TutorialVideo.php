<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutorialVideo extends Model
{
    use HasFactory;
    use UsesCentralConnection;

    protected $fillable = [
        'title',
        'module_name',
        'youtube_id',
        'previous_youtube_id',
        'video_updated_at',
        'description',
        'duration',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'video_updated_at' => 'datetime',
        ];
    }

    public function isLocalFile(): bool
    {
        return $this->looksLikeLocalFilename((string) $this->youtube_id)
            || $this->looksLikeLocalFilename((string) $this->previous_youtube_id);
    }

    /**
     * Prefer the current (updated) filename, then the previous/legacy filename.
     *
     * @return list<string>
     */
    public function candidateFilenames(): array
    {
        $candidates = [];

        foreach ([(string) $this->youtube_id, (string) $this->previous_youtube_id] as $name) {
            $name = basename(trim($name));
            if ($name === '' || ! $this->looksLikeLocalFilename($name)) {
                continue;
            }
            if (! in_array($name, $candidates, true)) {
                $candidates[] = $name;
            }
        }

        return $candidates;
    }

    public function isYoutube(): bool
    {
        $id = trim((string) $this->youtube_id);

        if ($id === '' || $this->looksLikeLocalFilename($id)) {
            return false;
        }

        if (preg_match('/(youtube\.com|youtu\.be)/i', $id) === 1) {
            return true;
        }

        // Bare YouTube video ids are typically 11 chars.
        return (bool) preg_match('/^[A-Za-z0-9_-]{11}$/', $id);
    }

    private function looksLikeLocalFilename(string $id): bool
    {
        $id = trim($id);

        if ($id === '') {
            return false;
        }

        // Full YouTube URLs must never be treated as local filenames.
        if (preg_match('/(youtube\.com|youtu\.be)/i', $id) === 1) {
            return false;
        }

        if (preg_match('/^https?:\/\//i', $id) === 1) {
            return false;
        }

        // Local uploads: Video_1_....mp4 / "Video 1 ....mp4"
        if (preg_match('/\.(mp4|webm|ogg|mov)(\?.*)?$/i', $id) === 1) {
            return true;
        }

        return str_contains($id, '.') && ! str_starts_with($id, 'http');
    }
}
