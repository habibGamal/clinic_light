<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('patient_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('referring_doctor_id')->nullable()->constrained('referring_doctors')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('shifts');
            $table->integer('visit_number')->default(1);
            $table->dateTime('visit_date');
            $table->string('status')->default('waiting');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
