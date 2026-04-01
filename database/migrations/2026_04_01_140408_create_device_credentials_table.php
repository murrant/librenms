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
        Schema::create('device_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('device_id');
            $table->foreignId('credential_id')->constrained('credentials')->cascadeOnDelete();
            $table->string('credential_type');
            $table->unique(['device_id', 'credential_type']);

            $table->foreign('device_id')->references('device_id')->on('devices')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_credentials');
    }
};
