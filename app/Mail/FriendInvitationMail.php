<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FriendInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $senderName,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('You have a new friend request')
            ->view('emails.friends.invitation')
            ->with(['senderName' => $this->senderName]);
    }
}
