<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('visit_service_selected_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_service_id')->constrained('visit_services')->cascadeOnDelete();
            $table->foreignId('service_option_id')->constrained('service_options');
            $table->decimal('additional_price', 10, 2)->default(0);
            $table->timestamps();
        });
    }
};
