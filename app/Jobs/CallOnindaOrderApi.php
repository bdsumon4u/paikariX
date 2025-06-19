<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class CallOnindaOrderApi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $orderId
    ) {}

    public function handle(): void
    {
        $domain = parse_url(config('app.url'), PHP_URL_HOST);
        $endpoint = config('app.oninda_url').'/api/reseller/orders/place';

        info('Calling Oninda order API: ' . $endpoint, $data = [
            'order_id' => $this->orderId,
            'domain' => $domain,
        ]);

        // call the api and log the response
        Http::post($endpoint, $data)->throw()->then(function ($response) {
            info('Oninda order API response: ' . $response->body());
        });
    }
}
