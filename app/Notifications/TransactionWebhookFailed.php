<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

class TransactionWebhookFailed extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly array $payload,
        public readonly ?Throwable $exception = null,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $description = (string) ($this->payload['description'] ?? 'Unknown transaction');
        $accountName = (string) ($this->payload['account_name'] ?? 'Unknown account');
        $amount = (string) ($this->payload['amount'] ?? 'Unknown amount');
        $date = (string) ($this->payload['transaction_date'] ?? 'Unknown date');

        return (new MailMessage)
            ->subject('Transaction webhook failed')
            ->greeting("Hi {$notifiable->name},")
            ->line('A transaction webhook job failed before the transaction could be created.')
            ->line("Description: {$description}")
            ->line("Account: {$accountName}")
            ->line("Amount: {$amount}")
            ->line("Date: {$date}")
            ->line('Error: '.($this->exception?->getMessage() ?: 'Unknown error'))
            ->line('Review your queue logs before retrying the webhook payload.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'transaction_webhook_failed',
            'payload' => $this->payload,
            'error' => $this->exception?->getMessage(),
        ];
    }
}
