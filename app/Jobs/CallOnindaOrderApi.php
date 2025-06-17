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
        $domain = request()->getHost();

        Http::post(config('app.oninda_url').'/api/reseller/orders/place', [
            'order_id' => $this->orderId,
            'domain' => $domain,
        ]);
    }
}
