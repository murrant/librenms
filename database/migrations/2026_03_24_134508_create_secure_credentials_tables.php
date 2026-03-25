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
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('type');
            $table->unsignedInteger('version')->default(1);
            $table->text('data');
            $table->timestamps();
        });

        Schema::create('credential_device', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('device_id');
            $table->foreign('device_id')->references('device_id')->on('devices')->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->unique(['credential_id', 'device_id']);
        });

        Schema::create('credential_default_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credential_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(0);
            $table->enum('scope', ['global', 'device_group', 'poller_group']);
            $table->unsignedBigInteger('mapping_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credential_default_scopes');
        Schema::dropIfExists('credential_device');
        Schema::dropIfExists('credentials');
    }
};
