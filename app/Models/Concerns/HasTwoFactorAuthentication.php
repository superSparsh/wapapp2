<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

trait HasTwoFactorAuthentication
{
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null
            && $this->two_factor_secret !== null;
    }

    public function twoFactorSecret(): ?string
    {
        if ($this->two_factor_secret === null) {
            return null;
        }

        return Crypt::decryptString($this->two_factor_secret);
    }

    public function setTwoFactorSecret(string $secret): void
    {
        $this->two_factor_secret = Crypt::encryptString($secret);
    }

    /** @return Collection<int, string> */
    public function recoveryCodes(): Collection
    {
        if ($this->two_factor_recovery_codes === null) {
            return collect();
        }

        $decoded = json_decode(Crypt::decryptString($this->two_factor_recovery_codes), true);

        return collect(is_array($decoded) ? $decoded : []);
    }

    /** @param  array<int, string>  $codes */
    public function setRecoveryCodes(array $codes): void
    {
        $this->two_factor_recovery_codes = Crypt::encryptString(json_encode(array_values($codes)));
    }

    public function verifyTwoFactorCode(string $code): bool
    {
        if (! $this->hasTwoFactorEnabled()) {
            return false;
        }

        $google2fa = app(Google2FA::class);

        return $google2fa->verifyKey($this->twoFactorSecret(), preg_replace('/\s+/', '', $code));
    }

    public function consumeRecoveryCode(string $code): bool
    {
        $normalized = strtoupper(trim($code));
        $codes = $this->recoveryCodes();

        $match = $codes->first(fn (string $stored) => hash_equals(strtoupper($stored), $normalized));

        if ($match === null) {
            return false;
        }

        $this->setRecoveryCodes($codes->reject(fn (string $existing) => hash_equals(strtoupper($existing), $normalized))->values()->all());
        $this->save();

        return true;
    }
}
