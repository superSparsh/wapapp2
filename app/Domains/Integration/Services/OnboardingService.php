<?php

declare(strict_types=1);

namespace App\Domains\Integration\Services;

use App\Domains\WhatsApp\Services\AlibabaCamsClient;
use App\Enums\RecordStatus;
use App\Models\IsvTermsAcceptance;
use App\Models\User;
use App\Models\WabaAccount;
use App\Models\WhatsappLine;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class OnboardingService
{
    public function __construct(
        private readonly AlibabaCamsClient $camsClient,
        private readonly LineProfileService $lineProfileService,
    ) {}

    public function isComplete(): bool
    {
        if (WabaAccount::query()->where('is_registered', true)->exists()) {
            return true;
        }

        return WhatsappLine::query()
            ->where(function ($query): void {
                $query->whereNotNull('waba_id')->where('waba_id', '!=', '')
                    ->orWhere(function ($inner): void {
                        $inner->whereNotNull('alibaba_cust_space_id')
                            ->where('alibaba_cust_space_id', '!=', '');
                    });
            })
            ->exists();
    }

    /**
     * @return array{
     *     business_name: ?string,
     *     bm_id: ?string,
     *     website_email: ?string,
     *     use_case: ?string,
     *     business_address: ?string
     * }|null
     */
    public function existingIsvTerms(): ?array
    {
        $term = IsvTermsAcceptance::query()->latest('id')->first();
        if (! $term) {
            return null;
        }

        return [
            'business_name' => $term->business_name,
            'bm_id' => $term->bm_id,
            'website_email' => $term->website_email,
            'use_case' => $term->use_case,
            'business_address' => $term->business_address,
        ];
    }

    /**
     * @param  array{
     *     business_name: string,
     *     bm_id: string,
     *     website_email: string,
     *     use_case: string,
     *     business_address: string
     * }  $data
     * @return array{term: IsvTermsAcceptance, app_id: ?string}
     */
    public function saveIsvTerms(array $data): array
    {
        $existing = IsvTermsAcceptance::query()->latest('id')->first();

        $emailQuery = IsvTermsAcceptance::query()
            ->where('website_email', $data['website_email']);
        if ($existing) {
            $emailQuery->where('id', '!=', $existing->id);
        }
        if ($emailQuery->exists()) {
            throw ValidationException::withMessages([
                'website_email' => ['The website email has already been taken.'],
            ]);
        }

        $term = $existing ?? new IsvTermsAcceptance([
            'country_code' => 'IN',
            'status' => 'Unverified',
        ]);

        $term->fill([
            'business_name' => $data['business_name'],
            'bm_id' => $data['bm_id'],
            'website_email' => $data['website_email'],
            'use_case' => $data['use_case'],
            'business_address' => $data['business_address'],
        ]);

        if (! $term->status) {
            $term->status = 'Unverified';
        }
        if (! $term->country_code) {
            $term->country_code = 'IN';
        }

        $term->save();

        return [
            'term' => $term->fresh() ?? $term,
            'app_id' => $this->camsClient->isvGetAppId(),
        ];
    }

    /**
     * Bind Meta WABA via CAMS after Embedded Signup finishes.
     *
     * @throws RuntimeException
     */
    public function completeEmbeddedSignup(string $wabaId, ?string $phoneNumberId, ?User $user): WabaAccount
    {
        $wabaId = trim($wabaId);
        if ($wabaId === '') {
            throw new RuntimeException('WABA ID is required.');
        }

        if (! $this->camsClient->isConfigured()) {
            throw new RuntimeException('WhatsApp provider (Alibaba CAMS) is not configured.');
        }

        $account = WabaAccount::query()->where('waba_id', $wabaId)->first()
            ?? new WabaAccount(['waba_id' => $wabaId]);

        if ($user) {
            $account->user_id = $user->id;
        }

        $account->waba_id = $wabaId;
        $account->save();

        $response = $this->camsClient->chatappBindWaba([
            'WabaId' => $wabaId,
        ]);

        $json = $response->json() ?? [];
        $code = Arr::get($json, 'Code') ?? Arr::get($json, 'code');
        $codeOk = $code === null || strtoupper((string) $code) === 'OK';

        $account->waba_response = is_array($json) ? $json : ['raw' => $response->body()];

        if (! $response->successful() || ! $codeOk) {
            $account->save();
            $message = (string) (Arr::get($json, 'Message') ?? Arr::get($json, 'message') ?? $response->body());

            throw new RuntimeException(
                $message !== ''
                    ? $message
                    : 'It seems that something went wrong. Please try again later.'
            );
        }

        $custSpaceId = (string) (
            Arr::get($json, 'Data.CustSpaceId')
            ?? Arr::get($json, 'Data.custSpaceId')
            ?? Arr::get($json, 'data.CustSpaceId')
            ?? Arr::get($json, 'data.custSpaceId')
            ?? ''
        );

        if ($custSpaceId !== '') {
            $account->alibaba_cust_space_id = $custSpaceId;
        }

        $account->is_registered = true;
        $metadata = is_array($account->metadata) ? $account->metadata : [];
        if ($phoneNumberId) {
            $metadata['phone_number_id'] = $phoneNumberId;
        }
        $account->metadata = $metadata;
        $account->save();

        IsvTermsAcceptance::query()->update(['status' => 'Verified']);
        if ($custSpaceId !== '') {
            IsvTermsAcceptance::query()->update(['cus_space_id' => $custSpaceId]);
        }

        $this->attachWabaToDefaultLine($wabaId, $custSpaceId, $phoneNumberId);

        if ($custSpaceId !== '') {
            try {
                $line = WhatsappLine::query()
                    ->where('waba_id', $wabaId)
                    ->orderByDesc('is_default')
                    ->first()
                    ?? $this->lineProfileService->defaultLine();

                if ($line) {
                    $this->lineProfileService->syncFromProvider($line);
                }
            } catch (Throwable $e) {
                Log::warning('Onboarding post-bind sync failed', [
                    'waba_id' => $wabaId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $account->fresh() ?? $account;
    }

    private function attachWabaToDefaultLine(string $wabaId, string $custSpaceId, ?string $phoneNumberId): void
    {
        $line = WhatsappLine::query()->orderByDesc('is_default')->first();

        if ($line) {
            $attributes = ['waba_id' => $wabaId];
            if ($custSpaceId !== '') {
                $attributes['alibaba_cust_space_id'] = $custSpaceId;
            }
            if ($phoneNumberId) {
                $attributes['alibaba_phone_number_id'] = $phoneNumberId;
            }
            $line->forceFill($attributes)->save();

            return;
        }

        if ($custSpaceId === '') {
            return;
        }

        // Placeholder until ChatappSyncPhoneNumber fills real numbers.
        $placeholder = 'pending'.substr(preg_replace('/\D+/', '', $wabaId) ?? $wabaId, 0, 12);
        WhatsappLine::query()->create([
            'phone' => $placeholder,
            'display_name' => 'WhatsApp Business',
            'waba_id' => $wabaId,
            'alibaba_cust_space_id' => $custSpaceId,
            'alibaba_phone_number_id' => $phoneNumberId,
            'status' => RecordStatus::Active,
            'is_default' => true,
        ]);
    }
}
