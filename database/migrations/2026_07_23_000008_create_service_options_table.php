<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('service_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('option_group_id')->constrained('service_option_groups')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('additional_price', 10, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
