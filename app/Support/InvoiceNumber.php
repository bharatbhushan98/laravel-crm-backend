<?php

namespace App\Support;

use App\Models\Invoice;

class InvoiceNumber
{
    public static function next(): string
    {
        $prefix = 'INV-'.now()->format('Ym').'-';
        $last = Invoice::where('invoice_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('invoice_number');

        $seq = 1;
        if ($last && preg_match('/-(\d{4})$/', $last, $m)) {
            $seq = (int)$m[1] + 1;
        }
        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }
}