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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // entry, exit, adjustment, transfer
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->foreignId('warehouse_id')->constrained()->onDelete('restrict');
            $table->integer('quantity');
            $table->integer('previous_quantity')->default(0);
            $table->integer('new_quantity')->default(0);
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->foreignId('supplier_id')->nullable()->constrained()->onDelete('set null');
            $table->text('reason')->nullable();
            $table->string('reference_number')->nullable();
            $table->date('expiration_date')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index(['product_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
