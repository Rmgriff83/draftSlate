<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->tinyInteger('bench_slots')->default(2)->after('aggregate_odds_floor');
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn('bench_slots');
        });
    }
};
