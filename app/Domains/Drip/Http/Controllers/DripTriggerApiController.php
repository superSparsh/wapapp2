<?php

declare(strict_types=1);

namespace App\Domains\Drip\Http\Controllers;

use App\Domains\Drip\Services\DripTriggerDispatcher;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\DripCampaign;
use App\Support\PhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DripTriggerApiController extends Controller
{
    public function __invoke(Request $request, string $campaign, DripTriggerDispatcher $dispatcher): JsonResponse
    {
        $model = DripCampaign::query()->where('uuid', $campaign)->first();
        if ($model === null) {
            return response()->json(['message' => 'Drip campaign was not found.'], 404);
        }

        $phone = PhoneNormalizer::normalize((string) ($request->input('phone') ?: $request->input('whatsapp_number')));
        if ($phone === null) {
            return response()->json(['message' => 'A valid phone number is required.'], 422);
        }

        $contact = Contact::query()->whereIn('phone', PhoneNormalizer::lookupVariants($phone))->first();
        if ($contact === null) {
            return response()->json(['message' => 'Contact was not found for this phone number.'], 404);
        }

        $started = $dispatcher->enroll($model, $contact, force: true);

        return response()->json([
            'ok' => $started,
            'message' => $started
                ? 'Drip campaign started for this contact.'
                : 'Drip campaign could not start. Check that it is running and has a saved flow.',
        ], $started ? 200 : 422);
    }
}
