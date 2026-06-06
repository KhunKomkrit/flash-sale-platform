<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::disableQueryLog();

        $now = now();
        $nowTimestamp = $now->getTimestamp();
        $nowString = $now->toDateTimeString();
        $password = Hash::make('password');

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */
        $userRows = [];

        $userRows[] = [
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'email_verified_at' => $nowString,
            'password' => $password,
            'remember_token' => Str::random(10),
            'created_at' => $nowString,
            'updated_at' => $nowString,
        ];

        for ($i = 1; $i <= 1000; $i++) {
            $userRows[] = [
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'email_verified_at' => $nowString,
                'password' => $password,
                'remember_token' => Str::random(10),
                'created_at' => $nowString,
                'updated_at' => $nowString,
            ];
        }

        foreach (array_chunk($userRows, 500) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        /*
        |--------------------------------------------------------------------------
        | Products
        |--------------------------------------------------------------------------
        */
        $productRows = [];

        for ($i = 1; $i <= 500; $i++) {
            $productRows[] = [
                'name' => "Product {$i}",
                'sku' => 'SKU-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'stock' => random_int(50, 5000),
                'price' => random_int(5000, 500000) / 100,
                'is_active' => true,
                'created_at' => $nowString,
                'updated_at' => $nowString,
            ];
        }

        foreach (array_chunk($productRows, 500) as $chunk) {
            DB::table('products')->insert($chunk);
        }

        /*
        |--------------------------------------------------------------------------
        | Sale Events
        |--------------------------------------------------------------------------
        */
        $saleEventRows = [];

        for ($i = 1; $i <= 20; $i++) {
            $startsAt = $now->copy()->subDays(random_int(1, 30));
            $endsAt = $startsAt->copy()->addHours(random_int(1, 72));

            $saleEventRows[] = [
                'name' => "Flash Sale Event {$i}",
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'is_active' => true,
                'created_at' => $nowString,
                'updated_at' => $nowString,
            ];
        }

        DB::table('sales_events')->insert($saleEventRows);

        /*
        |--------------------------------------------------------------------------
        | Orders: 100,000 rows
        |--------------------------------------------------------------------------
        */
        $userIds = DB::table('users')->pluck('id')->values()->all();
        $products = DB::table('products')
            ->select(['id', 'price'])
            ->get()
            ->map(fn ($product) => [
                'id' => $product->id,
                'price' => $product->price,
            ])
            ->values()
            ->all();
        $saleEventIds = DB::table('sales_events')->pluck('id')->values()->all();

        $totalOrders = 100000;
        $chunkSize = 250;
        $orderRows = [];
        $userCount = count($userIds);
        $productCount = count($products);
        $saleEventCount = count($saleEventIds);

        for ($i = 0; $i < $totalOrders; $i++) {
            $userId = $userIds[$i % $userCount];
            $product = $products[intdiv($i, $userCount) % $productCount];
            $saleEventId = $saleEventIds[
                intdiv($i, $userCount * $productCount) % $saleEventCount
            ];

            $orderRows[] = [
                'user_id' => $userId,
                'product_id' => $product['id'],
                'sale_event_id' => $saleEventId,
                'quantity' => 1,
                'unit_price' => $product['price'],
                'status' => ['pending', 'paid', 'cancelled', 'failed'][$i % 4],
                'created_at' => date('Y-m-d H:i:s', $nowTimestamp - $i),
                'updated_at' => $nowString,
            ];

            if (count($orderRows) >= $chunkSize) {
                DB::table('orders')->insert($orderRows);
                $orderRows = [];
                gc_collect_cycles();
            }
        }

        if (! empty($orderRows)) {
            DB::table('orders')->insert($orderRows);
        }

        /*
        |--------------------------------------------------------------------------
        | Order Logs: memory-safe chunkById
        |--------------------------------------------------------------------------
        */
        DB::table('orders')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunkById(250, function ($orders) use ($nowString) {
                $logRows = [];

                foreach ($orders as $order) {
                    $logRows[] = [
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                        'action' => 'created',
                        'payload' => json_encode([
                            'request_id' => (string) Str::uuid(),
                            'source' => 'seeder',
                        ]),
                        'created_at' => $nowString,
                        'updated_at' => $nowString,
                    ];
                }

                DB::table('order_logs')->insert($logRows);
                unset($logRows);
                gc_collect_cycles();
            });
    }
}
