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
        Schema::dropIfExists('credential_default_scopes');

        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('credential_override');
        });

        Schema::table('credentials', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->boolean('credential_override')->default(false);
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
};
