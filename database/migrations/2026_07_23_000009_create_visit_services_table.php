<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('visit_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_id')->constrained('patient_visits')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services');
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->string('discount_type')->default('fixed');
            $table->decimal('discount_value', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }
};
