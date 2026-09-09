<?php

declare(strict_types=1);

namespace App\Domains\Audience\Http\Controllers;

use App\Domains\Audience\Http\Requests\Segment\StoreSegmentRequest;
use App\Domains\Audience\Http\Requests\Segment\UpdateSegmentRequest;
use App\Domains\Audience\Models\Segment;
use App\Domains\Audience\Services\SegmentService;
use App\Models\MailList;
use App\Support\PublicId;
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
        $mailList = $request->filled('list')
            ? PublicId::findOrFail(MailList::class, (string) $request->input('list'))
            : null;

        $segments = $this->service->index(
            mailListId: $mailList?->id,
            search: $request->get('search'),
            sort: $request->get('sort', 'created_at'),
            direction: $request->get('direction', 'desc'),
        );

        return view('audience.segments', [
            'segments' => $segments,
            'mailListId' => $mailList?->uuid,
            'mailList' => $mailList,
            'listFields' => $mailList
                ? $mailList->listFields()->orderBy('sort_order')->get(['id', 'label', 'tag', 'type'])
                : collect(),
            'currentSort' => $request->get('sort', 'created_at'),
            'currentDirection' => $request->get('direction', 'desc'),
        ]);
    }

    /**
     * Store a new segment.
     */
    public function store(StoreSegmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $mailList = PublicId::find(MailList::class, $data['mail_list_id'] ?? null);
        $data['mail_list_id'] = $mailList?->id;

        $this->service->store($data);

        return redirect()->route('audience.segments', array_filter(['list' => $mailList?->uuid]))
            ->with('status', 'Segment created successfully.');
    }

    /**
     * Update a segment.
     */
    public function update(UpdateSegmentRequest $request, Segment $segment): RedirectResponse
    {
        $data = $request->validated();
        if (array_key_exists('mail_list_id', $data)) {
            $mailList = PublicId::find(MailList::class, $data['mail_list_id'] ?? null);
            $data['mail_list_id'] = $mailList?->id;
        }

        $this->service->update($segment, $data);

        $segment = $segment->fresh();
        $listUuid = $segment?->mail_list_id
            ? MailList::query()->whereKey($segment->mail_list_id)->value('uuid')
            : null;

        return redirect()->route('audience.segments', array_filter(['list' => $listUuid]))
            ->with('status', 'Segment updated successfully.');
    }

    /**
     * Delete a segment.
     */
    public function destroy(Segment $segment): RedirectResponse
    {
        $listUuid = $segment->mail_list_id
            ? MailList::query()->whereKey($segment->mail_list_id)->value('uuid')
            : null;

        $this->service->destroy($segment);

        return redirect()->route('audience.segments', array_filter(['list' => $listUuid]))
            ->with('status', 'Segment deleted successfully.');
    }
}
