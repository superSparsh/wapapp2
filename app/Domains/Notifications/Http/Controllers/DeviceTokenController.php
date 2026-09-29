<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Http\Controllers;

use App\Domains\Notifications\Services\FcmPushService;
use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\CurrentAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeviceTokenController extends Controller
{
    public function __construct(
        private readonly FcmPushService $fcm,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['nullable', 'string', 'max:32'],
            'device_id' => ['nullable', 'string', 'max:191'],
        ]);

        [$userId, $teamMemberId] = $this->resolveActorIds($request);

        try {
            $row = $this->fcm->register([
                'token' => $validated['token'],
                'platform' => $validated['platform'] ?? null,
                'device_id' => $validated['device_id'] ?? null,
                'user_id' => $userId,
                'team_member_id' => $teamMemberId,
            ]);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['token' => [$e->getMessage()]]);
        }

        return response()->json([
            'success' => true,
            'id' => $row->id,
            'token' => $row->token,
            'platform' => $row->platform,
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        [$userId, $teamMemberId] = $this->resolveActorIds($request);

        $deleted = $this->fcm->revoke(
            token: $validated['token'],
            userId: $userId,
            teamMemberId: $teamMemberId,
        );

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
        ]);
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function resolveActorIds(Request $request): array
    {
        $user = $request->user();
        if ($user instanceof User) {
            return [(int) $user->id, null];
        }
        if ($user instanceof TeamMember) {
            return [null, (int) $user->id];
        }

        // Fallback for session auth when request user resolver is unset.
        $account = CurrentAccount::user();
        if ($account instanceof User) {
            return [(int) $account->id, null];
        }
        if ($account instanceof TeamMember) {
            return [null, (int) $account->id];
        }

        throw ValidationException::withMessages([
            'token' => ['Unauthenticated.'],
        ]);
    }
}
