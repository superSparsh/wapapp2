<?php

declare(strict_types=1);

namespace App\Domains\HelpCenter\Http\Controllers;

use App\Domains\HelpCenter\Services\TutorialQueryService;
use App\Domains\HelpCenter\Support\TutorialModuleTree;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TutorialController extends Controller
{
    public function index(
        Request $request,
        TutorialQueryService $queryService,
        TutorialModuleTree $moduleTree,
    ): View {
        $search = $request->string('q')->trim()->toString();
        $allVideos = $queryService->active();
        $videos = $search !== '' ? $queryService->search($search) : $allVideos;
        $flatVideos = $moduleTree->flat($allVideos);

        $videoId = (int) $request->integer('video_id');
        $selected = $videoId > 0 ? $allVideos->firstWhere('id', $videoId) : null;

        if ($selected === null && $flatVideos !== []) {
            $selected = $allVideos->firstWhere('id', (int) $flatVideos[0]['id']);
            $videoId = (int) ($selected?->id ?? 0);
        }

        $current = $selected !== null ? $moduleTree->videoRow($selected, $videoId) : null;
        $navigation = $moduleTree->navigation($flatVideos, $videoId > 0 ? $videoId : null);

        return view('tutorials.index', [
            'categories' => $moduleTree->build($videos, $videoId > 0 ? $videoId : null, $search),
            'current' => $current,
            'navigation' => $navigation,
            'search' => $search,
            'shareUrl' => $videoId > 0 ? route('tutorials.index', ['video_id' => $videoId]) : route('tutorials.index'),
        ]);
    }

    public function stream(string $filename): StreamedResponse
    {
        $filename = basename($filename);
        $path = config('help-center.video_path').DIRECTORY_SEPARATOR.$filename;

        abort_unless(File::isFile($path), 404);

        $size = filesize($path);
        abort_if($size === false, 404);

        $start = 0;
        $end = $size - 1;
        $status = 200;
        $length = $size;

        if ($range = request()->header('Range')) {
            if (preg_match('/bytes=(\d+)-(\d*)/', $range, $matches) === 1) {
                $start = (int) $matches[1];
                $end = $matches[2] !== '' ? (int) $matches[2] : $end;
                $end = min($end, $size - 1);
                $length = $end - $start + 1;
                $status = 206;
            }
        }

        $headers = [
            'Content-Type' => 'video/mp4',
            'Accept-Ranges' => 'bytes',
            'Content-Length' => (string) $length,
        ];

        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return Response::stream(function () use ($path, $start, $length): void {
            $stream = fopen($path, 'rb');
            abort_if($stream === false, 404);

            fseek($stream, $start);
            echo fread($stream, $length);
            fclose($stream);
        }, $status, $headers);
    }
}
