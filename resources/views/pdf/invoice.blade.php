<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tax Invoice {{ $invoice->invoice_number ?? 'N/A' }}</title>
    <style>
        @page { margin: 20mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        td, th { padding: 5px; border: 1px solid #000; vertical-align: top; }
        .no-border, .no-border td, .no-border th { border: none; }
        .header-section td { padding: 0; }
        .proforma-invoice { text-align: center; font-size: 12px; font-weight: bold; border-bottom: 1px solid #000; }
        .bold { font-weight: bold; }
        .text-right { text-align: right; }
        .terms-box { border: 1px solid #000; padding: 5px; margin-top: 10px; }
        small { font-size: 9px; }
    </style>
</head>
<body>
    <!-- Header -->
    <table class="no-border header-section">
        <tr><td class="proforma-invoice">TAX INVOICE</td></tr>
    </table>

    <!-- Company / Invoice Info -->
    <table style="margin-top: 10px;">
        <tr>
            <td width="50%">
                <strong>From (Company)</strong><br>
                {{ $invoice->companyProfile->name ?? 'N/A' }}<br>
                {{ $invoice->companyProfile->address ?? 'N/A' }}<br>
                GSTIN/UIN: {{ $invoice->companyProfile->gst_number ?? 'N/A' }}<br>
                Email: {{ $invoice->companyProfile->email ?? 'N/A' }}<br>
                Contact: {{ $invoice->companyProfile->phone ?? 'N/A' }}
            </td>
            <td width="50%">
                <table class="no-border">
                    <tr>
                        <td class="bold">Invoice No.</td>
                        <td>{{ $invoice->invoice_number ?? 'N/A' }}</td>
                        <td class="bold">Dated</td>
                        <td>{{ $invoice->issue_date ? \Carbon\Carbon::parse($invoice->issue_date)->format('d-M-Y') : 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Delivery Note</td>
                        <td>{{ $invoice->delivery_note ?? 'N/A' }}</td>
                        <td class="bold">Payment Mode</td>
                        <td>{{ $invoice->payment_mode ?? 'N/A' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Billing / Shipping -->
    <table style="margin-top: 10px;">
        <tr>
            <td width="50%">
                <div class="bold">Billing Address</div>
                @if($invoice->order && $invoice->order->billingAddress)
                    {{ $invoice->order->billingAddress->name ?? 'N/A' }}<br>
                    {{ $invoice->order->billingAddress->address_line1 ?? '' }}
                    {{ $invoice->order->billingAddress->address_line2 ? ', '.$invoice->order->billingAddress->address_line2 : '' }}<br>
                    {{ $invoice->order->billingAddress->city ?? '' }},
                    {{ $invoice->order->billingAddress->state ?? '' }} -
                    {{ $invoice->order->billingAddress->postal_code ?? '' }}<br>
                    {{ $invoice->order->billingAddress->country ?? '' }}<br>
                    Email: {{ $invoice->order->billingAddress->email ?? 'N/A' }}<br>
                    Contact: {{ $invoice->order->billingAddress->phone ?? 'N/A' }}
                @else
                    N/A
                @endif
            </td>
            <td width="50%">
                <div class="bold">Shipping Address</div>
                @if($invoice->order && $invoice->order->shippingAddress)
                    {{ $invoice->order->shippingAddress->name ?? 'N/A' }}<br>
                    {{ $invoice->order->shippingAddress->address_line1 ?? '' }}
                    {{ $invoice->order->shippingAddress->address_line2 ? ', '.$invoice->order->shippingAddress->address_line2 : '' }}<br>
                    {{ $invoice->order->shippingAddress->city ?? '' }},
                    {{ $invoice->order->shippingAddress->state ?? '' }} -
                    {{ $invoice->order->shippingAddress->postal_code ?? '' }}<br>
                    {{ $invoice->order->shippingAddress->country ?? '' }}<br>
                    Email: {{ $invoice->order->shippingAddress->email ?? 'N/A' }}<br>
                    Contact: {{ $invoice->order->shippingAddress->phone ?? 'N/A' }}
                @else
                    N/A
                @endif
            </td>
        </tr>
    </table>

    <!-- Items Table -->
    @php
        $subtotal = $totalCgst = $totalSgst = $totalIgst = 0;
    @endphp
    <table style="margin-top: 10px;">
        <thead>
            <tr>
                <th>S.No.</th>
                <th>Description of Goods</th>
                <th>HSN Code</th>
                <th>Batch No.</th>
                <th>Expiry Date</th>
                <th>Qty</th>
                <th>Rate</th>
                <th>Amount</th>
                <th>CGST</th>
                <th>SGST</th>
                <th>IGST</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $i => $item)
                @php
                    $amount = $item->quantity * $item->unit_price;
                    $cgstAmt = $amount * ($item->cgst_rate ?? 0) / 100;
                    $sgstAmt = $amount * ($item->sgst_rate ?? 0) / 100;
                    $igstAmt = $amount * ($item->igst_rate ?? 0) / 100;
                    $lineTotal = $amount + $cgstAmt + $sgstAmt + $igstAmt;
                    $subtotal += $amount;
                    $totalCgst += $cgstAmt;
                    $totalSgst += $sgstAmt;
                    $totalIgst += $igstAmt;
                @endphp
                <tr>
                    <td>{{ $i+1 }}</td>
                    <td>{{ $item->product->name ?? 'N/A' }}</td>
                    <td>{{ $item->hsn_code ?? 'N/A' }}</td>
                    <td>{{ $item->batch?->batch_number ?? 'N/A' }}</td>
                    <td>{{ $item->batch?->expiry_date ? \Carbon\Carbon::parse($item->batch->expiry_date)->format('d-M-Y') : 'N/A' }}</td>
                    <td>{{ $item->quantity ?? 0 }}</td>
                    <td class="text-right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format($amount, 2) }}</td>
                    <td class="text-right">{{ number_format($cgstAmt, 2) }}</td>
                    <td class="text-right">{{ number_format($sgstAmt, 2) }}</td>
                    <td class="text-right">{{ number_format($igstAmt, 2) }}</td>
                    <td class="text-right">{{ number_format($lineTotal, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="text-right">No items found</td></tr>
            @endforelse
            <tr>
                <td colspan="7" class="bold text-right">Totals</td>
                <td class="text-right bold">{{ number_format($subtotal, 2) }}</td>
                <td class="text-right bold">{{ number_format($totalCgst, 2) }}</td>
                <td class="text-right bold">{{ number_format($totalSgst, 2) }}</td>
                <td class="text-right bold">{{ number_format($totalIgst, 2) }}</td>
                <td class="text-right bold">{{ number_format($subtotal + $totalCgst + $totalSgst + $totalIgst, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @php
        $grandTotal = $subtotal + $totalCgst + $totalSgst + $totalIgst;
        $taxTotal = $totalCgst + $totalSgst + $totalIgst;
        $f = new \NumberFormatter("en_IN", \NumberFormatter::SPELLOUT);
        $amountInWords = ucfirst($f->format(round($grandTotal))) . " rupees only";
        $taxInWords = ucfirst($f->format(round($taxTotal))) . " rupees only";
    @endphp

    <!-- Totals in words -->
    <div class="terms-box">
        <div class="bold">Amount Chargeable (in words):</div>
        <div>{{ $amountInWords }}</div>
        <br>
        <div class="bold">Tax Amount (in words):</div>
        <div>{{ $taxInWords }}</div>
    </div>

    <!-- Bank / Signature -->
    <table class="no-border" style="margin-top: 10px;">
        <tr>
            <td width="50%">
                <div class="bold">Company's Bank Details</div>
                Bank: {{ $invoice->companyProfile->bank_details['bank_name'] ?? 'N/A' }}<br>
                A/C No: {{ $invoice->companyProfile->bank_details['account_number'] ?? 'N/A' }}<br>
                IFSC: {{ $invoice->companyProfile->bank_details['branch_ifsc'] ?? 'N/A' }}
            </td>
            <td width="50%" class="text-right">
                <div style="margin-top: 30px;">For {{ $invoice->companyProfile->name ?? 'N/A' }}</div>
                <div style="margin-top: 40px;">Authorized Signatory</div>
            </td>
        </tr>
    </table>

    <div style="text-align:center; margin-top: 10px;">
        <small>Subject to {{ $invoice->companyProfile->jurisdiction ?? 'N/A' }} Jurisdiction</small><br>
        <small>This is a Computer Generated Invoice</small>
    </div>
</body>
</html>