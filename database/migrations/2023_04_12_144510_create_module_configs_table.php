<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateModuleConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('module_configs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('device_id');
            $table->string('module', 32);
            $table->text('config');
            $table->boolean('poll_enabled')->default(1);
            $table->timestamps();
            $table->unique(['device_id', 'module']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('module_configs');
    }
}
