<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class UpdateInventoryMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:update-metrics';
    //php artisan inventory:update-metrics
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update EOQ, Safety Stock, and ROP annually';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Product::chunk(100, function ($products) {
            foreach ($products as $product) {
                $product->calculateInventoryMetrics();
            }
        });

        $this->info('Inventory metrics updated successfully.');
    }
}
