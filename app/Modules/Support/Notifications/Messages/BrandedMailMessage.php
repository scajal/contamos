<?php

declare(strict_types=1);

namespace App\Modules\Support\Notifications\Messages;

use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

/**
 * A mail message rendered in the Contamos layout, able to
 * offer "manage notifications" and "unsubscribe" links in
 * the footer of the email.
 */
class BrandedMailMessage extends MailMessage
{
    /**
     * Where the recipient can change which notifications
     * they receive, or null to leave the link out.
     */
    public ?string $manageNotificationsUrl = null;

    /**
     * Where the recipient can stop receiving this kind of
     * email, or null to leave the link out.
     */
    public ?string $unsubscribeUrl = null;

    /**
     * Link the footer to the page where the recipient
     * manages their notifications.
     */
    public function manageNotificationsUsing(string $url): static
    {
        $this->manageNotificationsUrl = $url;

        return $this;
    }

    /**
     * Link the footer to the page that unsubscribes the
     * recipient, and advertise it to mail clients through
     * the List-Unsubscribe header.
     */
    public function unsubscribeUsing(string $url): static
    {
        $this->unsubscribeUrl = $url;

        // Let mail clients offer their own unsubscribe button.
        return $this->withSymfonyMessage(function (Email $email) use ($url): void {
            $email->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$url.'>');
        });
    }

    /**
     * Get the data array for the mail message, including
     * the footer links.
     *
     * @return array<array-key, mixed>
     */
    public function data(): array
    {
        return [
            ...parent::data(),
            'manageNotificationsUrl' => $this->manageNotificationsUrl,
            'unsubscribeUrl' => $this->unsubscribeUrl,
        ];
    }
}
