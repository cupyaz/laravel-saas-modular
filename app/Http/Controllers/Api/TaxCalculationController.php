<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TaxCalculationController extends Controller
{
    /**
     * Calculate tax for testing purposes.
     */
    public function calculateTax(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required',
            'country_code' => 'required|string|size:2',
            'state_code' => 'nullable|string|max:10',
            'postal_code' => 'nullable|string|max:20',
        ]);

        // Mock tax calculation per test
        $subtotal = 29.99;
        $taxAmount = 0;
        $taxRate = 0;

        // Simulazione calcolo tasse
        switch ($request->country_code) {
            case 'US':
                $taxRate = $request->state_code === 'CA' ? 0.0725 : 0.06;
                $taxAmount = $subtotal * $taxRate;
                break;
            case 'DE':
            case 'IT':
            case 'FR':
                $taxRate = 0.20; // IVA EU
                $taxAmount = $subtotal * $taxRate;
                break;
        }

        return response()->json([
            'subtotal' => $subtotal,
            'tax' => [
                'amount' => round($taxAmount, 2),
                'formatted_amount' => '$' . number_format($taxAmount, 2),
                'rate' => $taxRate,
                'jurisdiction' => $request->country_code,
                'type' => in_array($request->country_code, ['DE', 'IT', 'FR']) ? 'vat' : 'sales_tax',
            ],
            'total' => round($subtotal + $taxAmount, 2),
            'currency' => 'USD',
        ]);
    }
}