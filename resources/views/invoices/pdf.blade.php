<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 20px;
        }
        
        .company-info {
            flex: 1;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
        }
        
        .company-details {
            font-size: 11px;
            color: #666;
            line-height: 1.3;
        }
        
        .invoice-info {
            text-align: right;
            flex: 1;
        }
        
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
        }
        
        .invoice-details {
            font-size: 11px;
            color: #666;
        }
        
        .billing-section {
            display: flex;
            justify-content: space-between;
            margin: 30px 0;
        }
        
        .bill-to, .invoice-summary {
            flex: 1;
            margin-right: 20px;
        }
        
        .bill-to:last-child, .invoice-summary:last-child {
            margin-right: 0;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .tenant-info {
            background: #f9fafb;
            padding: 15px;
            border-left: 3px solid #2563eb;
        }
        
        .tenant-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .line-items {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 11px;
        }
        
        .line-items th {
            background: #f3f4f6;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            color: #374151;
            border-bottom: 2px solid #d1d5db;
        }
        
        .line-items td {
            padding: 10px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .line-items tr:hover {
            background: #f9fafb;
        }
        
        .text-right {
            text-align: right;
        }
        
        .text-center {
            text-align: center;
        }
        
        .totals-section {
            width: 300px;
            margin-left: auto;
            margin-top: 20px;
        }
        
        .totals-table {
            width: 100%;
            font-size: 12px;
        }
        
        .totals-table td {
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .totals-table .total-row {
            font-weight: bold;
            font-size: 14px;
            color: #1f2937;
        }
        
        .totals-table .total-row td {
            border-bottom: 2px solid #374151;
            padding-top: 12px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-open {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-draft {
            background: #e5e7eb;
            color: #374151;
        }
        
        .status-void {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .notes-section {
            margin-top: 40px;
            font-size: 11px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }
        
        .page-break {
            page-break-after: always;
        }
        
        @media print {
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">
            <div class="company-name">{{ $company['name'] }}</div>
            <div class="company-details">
                {{ $company['address_line1'] }}<br>
                @if($company['address_line2'])
                    {{ $company['address_line2'] }}<br>
                @endif
                {{ $company['city'] }}, {{ $company['state'] }} {{ $company['postal_code'] }}<br>
                {{ $company['country'] }}<br><br>
                
                Email: {{ $company['email'] }}<br>
                Phone: {{ $company['phone'] }}<br>
                @if($company['tax_id'])
                    Tax ID: {{ $company['tax_id'] }}<br>
                @endif
                Website: {{ $company['website'] }}
            </div>
        </div>
        
        <div class="invoice-info">
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-details">
                <strong>Invoice #:</strong> {{ $invoice->number }}<br>
                <strong>Date:</strong> {{ $invoice->created_at->format('M d, Y') }}<br>
                @if($invoice->period_start && $invoice->period_end)
                    <strong>Billing Period:</strong><br>
                    {{ $invoice->period_start->format('M d, Y') }} - {{ $invoice->period_end->format('M d, Y') }}<br>
                @endif
                <strong>Status:</strong> 
                <span class="status-badge status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span>
            </div>
        </div>
    </div>
    
    <div class="billing-section">
        <div class="bill-to">
            <div class="section-title">Bill To</div>
            <div class="tenant-info">
                <div class="tenant-name">{{ $tenant->name }}</div>
                @if($tenant->domain)
                    Domain: {{ $tenant->domain }}<br>
                @endif
                Tenant ID: {{ $tenant->id }}
            </div>
        </div>
        
        <div class="invoice-summary">
            <div class="section-title">Invoice Summary</div>
            <table class="totals-table">
                <tr>
                    <td>Subtotal:</td>
                    <td class="text-right">${{ number_format($invoice->subtotal, 2) }}</td>
                </tr>
                @if($invoice->tax > 0)
                <tr>
                    <td>Tax:</td>
                    <td class="text-right">${{ number_format($invoice->tax, 2) }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td>Total:</td>
                    <td class="text-right">${{ number_format($invoice->total, 2) }}</td>
                </tr>
                @if($invoice->amount_paid > 0)
                <tr>
                    <td>Amount Paid:</td>
                    <td class="text-right">${{ number_format($invoice->amount_paid, 2) }}</td>
                </tr>
                @endif
                @if($invoice->amount_due > 0)
                <tr>
                    <td><strong>Amount Due:</strong></td>
                    <td class="text-right"><strong>${{ number_format($invoice->amount_due, 2) }}</strong></td>
                </tr>
                @endif
            </table>
        </div>
    </div>
    
    @if($invoice->line_items && count($invoice->line_items) > 0)
    <table class="line-items">
        <thead>
            <tr>
                <th style="width: 50%;">Description</th>
                <th class="text-center" style="width: 15%;">Quantity</th>
                <th class="text-right" style="width: 20%;">Unit Price</th>
                <th class="text-right" style="width: 15%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->line_items as $item)
            <tr>
                <td>
                    <strong>{{ $item['description'] ?? $item['product_name'] ?? 'Service' }}</strong>
                    @if(isset($item['period_start']) && isset($item['period_end']) && $item['period_start'] && $item['period_end'])
                        <br><small class="text-gray-600">
                            Period: {{ date('M d, Y', $item['period_start']) }} - {{ date('M d, Y', $item['period_end']) }}
                        </small>
                    @endif
                </td>
                <td class="text-center">{{ $item['quantity'] ?? 1 }}</td>
                <td class="text-right">${{ number_format(($item['amount'] ?? 0) / ($item['quantity'] ?? 1), 2) }}</td>
                <td class="text-right">${{ number_format($item['amount'] ?? 0, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
    
    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td>Subtotal:</td>
                <td class="text-right">${{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
            @if($invoice->tax > 0)
            <tr>
                <td>Tax:</td>
                <td class="text-right">${{ number_format($invoice->tax, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Total ({{ strtoupper($invoice->currency) }}):</td>
                <td class="text-right">${{ number_format($invoice->total, 2) }}</td>
            </tr>
        </table>
    </div>
    
    <div class="notes-section">
        <strong>Payment Information:</strong><br>
        @if($invoice->status === 'paid')
            This invoice has been paid in full. Thank you for your business!
        @elseif($invoice->status === 'open')
            Payment is due upon receipt. Late payments may be subject to service interruption.
        @endif
        <br><br>
        
        <strong>Questions?</strong><br>
        If you have any questions about this invoice, please contact us at {{ $company['email'] }} or {{ $company['phone'] }}.
        <br><br>
        
        <small>
            This is a computer-generated invoice. No signature required.<br>
            Generated on {{ now()->format('M d, Y \a\t H:i:s T') }}
        </small>
    </div>
</body>
</html>