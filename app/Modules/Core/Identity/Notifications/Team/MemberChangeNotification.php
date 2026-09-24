<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Notifications\Team;

use App\Modules\Core\Identity\Enums\Team\NotificationType;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Notifications\Messages\BrandedMailMessage;

/**
 * An email telling the Owner and Admins that the people on
 * the team changed, with a link to the member listing.
 */
abstract class MemberChangeNotification extends TeamNotification
{
    /**
     * Get the subject of the email.
     */
    abstract protected function subject(): string;

    /**
     * Get the sentence that says what changed.
     */
    abstract protected function sentence(): string;

    /**
     * Get the switch that decides whether a member receives
     * the email.
     */
    public function type(): NotificationType
    {
        return NotificationType::MemberChanges;
    }

    /**
     * Write the subject and the body of the email onto the
     * given message.
     */
    protected function compose(BrandedMailMessage $message, User $notifiable): BrandedMailMessage
    {
        return $message
            ->subject($this->subject())
            ->line($this->sentence())
            ->action(trans('View members'), route('identity.teams.members.index', ['team' => $this->team]));
    }
}
