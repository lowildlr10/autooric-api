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
        Schema::create('particulars', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id');
            $table->foreign('category_id')
                ->references('id')
                ->on('categories');
            $table->uuid('account_id');
            $table->foreign('account_id')
                ->references('id')
                ->on('accounts');
            $table->string('particular_name');
            $table->double('default_amount', 20, 2)->nullable();
            $table->integer('order_no')->unsigned();
            $table->boolean('coa_accounting')->default(false);
            $table->boolean('pnp_crame')->default(false);
            $table->boolean('firearms_registration')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('particulars');
    }
};
