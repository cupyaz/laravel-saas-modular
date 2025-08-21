<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Dompdf\Dompdf;
use Dompdf\Options;
use Carbon\Carbon;

class InvoicePdfService
{
    protected $dompdf;
    protected $options;

    public function __construct()
    {
        $this->options = new Options();
        $this->options->set('defaultFont', 'Helvetica');
        $this->options->set('isHtml5ParserEnabled', true);
        $this->options->set('isPhpEnabled', true);
        $this->options->set('isRemoteEnabled', true);
        $this->options->set('defaultPaperSize', 'A4');
        $this->options->set('defaultPaperOrientation', 'portrait');
        
        $this->dompdf = new Dompdf($this->options);
    }

    /**
     * Generate PDF for invoice and store it.
     */
    public function generatePdf(Invoice $invoice): string
    {
        try {
            $html = $this->generateInvoiceHtml($invoice);
            
            $this->dompdf->loadHtml($html);
            $this->dompdf->setPaper('A4', 'portrait');
            $this->dompdf->render();
            
            $pdfContent = $this->dompdf->output();
            $filename = "invoices/{$invoice->number}.pdf";
            
            // Store PDF file
            Storage::put($filename, $pdfContent);
            
            Log::info('Invoice PDF generated successfully', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
                'file_size' => strlen($pdfContent),
                'storage_path' => $filename
            ]);
            
            return $filename;
            
        } catch (\Exception $e) {
            Log::error('Failed to generate invoice PDF', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw new \Exception('Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Generate professional invoice HTML template.
     */
    protected function generateInvoiceHtml(Invoice $invoice): string
    {
        $customerInfo = $invoice->getCustomerInfo();
        $companyInfo = $this->getCompanyInfo();
        $lineItems = $invoice->line_items ?? [];
        $paymentHistory = $invoice->getPaymentHistory();
        
        return view('pdf.invoice', compact(
            'invoice',
            'customerInfo', 
            'companyInfo',
            'lineItems',
            'paymentHistory'
        ))->render();
    }

    /**
     * Get company information for invoice header.
     */
    protected function getCompanyInfo(): array
    {
        return [
            'name' => config('app.name'),
            'logo' => config('app.logo_path'),
            'address' => [
                'line1' => config('company.address.line1'),
                'line2' => config('company.address.line2'),
                'city' => config('company.address.city'),
                'state' => config('company.address.state'),
                'postal_code' => config('company.address.postal_code'),
                'country' => config('company.address.country'),
            ],
            'contact' => [
                'email' => config('company.contact.email'),
                'phone' => config('company.contact.phone'),
                'website' => config('company.contact.website'),
            ],
            'tax_info' => [
                'tax_id' => config('company.tax.id'),
                'vat_number' => config('company.tax.vat_number'),
            ],
            'bank_info' => [
                'bank_name' => config('company.bank.name'),
                'account_number' => config('company.bank.account_number'),
                'routing_number' => config('company.bank.routing_number'),
                'iban' => config('company.bank.iban'),
                'swift' => config('company.bank.swift'),
            ]
        ];
    }

    /**
     * Generate invoice HTML with professional styling.
     */
    protected function generateInvoiceTemplate(Invoice $invoice, array $customerInfo, array $companyInfo): string
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Invoice ' . $invoice->number . '</title>
            <style>
                body {
                    font-family: "Helvetica", Arial, sans-serif;
                    margin: 0;
                    padding: 20px;
                    font-size: 14px;
                    line-height: 1.6;
                    color: #333;
                    background: #fff;
                }
                
                .invoice-header {
                    display: table;
                    width: 100%;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #2563eb;
                    padding-bottom: 20px;
                }
                
                .company-info {
                    display: table-cell;
                    width: 50%;
                    vertical-align: top;
                }
                
                .company-logo {
                    max-width: 200px;
                    max-height: 80px;
                    margin-bottom: 10px;
                }
                
                .company-name {
                    font-size: 24px;
                    font-weight: bold;
                    color: #2563eb;
                    margin-bottom: 5px;
                }
                
                .invoice-details {
                    display: table-cell;
                    width: 50%;
                    text-align: right;
                    vertical-align: top;
                }
                
                .invoice-number {
                    font-size: 28px;
                    font-weight: bold;
                    color: #2563eb;
                    margin-bottom: 5px;
                }
                
                .invoice-date {
                    font-size: 16px;
                    color: #666;
                    margin-bottom: 10px;
                }
                
                .status-badge {
                    display: inline-block;
                    padding: 8px 16px;
                    border-radius: 20px;
                    font-weight: bold;
                    font-size: 12px;
                    text-transform: uppercase;
                }
                
                .status-paid {
                    background-color: #dcfce7;
                    color: #166534;
                }
                
                .status-open {
                    background-color: #fef3c7;
                    color: #92400e;
                }
                
                .status-overdue {
                    background-color: #fee2e2;
                    color: #991b1b;
                }
                
                .billing-info {
                    display: table;
                    width: 100%;
                    margin-bottom: 30px;
                }
                
                .billing-from, .billing-to {
                    display: table-cell;
                    width: 50%;
                    vertical-align: top;
                    padding: 0 20px 0 0;
                }
                
                .billing-to {
                    padding: 0 0 0 20px;
                }
                
                .billing-section-title {
                    font-weight: bold;
                    font-size: 16px;
                    color: #2563eb;
                    margin-bottom: 10px;
                    border-bottom: 1px solid #e5e7eb;
                    padding-bottom: 5px;
                }
                
                .line-items {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 30px;
                    border: 1px solid #e5e7eb;
                }
                
                .line-items th {
                    background-color: #f9fafb;
                    padding: 12px;
                    text-align: left;
                    font-weight: bold;
                    border-bottom: 2px solid #e5e7eb;
                    color: #374151;
                }
                
                .line-items td {
                    padding: 12px;
                    border-bottom: 1px solid #e5e7eb;
                }
                
                .line-items tr:last-child td {
                    border-bottom: none;
                }
                
                .line-items .quantity {
                    text-align: center;
                    width: 80px;
                }
                
                .line-items .unit-price,
                .line-items .total-price {
                    text-align: right;
                    width: 120px;
                }
                
                .totals-section {
                    float: right;
                    width: 300px;
                    margin-bottom: 30px;
                }
                
                .totals-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                
                .totals-table td {
                    padding: 8px 12px;
                    border-bottom: 1px solid #e5e7eb;
                }
                
                .totals-table .label {
                    text-align: left;
                    font-weight: bold;
                }
                
                .totals-table .amount {
                    text-align: right;
                }
                
                .totals-table .total-row {
                    font-size: 18px;
                    font-weight: bold;
                    color: #2563eb;
                    border-top: 2px solid #2563eb;
                    border-bottom: 2px solid #2563eb;
                }
                
                .payment-info {
                    clear: both;
                    margin-top: 40px;
                    padding: 20px;
                    background-color: #f9fafb;
                    border: 1px solid #e5e7eb;
                    border-radius: 8px;
                }
                
                .payment-info h3 {
                    color: #2563eb;
                    margin-bottom: 15px;
                }
                
                .payment-methods {
                    display: table;
                    width: 100%;
                }
                
                .payment-method {
                    display: table-cell;
                    width: 50%;
                    vertical-align: top;
                    padding-right: 20px;
                }
                
                .footer {
                    margin-top: 50px;
                    padding-top: 20px;
                    border-top: 1px solid #e5e7eb;
                    text-align: center;
                    font-size: 12px;
                    color: #6b7280;
                }
                
                .notes {
                    margin-top: 30px;
                    padding: 20px;
                    background-color: #fffbeb;
                    border: 1px solid #fbbf24;
                    border-radius: 8px;
                }
                
                .notes h4 {
                    color: #92400e;
                    margin-bottom: 10px;
                }
                
                @media print {
                    body {
                        margin: 0;
                        padding: 15px;
                    }
                    
                    .invoice-header {
                        page-break-inside: avoid;
                    }
                    
                    .line-items {
                        page-break-inside: auto;
                    }
                    
                    .line-items tr {
                        page-break-inside: avoid;
                        page-break-after: auto;
                    }
                    
                    .totals-section {
                        page-break-inside: avoid;
                    }
                }
            </style>
        </head>
        <body>';

        // Invoice Header
        $html .= '<div class="invoice-header">';
        $html .= '<div class="company-info">';
        if (!empty($companyInfo['logo'])) {
            $html .= '<img src="' . $companyInfo['logo'] . '" class="company-logo" alt="Company Logo">';
        }
        $html .= '<div class="company-name">' . htmlspecialchars($companyInfo['name']) . '</div>';
        $html .= '<div>' . htmlspecialchars($companyInfo['address']['line1']) . '</div>';
        if (!empty($companyInfo['address']['line2'])) {
            $html .= '<div>' . htmlspecialchars($companyInfo['address']['line2']) . '</div>';
        }
        $html .= '<div>' . htmlspecialchars($companyInfo['address']['city']) . ', ' . htmlspecialchars($companyInfo['address']['state']) . ' ' . htmlspecialchars($companyInfo['address']['postal_code']) . '</div>';
        $html .= '<div>' . htmlspecialchars($companyInfo['address']['country']) . '</div>';
        $html .= '<div>Email: ' . htmlspecialchars($companyInfo['contact']['email']) . '</div>';
        $html .= '<div>Phone: ' . htmlspecialchars($companyInfo['contact']['phone']) . '</div>';
        $html .= '</div>';

        $html .= '<div class="invoice-details">';
        $html .= '<div class="invoice-number">Invoice #' . htmlspecialchars($invoice->number) . '</div>';
        $html .= '<div class="invoice-date">Date: ' . $invoice->created_at->format('F d, Y') . '</div>';
        if ($invoice->due_date) {
            $html .= '<div class="invoice-date">Due Date: ' . $invoice->due_date->format('F d, Y') . '</div>';
        }
        
        $statusClass = match($invoice->status) {
            'paid' => 'status-paid',
            'open' => $invoice->isOverdue() ? 'status-overdue' : 'status-open',
            default => 'status-open'
        };
        
        $html .= '<div class="status-badge ' . $statusClass . '">' . ucfirst($invoice->status);
        if ($invoice->isOverdue()) {
            $html .= ' (Overdue)';
        }
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        // Billing Information
        $html .= '<div class="billing-info">';
        $html .= '<div class="billing-from">';
        $html .= '<div class="billing-section-title">From</div>';
        $html .= '<div><strong>' . htmlspecialchars($companyInfo['name']) . '</strong></div>';
        $html .= '<div>' . htmlspecialchars($companyInfo['address']['line1']) . '</div>';
        if (!empty($companyInfo['address']['line2'])) {
            $html .= '<div>' . htmlspecialchars($companyInfo['address']['line2']) . '</div>';
        }
        $html .= '<div>' . htmlspecialchars($companyInfo['address']['city']) . ', ' . htmlspecialchars($companyInfo['address']['state']) . ' ' . htmlspecialchars($companyInfo['address']['postal_code']) . '</div>';
        if (!empty($companyInfo['tax_info']['tax_id'])) {
            $html .= '<div>Tax ID: ' . htmlspecialchars($companyInfo['tax_info']['tax_id']) . '</div>';
        }
        $html .= '</div>';

        $html .= '<div class="billing-to">';
        $html .= '<div class="billing-section-title">Bill To</div>';
        $html .= '<div><strong>' . htmlspecialchars($customerInfo['name']) . '</strong></div>';
        $html .= '<div>' . htmlspecialchars($customerInfo['email']) . '</div>';
        if (!empty($customerInfo['address']['line1'])) {
            $html .= '<div>' . htmlspecialchars($customerInfo['address']['line1']) . '</div>';
            if (!empty($customerInfo['address']['line2'])) {
                $html .= '<div>' . htmlspecialchars($customerInfo['address']['line2']) . '</div>';
            }
            $html .= '<div>' . htmlspecialchars($customerInfo['address']['city']) . ', ' . htmlspecialchars($customerInfo['address']['state']) . ' ' . htmlspecialchars($customerInfo['address']['postal_code']) . '</div>';
        }
        if (!empty($customerInfo['tax_id'])) {
            $html .= '<div>Tax ID: ' . htmlspecialchars($customerInfo['tax_id']) . '</div>';
        }
        $html .= '</div>';
        $html .= '</div>';

        // Line Items
        $html .= '<table class="line-items">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th>Description</th>';
        $html .= '<th class="quantity">Quantity</th>';
        $html .= '<th class="unit-price">Unit Price</th>';
        $html .= '<th class="total-price">Total</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';

        $lineItems = $invoice->line_items ?? [];
        if (empty($lineItems)) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($invoice->description ?: 'Service') . '</td>';
            $html .= '<td class="quantity">1</td>';
            $html .= '<td class="unit-price">' . $invoice->formatted_total . '</td>';
            $html .= '<td class="total-price">' . $invoice->formatted_total . '</td>';
            $html .= '</tr>';
        } else {
            foreach ($lineItems as $item) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($item['description'] ?? 'Item') . '</td>';
                $html .= '<td class="quantity">' . ($item['quantity'] ?? 1) . '</td>';
                $html .= '<td class="unit-price">' . $this->formatMoney($item['unit_price'] ?? 0, $invoice->currency) . '</td>';
                $html .= '<td class="total-price">' . $this->formatMoney($item['total'] ?? 0, $invoice->currency) . '</td>';
                $html .= '</tr>';
            }
        }

        $html .= '</tbody>';
        $html .= '</table>';

        // Totals Section
        $html .= '<div class="totals-section">';
        $html .= '<table class="totals-table">';
        
        if ($invoice->subtotal && $invoice->subtotal != $invoice->total) {
            $html .= '<tr>';
            $html .= '<td class="label">Subtotal:</td>';
            $html .= '<td class="amount">' . $invoice->formatted_subtotal . '</td>';
            $html .= '</tr>';
        }
        
        if ($invoice->total_discount_amount > 0) {
            $html .= '<tr>';
            $html .= '<td class="label">Discount:</td>';
            $html .= '<td class="amount">-' . $invoice->formatted_total_discount . '</td>';
            $html .= '</tr>';
        }
        
        if ($invoice->tax && $invoice->tax > 0) {
            $html .= '<tr>';
            $html .= '<td class="label">Tax:</td>';
            $html .= '<td class="amount">' . $invoice->formatted_tax . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '<tr class="total-row">';
        $html .= '<td class="label">Total:</td>';
        $html .= '<td class="amount">' . $invoice->formatted_total . '</td>';
        $html .= '</tr>';
        
        if ($invoice->amount_paid > 0) {
            $html .= '<tr>';
            $html .= '<td class="label">Amount Paid:</td>';
            $html .= '<td class="amount">' . $invoice->formatted_amount_paid . '</td>';
            $html .= '</tr>';
            
            if ($invoice->amount_due > 0) {
                $html .= '<tr class="total-row">';
                $html .= '<td class="label">Amount Due:</td>';
                $html .= '<td class="amount">' . $invoice->formatted_amount_due . '</td>';
                $html .= '</tr>';
            }
        }
        
        $html .= '</table>';
        $html .= '</div>';

        // Payment Information
        if ($invoice->isOpen()) {
            $html .= '<div class="payment-info">';
            $html .= '<h3>Payment Information</h3>';
            $html .= '<div class="payment-methods">';
            
            if (!empty($companyInfo['bank_info']['bank_name'])) {
                $html .= '<div class="payment-method">';
                $html .= '<h4>Bank Transfer</h4>';
                $html .= '<div>Bank: ' . htmlspecialchars($companyInfo['bank_info']['bank_name']) . '</div>';
                if (!empty($companyInfo['bank_info']['account_number'])) {
                    $html .= '<div>Account: ' . htmlspecialchars($companyInfo['bank_info']['account_number']) . '</div>';
                }
                if (!empty($companyInfo['bank_info']['iban'])) {
                    $html .= '<div>IBAN: ' . htmlspecialchars($companyInfo['bank_info']['iban']) . '</div>';
                }
                $html .= '</div>';
            }
            
            $html .= '<div class="payment-method">';
            $html .= '<h4>Online Payment</h4>';
            $html .= '<div>Pay online at: ' . htmlspecialchars($companyInfo['contact']['website']) . '</div>';
            $html .= '<div>Or use the link provided in your email</div>';
            $html .= '</div>';
            
            $html .= '</div>';
            $html .= '</div>';
        }

        // Notes
        if ($invoice->notes) {
            $html .= '<div class="notes">';
            $html .= '<h4>Notes</h4>';
            $html .= '<div>' . nl2br(htmlspecialchars($invoice->notes)) . '</div>';
            $html .= '</div>';
        }

        // Footer
        $html .= '<div class="footer">';
        $html .= '<div>Thank you for your business!</div>';
        $html .= '<div>Generated on ' . now()->format('F d, Y \a\t g:i A') . '</div>';
        if (!empty($companyInfo['contact']['website'])) {
            $html .= '<div>' . htmlspecialchars($companyInfo['contact']['website']) . '</div>';
        }
        $html .= '</div>';

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Format money amount with currency symbol.
     */
    protected function formatMoney(float $amount, string $currency = 'USD'): string
    {
        $symbol = match(strtoupper($currency)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            default => strtoupper($currency) . ' ',
        };

        $decimals = in_array(strtoupper($currency), ['JPY', 'KRW']) ? 0 : 2;
        
        return $symbol . number_format($amount, $decimals);
    }

    /**
     * Get PDF download response.
     */
    public function getDownloadResponse(Invoice $invoice): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        if (!$invoice->pdf_storage_path || !Storage::exists($invoice->pdf_storage_path)) {
            $pdfPath = $this->generatePdf($invoice);
            $invoice->update([
                'pdf_storage_path' => $pdfPath,
                'pdf_file_size' => Storage::size($pdfPath),
                'pdf_generated_at' => now()
            ]);
        }

        return Storage::download(
            $invoice->pdf_storage_path,
            $invoice->getDownloadFilename(),
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Generate PDF and return as stream.
     */
    public function streamPdf(Invoice $invoice): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $html = $this->generateInvoiceTemplate($invoice, $invoice->getCustomerInfo(), $this->getCompanyInfo());
        
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();
        
        return response()->stream(
            function () {
                echo $this->dompdf->output();
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $invoice->getDownloadFilename() . '"',
            ]
        );
    }
}