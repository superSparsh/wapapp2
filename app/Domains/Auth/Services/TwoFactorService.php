<?php

declare(strict_types=1);

namespace App\Domains\Auth\Services;

use App\Contracts\TwoFactorAuthenticatable;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /** @return array<int, string> */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return Collection::times($count, fn () => Str::upper(Str::random(4).'-'.Str::random(4)))->all();
    }

    public function qrCodeSvg(Authenticatable $user, string $secret): string
    {
        $email = $user->email ?? 'user@wapapp.test';
        $company = config('app.name', 'WapApp');
        $otpauth = $this->google2fa->getQRCodeUrl($company, $email, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(192),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($otpauth);
    }

    public function verifySetupCode(Authenticatable $user, string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, preg_replace('/\s+/', '', $code));
    }

    public function enable(
        TwoFactorAuthenticatable $user,
        string $secret,
        string $code,
        ?array $recoveryCodes = null,
    ): array {
        if (! $this->verifySetupCode($user, $secret, $code)) {
            throw new \InvalidArgumentException('Invalid authentication code.');
        }

        $recoveryCodes ??= $this->generateRecoveryCodes();

        $user->setTwoFactorSecret($secret);
        $user->setRecoveryCodes($recoveryCodes);
        $user->two_factor_confirmed_at = now();
        $user->save();

        return $recoveryCodes;
    }

    public function disableWithVerification(
        TwoFactorAuthenticatable $user,
        string $password,
        string $code,
    ): void {
        if (! Hash::check($password, $user->getAuthPassword())) {
            throw new \InvalidArgumentException('The password is incorrect.');
        }

        if (! $this->verifyChallenge($user, $code)) {
            throw new \InvalidArgumentException('Invalid authentication code.');
        }

        $this->disable($user);
    }

    /** @return array<int, string> */
    public function regenerateRecoveryCodes(TwoFactorAuthenticatable $user, string $code): array
    {
        if (! $user->hasTwoFactorEnabled()) {
            throw new \InvalidArgumentException('Two-factor authentication is not enabled.');
        }

        if (! $this->google2fa->verifyKey($user->twoFactorSecret(), preg_replace('/\s+/', '', $code))) {
            throw new \InvalidArgumentException('Invalid authentication code.');
        }

        $recoveryCodes = $this->generateRecoveryCodes();
        $user->setRecoveryCodes($recoveryCodes);
        $user->save();

        return $recoveryCodes;
    }

    public function disable(TwoFactorAuthenticatable $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function verifyChallenge(TwoFactorAuthenticatable $user, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        if ($this->google2fa->verifyKey($user->twoFactorSecret(), $code)) {
            return true;
        }

        return $user->consumeRecoveryCode($code);
    }
}
