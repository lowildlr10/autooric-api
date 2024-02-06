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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->unique()->nullable();
            $table->uuid('position_id');
            $table->foreign('position_id')
                ->references('id')
                ->on('positions');
            $table->uuid('designation_id');
            $table->foreign('designation_id')
                ->references('id')
                ->on('designations');
            $table->uuid('station_id');
            $table->foreign('station_id')
                ->references('id')
                ->on('stations');
            $table->string('username')->unique();
            $table->string('password');
            $table->enum('role', ['admin', 'staff'])
                ->default('staff');
            $table->boolean('is_active')
                ->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
