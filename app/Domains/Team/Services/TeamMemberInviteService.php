<?php

declare(strict_types=1);

namespace App\Domains\Team\Services;

use App\Domains\Team\Mail\TeamMemberInviteMail;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TeamMemberInviteService
{
    public function sendInvite(TeamMember $member, User $owner): void
    {
        $email = strtolower((string) $member->email);
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            ['token' => Hash::make($token), 'created_at' => now()],
        );

        $setPasswordUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $email,
        ], false));

        $businessName = trim((string) (tenant('company_name') ?? tenant('name') ?? $owner->name ?? 'WapApp'));

        Mail::to($email)->send(new TeamMemberInviteMail(
            member: $member,
            owner: $owner,
            businessName: $businessName !== '' ? $businessName : 'WapApp',
            setPasswordUrl: $setPasswordUrl,
            loginUrl: url(route('login', absolute: false)),
        ));
    }
}
