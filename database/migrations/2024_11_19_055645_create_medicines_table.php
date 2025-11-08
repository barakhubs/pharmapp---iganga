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
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('stock_category_id')->constrained()->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');
            $table->foreignId('branch_id')->constrained()->CascadeOnDelete();
            $table->string('description')->nullable();
            $table->string('buying_price');
            $table->string('selling_price')->nullable();
            $table->string('stock_quantity');
            $table->string('expiry_date')->nullable();
            $table->string('batch_no')->nullable();
            $table->string('manufacture_date')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicines');
    }
};

