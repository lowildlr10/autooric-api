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
        Schema::create('signatories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('signatory_name');
            $table->uuid('designation_id');
            $table->foreign('designation_id')
                ->references('id')
                ->on('designations');
            $table->uuid('station_id');
            $table->foreign('station_id')
                ->references('id')
                ->on('stations');
            $table->json('report')->default(NULL);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signatories');
    }
};
