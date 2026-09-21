<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Add online payment tracking columns to orders.
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_reference')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
        });
    }

    // Remove the payment columns again.
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_reference']);
            $table->dropColumn(['payment_status', 'payment_reference', 'paid_at']);
        });
    }
};
