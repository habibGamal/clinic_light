<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('shift_id')->nullable()->after('invoice_id')->constrained('shifts')->nullOnDelete();
            $table->dateTime('paid_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['shift_id']);
            $table->dropColumn('shift_id');
            $table->dateTime('paid_at')->nullable(false)->change();
        });
    }
};
