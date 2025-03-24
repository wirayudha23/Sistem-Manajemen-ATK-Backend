<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ReorderCart;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        if ($product->stock <= $product->reorder_point) {
            $exist = ReorderCart::where('product_id', $product->id)->first();
            if (!$exist) {
                ReorderCart::create([
                    'product_id' => $product->id,
                    'quantity' => 1,
                ]);
            }
        }
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        //
    }

    /**
     * Handle the Product "force deleted" event.
     */
    public function forceDeleted(Product $product): void
    {
        //
    }

    public function updating(Product $product): void
    {
        //
    }
}
