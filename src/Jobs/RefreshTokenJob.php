<?php

namespace WhatsApp\Business\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use WhatsApp\Business\Models\Customer;
use WhatsApp\Business\Services\OAuthService;

class RefreshTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Customer $customer
    ) {}

    public function handle(OAuthService $oauthService): void
    {
        if ($this->customer->isTokenExpired()) {
            $newToken = $oauthService->refreshLongLivedToken($this->customer->access_token);

            if ($newToken && isset($newToken['access_token'])) {
                $this->customer->update([
                    'access_token' => $newToken['access_token'],
                    'token_expires_at' => now()->addSeconds($newToken['expires_in'] ?? 5184000),
                ]);
            }
        }
    }
}
