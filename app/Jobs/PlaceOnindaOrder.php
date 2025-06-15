<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PlaceOnindaOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Order $order
    ) {}

    public function handle(): void
    {
        $reseller = DB::connection('oninda')
            ->table('users')
            ->where('domain', request()->getHost())
            ->first();

        if (! $reseller) {
            return;
        }

        $data = $this->order->getRawOriginal();

        // Get old orders to determine admin assignment
        $oldOrders = DB::connection('oninda')
            ->table('orders')
            ->select(['id', 'admin_id', 'status'])
            ->where('phone', $data['phone'])
            ->get();

        $adminIds = $oldOrders->pluck('admin_id')->unique()->toArray();

        if (config('app.round_robin_order_receiving')) {
            $adminQ = DB::connection('oninda')
                ->table('admins')
                ->orderByRaw('CASE WHEN is_active = 1 THEN 0 ELSE 1 END, role_id desc, last_order_received_at asc');
            if (count($adminIds) > 0) {
                $admin = $adminQ->whereIn('id', $adminIds)->first() ?? $adminQ->first();
            } else {
                $admin = $adminQ->first();
            }
        } else {
            $adminQ = DB::connection('oninda')
                ->table('admins')
                ->where('role_id', Admin::SALESMAN)
                ->where('is_active', true)
                ->inRandomOrder();
            if (count($adminIds) > 0) {
                $admin = $adminQ->whereIn('id', $adminIds)->first() ?? $adminQ->first() ?? DB::connection('oninda')->table('admins')->where('is_active', true)->inRandomOrder()->first();
            } else {
                $admin = $adminQ->first() ?? DB::connection('oninda')->table('admins')->where('is_active', true)->inRandomOrder()->first();
            }
        }

        $data['user_id'] = $reseller->id;
        $data['admin_id'] = $admin->id;

        $onindaOrder = DB::connection('oninda')
            ->table('orders')
            ->insertGetId($data);

        if ($onindaOrder) {
            $this->order->update([
                'source_id' => $onindaOrder,
            ]);

            // Update admin's last_order_received_at
            DB::connection('oninda')
                ->table('admins')
                ->where('id', $admin->id)
                ->update(['last_order_received_at' => now()]);
        }
    }
}
