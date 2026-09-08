<?php

declare(strict_types=1);

namespace App\Domains\Integration\Services;

use App\Domains\Account\Services\ActivityLogService;
use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Enums\RecordStatus;
use App\Models\WhatsappLine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LineProfileService
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly AlibabaCamsClient $camsClient,
    ) {}

    /** @return Collection<int, WhatsappLine> */
    public function lines(): Collection
    {
        return WhatsappLine::query()->orderByDesc('is_default')->get();
    }

    public function defaultLine(): ?WhatsappLine
    {
        return WhatsappLine::query()->where('is_default', true)->first()
            ?? WhatsappLine::query()->first();
    }

    public function businessDetails(): array
    {
        $line = $this->defaultLine();
        $profile = $line?->profile ?? [];
        $metadata = is_array($line?->metadata) ? $line->metadata : [];

        return [
            'line' => $line,
            'waba_id' => $line?->waba_id,
            'business_name' => $metadata['business_name']
                ?? $line?->display_name
                ?? tenant('company_name'),
            'phone' => $line?->phone,
            'verified' => filled($line?->waba_id),
            'quality_rating' => $line?->quality_rating,
            'messaging_limit_tier' => $line?->messaging_limit_tier,
            'profile' => $profile,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(WhatsappLine $line, array $data, ?UploadedFile $logo = null, bool $removeLogo = false): WhatsappLine
    {
        $profile = $line->profile ?? [];

        $profile['email'] = $data['email'] ?? ($profile['email'] ?? null);
        $profile['website'] = $data['website'] ?? ($profile['website'] ?? null);
        $profile['address'] = $data['address'] ?? ($profile['address'] ?? null);
        $profile['description'] = $data['description'] ?? ($profile['description'] ?? null);
        $profile['about'] = $data['about'] ?? ($profile['about'] ?? null);

        if ($removeLogo && isset($profile['logo_path'])) {
            Storage::disk('public')->delete($profile['logo_path']);
            unset($profile['logo_path']);
        }

        if ($logo) {
            if (isset($profile['logo_path'])) {
                Storage::disk('public')->delete($profile['logo_path']);
            }

            $profile['logo_path'] = $logo->store('line-profiles/'.$line->id, 'public');
        }

        $line->update(['profile' => $profile]);
        $line = $line->fresh() ?? $line;

        $this->pushProfileToProvider($line);

        $this->activityLogService->log('integration.profile.updated', [
            'subject_type' => WhatsappLine::class,
            'subject_id' => $line->id,
        ]);

        return $line->fresh() ?? $line;
    }

    /**
     * Sync phone quality / limits and WABA business info from Alibaba CAMS.
     *
     * @throws RuntimeException when CAMS is not configured or the sync fails
     */
    public function syncFromProvider(WhatsappLine $line): WhatsappLine
    {
        if (! $this->camsClient->isConfigured()) {
            throw new RuntimeException('WhatsApp provider (Alibaba CAMS) is not configured.');
        }

        $wabaId = trim((string) ($line->waba_id ?? ''));
        if ($wabaId === '') {
            throw new RuntimeException('No WABA ID on the WhatsApp line. Connect WhatsApp Business first.');
        }

        $custSpaceId = $this->refreshCustSpaceId($line, $wabaId);
        $phoneNumbers = $this->fetchSyncedPhoneNumbers($custSpaceId);
        $this->upsertLinesFromPhoneNumbers($phoneNumbers, $wabaId, $custSpaceId);

        foreach (WhatsappLine::query()->where('waba_id', $wabaId)->get() as $syncedLine) {
            $this->registerPhoneWebhook($custSpaceId, (string) $syncedLine->phone);
        }

        $this->applyBusinessInfo($custSpaceId, $wabaId);

        $this->activityLogService->log('integration.sync.completed', [
            'subject_type' => WhatsappLine::class,
            'subject_id' => $line->id,
        ]);

        return $line->fresh() ?? $line;
    }

    /**
     * Register an additional Chatapp phone number via CAMS and upsert local WhatsappLine.
     *
     * @param  array{country_code: string, phone_number: string, verified_name: string}  $data
     *
     * @throws RuntimeException
     */
    public function addChatappPhoneNumber(array $data): WhatsappLine
    {
        if (! $this->camsClient->isConfigured()) {
            throw new RuntimeException('WhatsApp provider (Alibaba CAMS) is not configured.');
        }

        $defaultLine = $this->defaultLine();
        $custSpaceId = trim((string) ($defaultLine?->alibaba_cust_space_id ?? ''));
        $wabaId = trim((string) ($defaultLine?->waba_id ?? ''));

        if ($custSpaceId === '' || $wabaId === '') {
            throw new RuntimeException('WhatsApp must be connected first (customer space is missing). Finish onboarding, then add more numbers.');
        }

        $countryCode = preg_replace('/\D+/', '', (string) $data['country_code']) ?? '';
        $nationalPhone = preg_replace('/\D+/', '', (string) $data['phone_number']) ?? '';
        $verifiedName = trim((string) $data['verified_name']);

        if ($countryCode === '' || $nationalPhone === '' || $verifiedName === '') {
            throw new RuntimeException('Country code, phone number, and display name are required.');
        }

        $response = $this->camsClient->addChatappPhoneNumber([
            'CustSpaceId' => $custSpaceId,
            'PhoneNumber' => $nationalPhone,
            'Cc' => $countryCode,
            'VerifiedName' => $verifiedName,
        ]);

        $json = $response->json();
        $code = Arr::get($json, 'Code') ?? Arr::get($json, 'code') ?? Arr::get($json, 'body.Code');
        if (! $response->successful() || ($code !== null && strtoupper((string) $code) !== 'OK')) {
            $message = (string) (Arr::get($json, 'Message') ?? Arr::get($json, 'message') ?? '');
            if ($message === '') {
                $message = $response->body() !== ''
                    ? $response->body()
                    : 'Alibaba CAMS rejected AddChatappPhoneNumber.';
            }

            throw new RuntimeException($message);
        }

        $e164 = $countryCode.$nationalPhone;
        $line = WhatsappLine::query()->where('phone', $e164)->first()
            ?? WhatsappLine::query()->where('phone', '+'.$e164)->first()
            ?? WhatsappLine::query()->where('phone', $nationalPhone)->first();

        $attributes = [
            'phone' => $e164,
            'display_name' => $verifiedName,
            'waba_id' => $wabaId,
            'alibaba_cust_space_id' => $custSpaceId,
            'status' => RecordStatus::Active,
            'metadata' => array_merge(is_array($line?->metadata) ? $line->metadata : [], [
                'provider_status' => 'pending_verification',
                'country_code' => $countryCode,
                'national_phone' => $nationalPhone,
            ]),
        ];

        if ($line) {
            $line->update($attributes);
            $line = $line->fresh() ?? $line;
        } else {
            $hasDefault = WhatsappLine::query()->where('is_default', true)->exists();
            $line = WhatsappLine::query()->create(array_merge($attributes, [
                'is_default' => ! $hasDefault,
            ]));
        }

        $this->activityLogService->log('integration.phone.added', [
            'subject_type' => WhatsappLine::class,
            'subject_id' => $line->id,
            'phone' => $e164,
        ]);

        return $line;
    }

    /**
     * Pull remote profile into local JSON (used on edit when available).
     */
    public function pullProfileFromProvider(WhatsappLine $line): WhatsappLine
    {
        if (! $this->camsClient->isConfigured() || blank($line->alibaba_cust_space_id) || blank($line->phone)) {
            return $line;
        }

        $response = $this->camsClient->queryPhoneBusinessProfile([
            'CustSpaceId' => (string) $line->alibaba_cust_space_id,
            'PhoneNumber' => $this->digitsPhone((string) $line->phone),
        ]);

        if (! $response->successful()) {
            return $line;
        }

        $data = Arr::get($response->json(), 'Data', Arr::get($response->json(), 'data', []));
        if (! is_array($data) || $data === []) {
            return $line;
        }

        $profile = $line->profile ?? [];
        $profile['email'] = Arr::get($data, 'email') ?? Arr::get($data, 'Email') ?? ($profile['email'] ?? null);
        $profile['address'] = Arr::get($data, 'address') ?? Arr::get($data, 'Address') ?? ($profile['address'] ?? null);
        $description = Arr::get($data, 'description') ?? Arr::get($data, 'Description');
        if (is_string($description)) {
            $profile['description'] = $description;
            $profile['about'] = $description;
        }
        $websites = Arr::get($data, 'websites') ?? Arr::get($data, 'Websites');
        if (is_array($websites) && isset($websites[0])) {
            $profile['website'] = is_string($websites[0]) ? $websites[0] : ($profile['website'] ?? null);
        } elseif (is_string($websites)) {
            $profile['website'] = $websites;
        }

        $line->update(['profile' => $profile]);

        return $line->fresh() ?? $line;
    }

    private function pushProfileToProvider(WhatsappLine $line): void
    {
        if (! $this->camsClient->isConfigured()) {
            Log::warning('CAMS not configured; skipped ModifyPhoneBusinessProfile.', ['line_id' => $line->id]);

            return;
        }

        $custSpaceId = trim((string) ($line->alibaba_cust_space_id ?? ''));
        $phone = $this->digitsPhone((string) $line->phone);
        if ($custSpaceId === '' || $phone === '') {
            Log::warning('Missing CustSpaceId/phone; skipped ModifyPhoneBusinessProfile.', ['line_id' => $line->id]);

            return;
        }

        $profile = is_array($line->profile) ? $line->profile : [];
        $description = (string) ($profile['description'] ?? $profile['about'] ?? '');
        $website = (string) ($profile['website'] ?? '');
        $logoPath = (string) ($profile['logo_path'] ?? '');
        $pictureUrl = $logoPath !== '' ? Storage::disk('public')->url($logoPath) : null;

        $params = [
            'CustSpaceId' => $custSpaceId,
            'PhoneNumber' => $phone,
            'Address' => (string) ($profile['address'] ?? ''),
            'Description' => $description,
            'Email' => (string) ($profile['email'] ?? ''),
            'ProfilePictureUrl' => $pictureUrl,
        ];

        if ($website !== '') {
            $params['Websites'] = [$website];
        }

        $response = $this->camsClient->modifyPhoneBusinessProfile($params);
        if (! $response->successful()) {
            Log::warning('ModifyPhoneBusinessProfile failed', [
                'line_id' => $line->id,
                'body' => $response->body(),
            ]);
        }
    }

    private function refreshCustSpaceId(WhatsappLine $line, string $wabaId): string
    {
        $existing = trim((string) ($line->alibaba_cust_space_id ?? ''));

        $response = $this->camsClient->chatappBindWaba([
            'WabaId' => $wabaId,
        ]);

        if (! $response->successful()) {
            if ($existing !== '') {
                return $existing;
            }

            throw new RuntimeException('Failed to bind WABA / refresh CustSpaceId: '.$response->body());
        }

        $json = $response->json();
        $custSpaceId = (string) (
            Arr::get($json, 'Data.CustSpaceId')
            ?? Arr::get($json, 'Data.custSpaceId')
            ?? Arr::get($json, 'data.custSpaceId')
            ?? Arr::get($json, 'data.CustSpaceId')
            ?? ''
        );

        if ($custSpaceId === '') {
            if ($existing !== '') {
                return $existing;
            }

            throw new RuntimeException('CAMS bind WABA did not return CustSpaceId.');
        }

        WhatsappLine::query()
            ->where('waba_id', $wabaId)
            ->update(['alibaba_cust_space_id' => $custSpaceId]);

        return $custSpaceId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchSyncedPhoneNumbers(string $custSpaceId): array
    {
        $response = $this->camsClient->chatappSyncPhoneNumber([
            'CustSpaceId' => $custSpaceId,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('ChatappSyncPhoneNumber failed: '.$response->body());
        }

        $json = $response->json();
        $phones = Arr::get($json, 'PhoneNumbers')
            ?? Arr::get($json, 'phoneNumbers')
            ?? Arr::get($json, 'Data.PhoneNumbers')
            ?? Arr::get($json, 'Data.phoneNumbers')
            ?? [];

        if (! is_array($phones)) {
            throw new RuntimeException('ChatappSyncPhoneNumber returned no phone numbers.');
        }

        /** @var list<array<string, mixed>> $normalized */
        $normalized = [];
        foreach ($phones as $phone) {
            if (is_array($phone)) {
                $normalized[] = $phone;
            } elseif (is_object($phone)) {
                $normalized[] = (array) $phone;
            }
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $phoneNumbers
     */
    private function upsertLinesFromPhoneNumbers(array $phoneNumbers, string $wabaId, string $custSpaceId): void
    {
        foreach ($phoneNumbers as $item) {
            $phone = $this->digitsPhone((string) (
                Arr::get($item, 'phoneNumber')
                ?? Arr::get($item, 'PhoneNumber')
                ?? ''
            ));
            if ($phone === '') {
                continue;
            }

            $quality = Arr::get($item, 'qualityRating') ?? Arr::get($item, 'QualityRating');
            $tier = Arr::get($item, 'messagingLimitTier') ?? Arr::get($item, 'MessagingLimitTier');
            $verifiedName = Arr::get($item, 'verifiedName') ?? Arr::get($item, 'VerifiedName');
            $status = Arr::get($item, 'status') ?? Arr::get($item, 'Status');

            $line = WhatsappLine::query()->where('phone', $phone)->first()
                ?? WhatsappLine::query()->where('phone', '+'.$phone)->first();

            $attributes = [
                'waba_id' => $wabaId,
                'alibaba_cust_space_id' => $custSpaceId,
                'quality_rating' => is_scalar($quality) ? (string) $quality : null,
                'messaging_limit_tier' => is_scalar($tier) ? (string) $tier : null,
                'status' => RecordStatus::Active,
            ];

            if (is_string($verifiedName) && $verifiedName !== '') {
                $attributes['display_name'] = $verifiedName;
            }

            $metadata = is_array($line?->metadata) ? $line->metadata : [];
            if (is_scalar($status)) {
                $metadata['provider_status'] = (string) $status;
            }
            $attributes['metadata'] = $metadata;

            if ($line) {
                $line->update($attributes);
            } else {
                $hasDefault = WhatsappLine::query()->where('is_default', true)->exists();
                WhatsappLine::query()->create(array_merge($attributes, [
                    'phone' => $phone,
                    'display_name' => is_string($verifiedName) && $verifiedName !== '' ? $verifiedName : $phone,
                    'is_default' => ! $hasDefault,
                ]));
            }
        }
    }

    private function registerPhoneWebhook(string $custSpaceId, string $phone): void
    {
        $digits = $this->digitsPhone($phone);
        if ($digits === '') {
            return;
        }

        $base = rtrim((string) config('app.url'), '/');
        $response = $this->camsClient->updatePhoneWebhook([
            'CustSpaceId' => $custSpaceId,
            'PhoneNumber' => $digits,
            'StatusCallbackUrl' => $base.'/api/v1/status-uplink/alibaba',
            'UpCallbackUrl' => $base.'/api/v1/message-uplink/alibaba',
        ]);

        if (! $response->successful()) {
            Log::warning('UpdatePhoneWebhook failed', [
                'phone' => $digits,
                'body' => $response->body(),
            ]);
        }
    }

    private function applyBusinessInfo(string $custSpaceId, string $wabaId): void
    {
        $response = $this->camsClient->queryWabaBusinessInfo([
            'CustSpaceId' => $custSpaceId,
            'WabaId' => $wabaId,
        ]);

        if (! $response->successful()) {
            Log::warning('QueryWabaBusinessInfo failed', ['body' => $response->body()]);

            return;
        }

        $data = Arr::get($response->json(), 'Data')
            ?? Arr::get($response->json(), 'data')
            ?? [];

        if (! is_array($data) || $data === []) {
            return;
        }

        $businessName = Arr::get($data, 'businessName') ?? Arr::get($data, 'BusinessName');
        $businessId = Arr::get($data, 'businessId') ?? Arr::get($data, 'BusinessId');
        $verification = Arr::get($data, 'verificationStatus') ?? Arr::get($data, 'VerificationStatus');
        $vertical = Arr::get($data, 'vertical') ?? Arr::get($data, 'Vertical');

        $default = WhatsappLine::query()->where('waba_id', $wabaId)->orderByDesc('is_default')->first();
        if (! $default) {
            return;
        }

        $metadata = is_array($default->metadata) ? $default->metadata : [];
        if (is_string($businessName) && $businessName !== '') {
            $metadata['business_name'] = $businessName;
        }
        if (is_scalar($businessId)) {
            $metadata['business_id'] = (string) $businessId;
        }
        if (is_scalar($verification)) {
            $metadata['business_verification_status'] = (string) $verification;
        }
        if (is_scalar($vertical)) {
            $metadata['vertical'] = (string) $vertical;
        }

        $default->metadata = $metadata;
        $default->save();
    }

    private function digitsPhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }
}
