<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;

class UpdateYearlyMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:update-yearly-metrics';
    // php artisan inventory:update-yearly-metrics

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update EOQ for all Products';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Product::chunk(100, function ($products) {
            foreach ($products as $product) {
                $product->calculateEoq();
            }
        });

        $this->info('Yearly metrics updated successfully.');
    }
}
