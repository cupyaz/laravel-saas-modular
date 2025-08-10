<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->morphs('billable');
            $table->string('stripe_invoice_id')->unique();
            $table->string('number');
            $table->string('status');
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('amount_due', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 3);
            $table->string('description')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->timestamp('invoice_pdf')->nullable();
            $table->timestamp('hosted_invoice_url')->nullable();
            $table->json('line_items')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['billable_id', 'billable_type']);
            $table->index(['stripe_invoice_id']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};