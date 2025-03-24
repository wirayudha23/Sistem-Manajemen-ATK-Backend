<?php

namespace App\Mail;

use App\Models\Reorder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReorderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $reorder;

    public function __construct(Reorder $reorder)
    {
        $this->reorder = $reorder;
    }

    public function build()
    {
        return $this->view('emails.reorder')
                    ->with([
                        'reorder' => $this->reorder,
                    ]);
    }
}
