<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Purchase Order #{{ $po->po_number ?? $po->id }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f8f8; padding: 10px; text-align: center; }
        .table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background: #f2f2f2; }
        .footer { margin-top: 20px; font-size: 12px; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Purchase Order #{{ $po->po_number ?? $po->id }}</h1>
        </div>

        <p>Dear {{ $po->supplier->name ?? 'Supplier' }},</p>
        <p>We would like to place the following order with you:</p>

        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Buy Price</th>
                    <th>Delivery Deadline:</th>
                </tr>
            </thead>
            <tbody>
                @foreach($po->items as $item)
                    <tr>
                        <td>{{ $item->product->name ?? 'N/A' }}</td>
                        <td>{{ $item->requested_qty }}</td>
                        <td>${{ number_format($item->buy_price, 2) }}</td>
                        <td>{{ $po->delivery_deadline ? date('Y-m-d', strtotime($po->delivery_deadline)) : 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p>Please confirm receipt of this purchase order and ensure delivery by the specified deadline.</p>
        <p>Thank you for your prompt attention to this order.</p>

        <div class="footer">
            <p>Best regards,<br>PharmaGrow CRM</p>
        </div>
    </div>
</body>
</html>
