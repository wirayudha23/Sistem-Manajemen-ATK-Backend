<?php

namespace App\Mail;

use App\Models\Checkout;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CheckoutMail extends Mailable
{
    use Queueable, SerializesModels;

    public Checkout $checkout;

    public function __construct(Checkout $checkout)
    {
        $this->checkout = $checkout;
    }

    public function build()
    {
        $tanggal = $this->checkout->checkout_date->format('d M Y');

        return $this
            ->subject("Informasi Pengambilan ATK – {$tanggal}")
            ->view('mail.mail')
            ->with([
                'checkout' => $this->checkout,
                'user' => $this->checkout->user,
                'items' => $this->checkout->items,
            ]);
    }

}
