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
        Schema::create('snmp_contexts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedInteger('device_id');
            $table->string('context', 128);
            $table->string('type', 64);
            $table->string('v2_format', 64)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snmp_contexts');
    }
};
