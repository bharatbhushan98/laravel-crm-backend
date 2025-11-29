<?php

namespace App\Http\Controllers;

use App\Models\{Invoice, InvoiceItem, Order, Customer, CompanyProfile, Notification};
use App\Support\InvoiceNumber;
use App\Events\NewNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\InvoicesExport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    /**
     * Send Notification – SAME AS ALL OTHER CONTROLLERS
     */
    private function notify(Request $request, string $type, string $title, string $message, array $replacements = [])
    {
        $user = $request->user();

        $performerName = $user?->name ?? $request->header('X-User-Name', 'Unknown User');
        $performerId   = $user?->id ?? $request->header('X-User-ID', 1);

        $default = [
            'performer_name' => $performerName,
            'performer_id'   => $performerId,
            'timestamp'      => Carbon::now()->format('d M Y, h:i A'),
        ];

        $replacements = array_merge($default, $replacements);

        foreach ($replacements as $key => $value) {
            $message = str_replace("{{{$key}}}", $value, $message);
        }

        $notification = Notification::create([
            'user_id' => $performerId,
            'type'    => $type,
            'data'    => [
                'title'   => $title,
                'message' => $message,
                'icon'    => match ($type) {
                    'invoice_created'     => 'FileText',
                    'invoice_sent'        => 'Mail',
                    'invoice_downloaded'  => 'Download',
                    default               => 'Bell'
                },
            ],
            'is_read' => false,
        ]);

        broadcast(new NewNotification($notification));
    }

    public function index()
    {
        $invoices = Invoice::with(['customer', 'companyProfile', 'order', 'items.product', 'items.batch'])
            ->latest()
            ->paginate(15);

        return response()->json($invoices);
    }

    public function show($id)
    {
        $invoice = Invoice::with(['customer', 'companyProfile', 'order', 'items.product', 'items.batch'])
            ->findOrFail($id);

        return response()->json($invoice);
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'customer_id' => 'required|exists:customers,id',
            'company_profile_id' => 'nullable|exists:company_profiles,id',
            'order_id' => 'nullable|exists:orders,id',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'currency' => 'nullable|string|size:3',
            'shipping_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'required_without:order_id|array|min:1',
            'items.*.product_id' => 'required_without:order_id|exists:products,id',
            'items.*.batch_id' => 'nullable|exists:batches,id',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required_without:order_id|numeric|min:0.01',
            'items.*.sell_price' => 'required_without:order_id|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.hsn_code' => 'nullable|string',
            'items.*.gst_rate' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($data, $req) {
            $data['company_profile_id'] = 1;

            if (!empty($data['order_id']) && empty($data['items'])) {
                $order = Order::with('items')->findOrFail($data['order_id']);
                if ($order->items->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Order has no items to invoice.',
                    ], 400);
                }
                $data['items'] = $order->items->map(function ($oi) {
                    return [
                        'product_id' => $oi->product_id,
                        'batch_id' => $oi->batch_id,
                        'description' => $oi->product?->name ?? 'Unknown Product',
                        'quantity' => (float) $oi->quantity,
                        'sell_price' => (float) $oi->sell_price,
                        'discount' => 0,
                        'hsn_code' => $oi->product?->hsn_code ?? null,
                        'gst_rate' => $oi->product?->gst_rate ?? 0,
                    ];
                })->toArray();
            } elseif (empty($data['items'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Items are required when no order is provided.',
                ], 400);
            }

            $subTotal = 0;
            $taxTotal = 0;

            foreach ($data['items'] as &$it) {
                $qty = (float) $it['quantity'];
                $price = (float) $it['sell_price'];
                $disc = (float) ($it['discount'] ?? 0);
                $gstRate = (float) ($it['gst_rate'] ?? 0);

                $it['cgst_rate'] = $gstRate / 2;
                $it['sgst_rate'] = $gstRate / 2;
                $it['igst_rate'] = 0;

                $lineBase = max($qty * $price - $disc, 0);
                $cgstAmt = round($lineBase * $it['cgst_rate'] / 100, 2);
                $sgstAmt = round($lineBase * $it['sgst_rate'] / 100, 2);
                $igstAmt = round($lineBase * $it['igst_rate'] / 100, 2);

                $lineTax = $cgstAmt + $sgstAmt + $igstAmt;

                $it['line_total'] = $lineBase + $lineTax;
                $it['tax_rate'] = $gstRate;

                $subTotal += $lineBase;
                $taxTotal += $lineTax;
            }

            $shipping = (float) ($data['shipping_amount'] ?? 0);
            $headerDiscount = (float) ($data['discount_amount'] ?? 0);
            $total = max($subTotal - $headerDiscount, 0) + $taxTotal + $shipping;

            $invoice = Invoice::create([
                'invoice_number' => InvoiceNumber::next(),
                'customer_id' => $data['customer_id'],
                'company_profile_id' => $data['company_profile_id'],
                'order_id' => $data['order_id'] ?? null,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'currency' => $data['currency'] ?? 'INR',
                'sub_total' => $subTotal,
                'discount_amount' => $headerDiscount,
                'tax_amount' => $taxTotal,
                'shipping_amount' => $shipping,
                'total_amount' => $total,
                'amount_paid' => 0,
                'status' => 'Draft',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            foreach ($data['items'] as $it) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $it['product_id'],
                    'batch_id' => $it['batch_id'] ?? null,
                    'description' => $it['description'] ?? null,
                    'quantity' => $it['quantity'],
                    'unit_price' => $it['sell_price'],
                    'discount' => $it['discount'] ?? 0,
                    'hsn_code' => $it['hsn_code'] ?? null,
                    'cgst_rate' => $it['cgst_rate'],
                    'sgst_rate' => $it['sgst_rate'],
                    'igst_rate' => $it['igst_rate'],
                    'tax_rate' => $it['tax_rate'],
                    'line_total' => $it['line_total'],
                ]);
            }

            // NOTIFICATION: Invoice Created
            $customer = Customer::find($data['customer_id']);
            $this->notify(
                $req,
                'invoice_created',
                'Invoice Created',
                '{{performer_name}} created invoice {{invoice_number}} for {{customer_name}} (₹{{total_amount}}) at {{timestamp}}.',
                [
                    'invoice_number' => $invoice->invoice_number,
                    'customer_name'  => $customer->name,
                    'total_amount'   => number_format($invoice->total_amount, 2),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Invoice created',
                'invoice' => $invoice->load('customer', 'companyProfile', 'order', 'items.product', 'items.batch'),
            ], 201);
        });
    }

    /**
     * EXPORT PDF + NOTIFICATION
     */
    public function exportPDF(Request $request, $id)
    {
        $invoice = Invoice::with(['customer', 'companyProfile', 'items.product', 'items.batch'])
            ->findOrFail($id);

        if (!$invoice->customer || !$invoice->companyProfile) {
            abort(404, 'Customer or Company Profile not found for this invoice.');
        }

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'));

        // NOTIFICATION: Invoice Downloaded
        $this->notify(
            $request,
            'invoice_downloaded',
            'Invoice Downloaded',
            '{{performer_name}} downloaded invoice {{invoice_number}} for {{customer_name}} at {{timestamp}}.',
            [
                'invoice_number' => $invoice->invoice_number,
                'customer_name'  => $invoice->customer->name,
            ]
        );

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }

    public function sendInvoiceEmail(Request $req, $id)
    {
        $invoice = Invoice::with(['customer', 'companyProfile', 'items.product', 'items.batch'])
            ->findOrFail($id);

        if (!$invoice->customer || !$invoice->customer->contact) {
            return response()->json([
                'success' => false,
                'message' => 'Customer does not have an email address.',
            ], 400);
        }

        $recipient = $req->input('to_email', $invoice->customer->contact);
        $ccEmail = $req->input('cc_email');

        if ($ccEmail && !filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid CC email address.',
            ], 400);
        }

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'));
        $pdfData = $pdf->output();
        $downloadUrl = url("/api/invoices/{$invoice->id}/export-pdf");

        try {
            Mail::send([], [], function (Message $message) use ($recipient, $ccEmail, $invoice, $pdfData, $downloadUrl) {
                $message->to($recipient)
                    ->subject("Invoice #{$invoice->invoice_number}")
                    ->html("
                        <p>Dear {$invoice->customer->name},</p>
                        <p>Please find your invoice <strong>#{$invoice->invoice_number}</strong>.</p>
                        <p>You can <a href='{$downloadUrl}' target='_blank'>click here</a> to download the invoice.</p>
                        <p>We have also attached the PDF copy for your reference.</p>
                        <p><strong>Company:</strong> {$invoice->companyProfile->name}, {$invoice->companyProfile->email}</p>
                        <p>Thank you for your business!</p>
                    ")
                    ->attachData($pdfData, "invoice-{$invoice->invoice_number}.pdf", [
                        'mime' => 'application/pdf',
                    ]);

                if ($ccEmail) {
                    $message->cc($ccEmail);
                }
            });

            // NOTIFICATION: Invoice Sent
            $this->notify(
                $req,
                'invoice_sent',
                'Invoice Sent via Email',
                '{{performer_name}} sent invoice {{invoice_number}} to {{email_to}}' . ($ccEmail ? ' (CC: {{email_cc}})' : '') . ' at {{timestamp}}.',
                [
                    'invoice_number' => $invoice->invoice_number,
                    'email_to'       => $recipient,
                    'email_cc'       => $ccEmail ?? 'N/A',
                ]
            );

            return response()->json([
                'success' => true,
                'message' => "Invoice sent successfully to {$recipient}" . ($ccEmail ? " with CC to {$ccEmail}" : "") . ".",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invoice: ' . $e->getMessage(),
            ], 500);
        }
    }
}