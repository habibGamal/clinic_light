<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_id')->constrained('patient_visits')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->cascadeOnDelete();
            $table->string('type')->default('payment');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->default('cash');
            $table->dateTime('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
