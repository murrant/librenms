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
        Schema::create('transceivers', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->bigInteger('device_id');
            $table->bigInteger('port_id');
            $table->string('index');
            $table->string('type', 16);
            $table->string('vendor', 16);
            $table->string('oui', 16);
            $table->string('model', 16);
            $table->string('revision', 4);
            $table->string('serial', 16);
            $table->date('date');
            $table->boolean('ddm')->default(0);
            $table->string('encoding', 16);
            $table->integer('distance');
            $table->string('connector', 16);
            $table->smallInteger('channels');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transceivers');
    }
};
