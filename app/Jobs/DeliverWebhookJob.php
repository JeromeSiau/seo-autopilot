<?php

namespace App\Jobs;

use App\Models\WebhookEndpoint;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public int $backoff = 60;

    public function __construct(
        public readonly WebhookEndpoint $endpoint,
        public readonly string $eventName,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $attemptNumber = $this->attempts();
        $maxAttempts = $this->tries;
        $body = [
            'event' => $this->eventName,
            'timestamp' => now()->toIso8601String(),
            'data' => $this->payload,
        ];

        $signature = $this->endpoint->secret
            ? hash_hmac('sha256', json_encode($body, JSON_UNESCAPED_SLASHES), $this->endpoint->secret)
            : null;

        $delivery = $this->endpoint->deliveries()->create([
            'event_name' => $this->eventName,
            'payload' => $body,
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempt_number' => $attemptNumber,
            'max_attempts' => $maxAttempts,
        ]);

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asJson()
                ->withHeaders(array_filter([
                    'X-SEO-Autopilot-Event' => $this->eventName,
                    'X-SEO-Autopilot-Signature' => $signature,
                ]))
                ->post($this->endpoint->url, $body);

            $delivery->update([
                'status' => $response->successful() ? WebhookDelivery::STATUS_SUCCESS : $this->failedStatus($attemptNumber, $maxAttempts),
                'response_code' => $response->status(),
                'response_body' => $response->body(),
                'attempted_at' => now(),
                'next_retry_at' => $response->successful() ? null : $this->nextRetryAt($attemptNumber, $maxAttempts),
            ]);

            $this->endpoint->update([
                'last_delivered_at' => now(),
                'last_error' => $response->successful() ? null : ('HTTP ' . $response->status()),
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException("Webhook delivery failed with status {$response->status()}");
            }
        } catch (\Throwable $exception) {
            $delivery->update([
                'status' => $this->failedStatus($attemptNumber, $maxAttempts),
                'error_message' => $exception->getMessage(),
                'attempted_at' => now(),
                'next_retry_at' => $this->nextRetryAt($attemptNumber, $maxAttempts),
            ]);

            $this->endpoint->update([
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    protected function failedStatus(int $attemptNumber, int $maxAttempts): string
    {
        return $attemptNumber < $maxAttempts
            ? WebhookDelivery::STATUS_RETRYING
            : WebhookDelivery::STATUS_FAILED;
    }

    protected function nextRetryAt(int $attemptNumber, int $maxAttempts): ?\Illuminate\Support\Carbon
    {
        if ($attemptNumber >= $maxAttempts) {
            return null;
        }

        $backoff = is_array($this->backoff)
            ? ($this->backoff[$attemptNumber - 1] ?? end($this->backoff) ?: 60)
            : $this->backoff;

        return now()->addSeconds((int) $backoff);
    }
}
