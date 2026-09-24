<?php

declare(strict_types=1);

use App\Modules\Support\Notifications\Messages\BrandedMailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as Notifications;
use Symfony\Component\Mime\Email;

/**
 * Unit tests for \App\Modules\Support\Notifications\Messages\BrandedMailMessage.
 */

/**
 * Send the given message to a throwaway recipient through
 * the mail channel and return the email that was sent.
 */
function sendBrandedMailMessage(BrandedMailMessage $message): Email
{
    Notifications::route('mail', 'someone@example.com')->notifyNow(new class($message) extends Notification
    {
        /**
         * Create a notification that sends the given message.
         */
        public function __construct(public BrandedMailMessage $message) {}

        /**
         * Deliver the notification by mail.
         *
         * @return array<int, string>
         */
        public function via(object $notifiable): array
        {
            return ['mail'];
        }

        /**
         * Get the message under test.
         */
        public function toMail(object $notifiable): BrandedMailMessage
        {
            return $this->message;
        }
    });

    /** @var \Illuminate\Mail\Transport\ArrayTransport $transport */
    $transport = Mail::mailer('array')->getSymfonyTransport();

    /** @var \Symfony\Component\Mailer\SentMessage $sent */
    $sent = $transport->messages()->sole();

    /** @var \Symfony\Component\Mime\Email $email */
    $email = $sent->getOriginalMessage();

    return $email;
}

it('renders no footer links when none are supplied', function (): void {
    $email = sendBrandedMailMessage((new BrandedMailMessage)->line('Hi.'));

    expect($email->getHtmlBody())->toBeString()
        ->not->toContain('Manage notifications', 'Unsubscribe', 'footer-links')
        ->and($email->getTextBody())->toBeString()
        ->not->toContain('Manage notifications', 'Unsubscribe')
        ->and($email->getHeaders()->has('List-Unsubscribe'))->toBeFalse();
});

it('renders only the footer links that are supplied', function (?string $manageUrl, ?string $unsubscribeUrl): void {
    $message = (new BrandedMailMessage)->line('Hi.');
    if ($manageUrl !== null) {
        $message->manageNotificationsUsing($manageUrl);
    }

    if ($unsubscribeUrl !== null) {
        $message->unsubscribeUsing($unsubscribeUrl);
    }

    $email = sendBrandedMailMessage($message);

    $html = (string) $email->getHtmlBody();
    $text = (string) $email->getTextBody();
    expect(str_contains($html, 'href="https://contamos.test/notifications"'))->toBe($manageUrl !== null)
        ->and(str_contains($html, 'Manage notifications</a>'))->toBe($manageUrl !== null)
        ->and(str_contains($text, 'Manage notifications: https://contamos.test/notifications'))->toBe($manageUrl !== null)
        ->and(str_contains($html, 'href="https://contamos.test/unsubscribe"'))->toBe($unsubscribeUrl !== null)
        ->and(str_contains($html, 'Unsubscribe</a>'))->toBe($unsubscribeUrl !== null)
        ->and(str_contains($text, 'Unsubscribe: https://contamos.test/unsubscribe'))->toBe($unsubscribeUrl !== null);
})->with([
    'manage notifications only' => ['https://contamos.test/notifications', null],
    'unsubscribe only' => [null, 'https://contamos.test/unsubscribe'],
    'both' => ['https://contamos.test/notifications', 'https://contamos.test/unsubscribe'],
]);

it('advertises the unsubscribe link in the List-Unsubscribe header', function (): void {
    $email = sendBrandedMailMessage(
        (new BrandedMailMessage)->line('Hi.')->unsubscribeUsing('https://contamos.test/unsubscribe'),
    );

    expect($email->getHeaders()->get('List-Unsubscribe')?->getBodyAsString())
        ->toBe('<https://contamos.test/unsubscribe>');
});
