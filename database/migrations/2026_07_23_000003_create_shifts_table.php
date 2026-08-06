<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('opening_balance', 10, 2)->default(0);
            $table->decimal('closing_balance', 10, 2)->nullable();
            $table->decimal('actual_cash', 10, 2)->nullable();
            $table->decimal('difference', 10, 2)->nullable();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
        });
    }
};
