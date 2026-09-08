<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Http\Requests\Segment\StoreSegmentRequest;
use App\Domains\Audience\Http\Requests\Segment\UpdateSegmentRequest;
use App\Domains\Audience\Services\SegmentService;
use App\Domains\Audience\Models\Segment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class SegmentController extends Controller
{
    public function __construct(
        private readonly SegmentService $service,
    ) {}

    /**
     * Segments listing.
     */
    public function index(Request $request): View
    {
        $mailListId = $request->has('list') ? $request->integer('list') : null;

        $segments = $this->service->index(
            mailListId: $mailListId,
            search: $request->get('search'),
            sort: $request->get('sort', 'created_at'),
            direction: $request->get('direction', 'desc'),
        );

        return view('audience.segments', [
            'segments' => $segments,
            'mailListId' => $mailListId,
            'currentSort' => $request->get('sort', 'created_at'),
            'currentDirection' => $request->get('direction', 'desc'),
        ]);
    }

    /**
     * Store a new segment.
     */
    public function store(StoreSegmentRequest $request): RedirectResponse
    {
        $this->service->store($request->validated());

        return redirect()->route('audience.segments')
            ->with('status', 'Segment created successfully.');
    }

    /**
     * Update a segment.
     */
    public function update(UpdateSegmentRequest $request, Segment $segment): RedirectResponse
    {
        $this->service->update($segment, $request->validated());

        return redirect()->route('audience.segments')
            ->with('status', 'Segment updated successfully.');
    }

    /**
     * Delete a segment.
     */
    public function destroy(Segment $segment): RedirectResponse
    {
        $this->service->destroy($segment);

        return redirect()->route('audience.segments')
            ->with('status', 'Segment deleted successfully.');
    }
}
