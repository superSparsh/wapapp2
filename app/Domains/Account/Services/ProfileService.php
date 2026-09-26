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
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $disk = (string) config('account.avatar.disk', 'public');
        Storage::disk($disk)->makeDirectory($directory);

        $extension = $avatar->guessExtension() ?: $avatar->getClientOriginalExtension() ?: 'jpg';
        $filename = $user->uuid.'-'.Str::uuid().'.'.$extension;
        $path = $avatar->storeAs($directory, $filename, $disk);

        if ($path === false || $path === '') {
            throw new \RuntimeException('Failed to store profile avatar.');
        }

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

    /**
     * Tenant public disk is not served by /storage/... (central symlink), so
     * avatars must use the auth-gated stream route.
     */
    private function avatarUrl(User $user): ?string
    {
        if ($user->avatar_path === null || $user->avatar_path === '') {
            return null;
        }

        return $this->avatarPreviewUrl($user->avatar_path);
    }

    public function avatarPreviewUrl(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return route('profile.avatars.show', ['path' => $path]);
    }

    public function streamAvatar(string $path): StreamedResponse
    {
        $path = $this->normalizeAvatarPath($path);
        $disk = Storage::disk((string) config('account.avatar.disk', 'public'));

        if (! $disk->exists($path)) {
            abort(404);
        }

        $mime = (string) ($disk->mimeType($path) ?: 'application/octet-stream');

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            if (! is_resource($stream)) {
                return;
            }

            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    private function normalizeAvatarPath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $path = preg_replace('#\.\./#', '', $path) ?? $path;

        $directory = trim((string) config('account.avatar.directory', 'avatars'), '/');
        if ($directory === '' || ! str_starts_with($path, $directory.'/')) {
            abort(404);
        }

        return $path;
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
