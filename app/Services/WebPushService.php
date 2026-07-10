<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    /**
     * Send a push notification to all of a user's registered browser
     * subscriptions. Expired/gone subscriptions are pruned automatically.
     *
     * @param array $payload ['title' => ..., 'body' => ..., 'url' => ..., 'type' => ...]
     */
    public function sendToUser(int $userId, array $payload): void
    {
        $subscriptions = PushSubscription::where('user_id', $userId)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        // OpenSSL needs an explicit config on Windows/XAMPP to create the
        // ephemeral EC key used for payload encryption.
        $conf = config('webpush.openssl_conf');
        if ($conf && ! getenv('OPENSSL_CONF')) {
            putenv('OPENSSL_CONF=' . $conf);
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => config('webpush.subject'),
                'publicKey'  => config('webpush.public_key'),
                'privateKey' => config('webpush.private_key'),
            ],
        ]);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->public_key,
                    'authToken'       => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding ?: 'aes128gcm',
                ]),
                json_encode($payload)
            );
        }

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            if (! $report->isSuccess()) {
                // 404/410 → subscription is gone; drop it. Others → just log.
                if ($report->isSubscriptionExpired()) {
                    PushSubscription::where('endpoint', $endpoint)->delete();
                } else {
                    Log::warning('WebPush delivery failed', [
                        'endpoint' => $endpoint,
                        'reason'   => $report->getReason(),
                    ]);
                }
            }
        }
    }
}
