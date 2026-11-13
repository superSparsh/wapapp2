<?php

declare(strict_types=1);

namespace App\Domains\Team\Mail;

use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamMemberInviteMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly TeamMember $member,
        public readonly User $owner,
        public readonly string $businessName,
        public readonly string $setPasswordUrl,
        public readonly string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        $business = $this->businessName !== '' ? $this->businessName : 'your team';

        return new Envelope(
            subject: "You're invited to join {$business} on WapApp",
        );
    }

    public function content(): Content
    {
        $ownerName = trim((string) ($this->owner->name ?? ''))
            ?: trim(($this->owner->first_name ?? '').' '.($this->owner->last_name ?? ''))
            ?: 'Your account owner';

        return new Content(
            view: 'emails.team.invite',
            with: [
                'memberName' => $this->member->displayName(),
                'memberEmail' => $this->member->email,
                'ownerName' => $ownerName,
                'businessName' => $this->businessName !== '' ? $this->businessName : 'WapApp',
                'roleLabel' => $this->member->role->label(),
                'setPasswordUrl' => $this->setPasswordUrl,
                'loginUrl' => $this->loginUrl,
            ],
        );
    }
}
