<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;

class UpdateMonthlyMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:update-monthly-metrics';
    // php artisan inventory:update-monthly-metrics

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Safety Stock and ROP for all Products';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Product::chunk(100, function ($products) {
            foreach ($products as $product) {
                $product->calculateSafetyAndRop();

            }
        });

        $this->info('Monthly metrics updated successfully.');
    }
}
