<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: #2563eb;
            color: white;
            padding: 30px 20px;
            border-radius: 8px 8px 0 0;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        
        .header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        
        .content {
            background: white;
            padding: 30px 20px;
            border: 1px solid #e5e7eb;
            border-top: none;
        }
        
        .invoice-details {
            background: #f8fafc;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #2563eb;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .detail-row:last-child {
            margin-bottom: 0;
        }
        
        .detail-label {
            font-weight: 600;
            color: #4b5563;
        }
        
        .detail-value {
            color: #1f2937;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            background: #f3f4f6;
            color: #374151;
        }
        
        .cta-section {
            text-align: center;
            margin: 30px 0;
        }
        
        .cta-button {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.2s;
        }
        
        .cta-button:hover {
            background: #1d4ed8;
        }
        
        .footer {
            background: #f9fafb;
            padding: 20px;
            border-radius: 0 0 8px 8px;
            border: 1px solid #e5e7eb;
            border-top: none;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }
        
        .footer a {
            color: #2563eb;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 600px) {
            body {
                padding: 10px;
            }
            
            .header, .content, .footer {
                padding: 20px 15px;
            }
            
            .detail-row {
                flex-direction: column;
                margin-bottom: 12px;
            }
            
            .detail-label {
                margin-bottom: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Invoice {{ $invoice->number }}</h1>
        <p>Thank you for your business with {{ config('app.name') }}</p>
    </div>
    
    <div class="content">
        <p>Hello {{ $tenant->name }},</p>
        
        <p>Your invoice for {{ config('app.name') }} subscription services is now available. Please find the details below:</p>
        
        <div class="invoice-details">
            <div class="detail-row">
                <span class="detail-label">Invoice Number:</span>
                <span class="detail-value">{{ $invoice->number }}</span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Invoice Date:</span>
                <span class="detail-value">{{ $invoice->created_at->format('M d, Y') }}</span>
            </div>
            
            @if($invoice->period_start && $invoice->period_end)
            <div class="detail-row">
                <span class="detail-label">Billing Period:</span>
                <span class="detail-value">{{ $invoice->period_start->format('M d, Y') }} - {{ $invoice->period_end->format('M d, Y') }}</span>
            </div>
            @endif
            
            <div class="detail-row">
                <span class="detail-label">Total Amount:</span>
                <span class="detail-value"><strong>${{ number_format($invoice->total, 2) }} {{ strtoupper($invoice->currency) }}</strong></span>
            </div>
            
            @if($invoice->amount_due > 0)
            <div class="detail-row">
                <span class="detail-label">Amount Due:</span>
                <span class="detail-value"><strong>${{ number_format($invoice->amount_due, 2) }}</strong></span>
            </div>
            @endif
            
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value">
                    <span class="status-badge status-{{ $invoice->status }}">{{ ucfirst($invoice->status) }}</span>
                </span>
            </div>
        </div>
        
        @if($invoice->status === 'open')
        <div class="cta-section">
            <p><strong>Payment Required</strong></p>
            <p>Please log in to your account to complete the payment for this invoice.</p>
            <a href="{{ config('app.url') }}/billing" class="cta-button">Pay Invoice</a>
        </div>
        @elseif($invoice->status === 'paid')
        <div class="cta-section">
            <p style="color: #059669;"><strong>✓ Payment Received</strong></p>
            <p>Thank you! Your payment has been processed successfully.</p>
        </div>
        @endif
        
        <p>The complete invoice is attached to this email as a PDF file. You can also access your billing history anytime by logging into your account.</p>
        
        <p>If you have any questions about this invoice or need assistance, please don't hesitate to reach out to our support team.</p>
        
        <p>Thank you for choosing {{ config('app.name') }}!</p>
        
        <p>Best regards,<br>
        The {{ config('app.name') }} Team</p>
    </div>
    
    <div class="footer">
        <p>
            <strong>{{ config('app.name') }}</strong><br>
            Questions? Contact us at 
            <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>
        </p>
        
        <p style="margin-top: 15px; font-size: 12px;">
            This is an automated message. Please do not reply directly to this email.<br>
            <a href="{{ config('app.url') }}">Visit our website</a> | 
            <a href="{{ config('app.url') }}/billing">Manage billing</a>
        </p>
    </div>
</body>
</html>