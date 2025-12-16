<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'services';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn($this->table, 'updated_at')) {
            Schema::table($this->table, function (Blueprint $table) {
                $table->timestamps();
            });
        }

        if (Schema::hasColumn($this->table, 'service_changed')) {
            if (DB::getDriverName() !== 'sqlite') {
                DB::table($this->table)->update(['updated_at' => DB::raw('FROM_UNIXTIME(`service_changed`)')]);
            }

            Schema::table($this->table, function (Blueprint $table) {
                $table->dropColumn('service_changed');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table($this->table, function (Blueprint $table) {
            $table->dropTimestamps();
            $table->unsignedInteger('service_changed')->default(0);
        });
    }
};
