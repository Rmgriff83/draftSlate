<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->tinyInteger('matchup_duration_days')->default(7)->after('draft_timezone');
            $table->integer('total_matchups')->default(14)->after('pick_timer_seconds');
            $table->integer('min_hours_before_game')->default(1)->after('total_matchups');
            $table->date('season_start_date')->nullable()->after('min_hours_before_game');

            $table->dropColumn(['draft_day', 'regular_season_weeks']);
        });
    }

    public function down(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->tinyInteger('draft_day')->default(2)->after('draft_timezone');
            $table->integer('regular_season_weeks')->default(14)->after('pick_timer_seconds');

            $table->dropColumn(['matchup_duration_days', 'total_matchups', 'min_hours_before_game', 'season_start_date']);
        });
    }
};
