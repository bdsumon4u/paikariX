<?php

namespace App\Jobs;

use App\Http\Resources\ProductResource;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
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
        unset($data['id'], $data['source_id']);

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

        // Map products from Oninda database
        $products = json_decode($data['products'], true);

        // Get source_ids from reseller's products
        $sourceIds = collect($products)->pluck('source_id')->filter()->toArray();

        $onindaProducts = Product::on('oninda')
            ->whereIn('id', $sourceIds)
            ->get();

        $mappedProducts = collect($products)->map(function ($product) use ($onindaProducts) {
            $onindaProduct = $onindaProducts->firstWhere('id', $product['source_id']);
            if (! $onindaProduct) {
                return null;
            }

            $cartItem = (new ProductResource($onindaProduct))->toCartItem($product['quantity']);
            $cartItem['shipping_inside'] = $onindaProduct->shipping_inside;
            $cartItem['shipping_outside'] = $onindaProduct->shipping_outside;

            // Add retail price information
            $cartItem['retail_price'] = $product['price'];

            return $cartItem;
        })->filter()->values()->toArray();

        $data['products'] = json_encode($mappedProducts, JSON_UNESCAPED_UNICODE);
        $data['user_id'] = $reseller->id;
        $data['admin_id'] = $admin->id;

        // Modify data attribute
        $orderData = json_decode($data['data'], true);
        $orderData['subtotal'] = $this->order->getSubtotal($mappedProducts);
        $orderData['retail_delivery_fee'] = $orderData['shipping_cost'];
        $orderData['retail_discount'] = $orderData['discount'] ?? 0;

        // Calculate Oninda shipping cost
        $shippingCost = 0;
        foreach ($mappedProducts as $product) {
            if ($orderData['shipping_area'] === 'Inside Dhaka') {
                $shippingCost = max($shippingCost, $product['shipping_inside']);
            } else {
                $shippingCost = max($shippingCost, $product['shipping_outside']);
            }
        }

        $orderData['shipping_cost'] = $shippingCost;
        $orderData['discount'] = 0;

        $data['data'] = json_encode($orderData, JSON_UNESCAPED_UNICODE);

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
