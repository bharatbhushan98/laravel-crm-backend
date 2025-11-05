<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\PurchaseOrder;

class LowStockItemNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $po;

    public function __construct(PurchaseOrder $po)
    {
        $this->po = $po;
    }

    public function build()
    {
        return $this->subject("Purchase Order #{$this->po->id} Request")
            ->view('emails.purchase_order')
            ->with([
                'po' => $this->po,
            ]);
    }
}
