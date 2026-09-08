<?php

declare(strict_types=1);

namespace App\Domains\Account\Services;

use App\Domains\Account\DTOs\ProfileData;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileService
{
    public function forUser(User $user): ProfileData
    {
        [$firstName, $lastName] = $this->splitName($user);
        $tenant = $this->resolveTenant();

        return new ProfileData(
            id: $user->id,
            firstName: $firstName,
            lastName: $lastName,
            email: $user->email,
            phone: $user->phone,
            avatarUrl: $this->avatarUrl($user),
            timezone: $tenant?->timezone ?? 'Asia/Kolkata',
            countryCode: $tenant?->country_code ?? 'IN',
            locale: $tenant?->locale ?? 'en',
            canEditTenantPreferences: $user->role === UserRole::Owner,
        );
    }

    /** @param  array<string, mixed>  $data */
    public function update(User $user, array $data, ?UploadedFile $avatar = null, bool $removeAvatar = false): void
    {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));

        $user->forceFill([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim($firstName.' '.$lastName),
        ]);

        if (! empty($data['email']) && strtolower((string) $data['email']) !== strtolower($user->email)) {
            $user->email = strtolower((string) $data['email']);
            $user->email_verified_at = null;
        }

        if (! empty($data['password'])) {
            $user->password = Hash::make((string) $data['password']);
        }

        if ($removeAvatar) {
            $this->deleteAvatar($user);
        } elseif ($avatar !== null) {
            $this->storeAvatar($user, $avatar);
        }

        $user->save();

        if ($user->role === UserRole::Owner) {
            $this->updateTenantPreferences($data);
            $this->syncOwnerEmailOnCentral($user);
        }

        app(ActivityLogService::class)->log('profile.updated');
    }

    /** @param  array<string, mixed>  $data */
    private function updateTenantPreferences(array $data): void
    {
        $tenantId = tenant('id');

        if (! is_string($tenantId) || $tenantId === '') {
            return;
        }

        tenancy()->central(function () use ($tenantId, $data): void {
            Tenant::query()->whereKey($tenantId)->update([
                'timezone' => $data['timezone'] ?? 'Asia/Kolkata',
                'locale' => $data['locale'] ?? 'en',
                'country_code' => $data['country_code'] ?? 'IN',
            ]);
        });
    }

    private function syncOwnerEmailOnCentral(User $user): void
    {
        $tenantId = tenant('id');

        if (! is_string($tenantId) || $tenantId === '') {
            return;
        }

        tenancy()->central(function () use ($tenantId, $user): void {
            \App\Models\TenantUserAccess::query()
                ->where('tenant_id', $tenantId)
                ->where('account_type', \App\Enums\TenantUserAccountType::Owner)
                ->update(['email' => strtolower($user->email)]);
        });
    }

    private function storeAvatar(User $user, UploadedFile $avatar): void
    {
        $this->deleteAvatar($user);

        $directory = (string) config('account.avatar.directory', 'avatars');
        $filename = $user->uuid.'-'.Str::uuid().'.'.$avatar->guessExtension();
        $path = $avatar->storeAs($directory, $filename, (string) config('account.avatar.disk', 'public'));

        $user->avatar_path = $path;
    }

    private function deleteAvatar(User $user): void
    {
        if ($user->avatar_path === null) {
            return;
        }

        Storage::disk((string) config('account.avatar.disk', 'public'))->delete($user->avatar_path);
        $user->avatar_path = null;
    }

    private function avatarUrl(User $user): ?string
    {
        if ($user->avatar_path === null) {
            return null;
        }

        return Storage::disk((string) config('account.avatar.disk', 'public'))->url($user->avatar_path);
    }

    /** @return array{0: string, 1: string} */
    private function splitName(User $user): array
    {
        if (filled($user->first_name) || filled($user->last_name)) {
            return [
                (string) ($user->first_name ?? ''),
                (string) ($user->last_name ?? ''),
            ];
        }

        $parts = preg_split('/\s+/', trim((string) $user->name), 2) ?: [];

        return [
            $parts[0] ?? '',
            $parts[1] ?? '',
        ];
    }

    private function resolveTenant(): ?Tenant
    {
        $tenantId = tenant('id');

        if (! is_string($tenantId) || $tenantId === '') {
            return null;
        }

        return tenancy()->central(fn () => Tenant::query()->find($tenantId));
    }
}
