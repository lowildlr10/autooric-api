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
        Schema::create('official_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('accountable_personnel_id');
            $table->foreign('accountable_personnel_id')
                ->references('id')
                ->on('users');
            $table->date('receipt_date');
            $table->date('deposited_date')->nullable();
            $table->date('cancelled_date')->nullable();
            $table->string('or_no')->unique();
            $table->uuid('payor_id');
            $table->foreign('payor_id')
                ->references('id')
                ->on('payors');
            $table->uuid('nature_collection_id');
            $table->foreign('nature_collection_id')
                ->references('id')
                ->on('particulars');
            $table->double('amount', 20, 2);
            $table->uuid('discount_id')->nullable();
            $table->foreign('discount_id')
                ->references('id')
                ->on('discounts');
            $table->double('deposit', 20, 2)->nullable();
            $table->string('amount_words');
            $table->string('card_no')->nullable();
            $table->enum('payment_mode', ['cash', 'check', 'money_order']);
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('official_receipts');
    }
};
