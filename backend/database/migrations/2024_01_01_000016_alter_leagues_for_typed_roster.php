<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leagues', function (Blueprint $table) {
            $table->json('roster_config')->nullable()->after('bench_slots');
            $table->json('sports')->nullable()->after('roster_config');
            $table->integer('aggregate_odds_floor')->default(-250)->after('sports');
        });

        // Populate defaults for existing rows
        DB::table('leagues')->whereNull('roster_config')->update([
            'roster_config' => json_encode(['moneyline' => 1, 'spread' => 1, 'total' => 1, 'player_prop' => 2]),
        ]);
        DB::table('leagues')->whereNull('sports')->update([
            'sports' => json_encode(['basketball_nba']),
        ]);

        Schema::table('leagues', function (Blueprint $table) {
            $table->dropIndex(['sport']);
            $table->dropColumn([
                'starter_slots',
                'bench_slots',
                'odds_mode',
                'global_odds_floor',
                'slot_bands',
                'bench_floor',
                'sport',
            ]);
        });

        Schema::table('slate_picks', function (Blueprint $table) {
            $table->string('slot_type', 30)->nullable()->after('slot_number');
        });
    }

    public function down(): void
    {
        Schema::table('slate_picks', function (Blueprint $table) {
            $table->dropColumn('slot_type');
        });

        Schema::table('leagues', function (Blueprint $table) {
            $table->integer('starter_slots')->default(5)->after('payout_structure');
            $table->integer('bench_slots')->default(3)->after('starter_slots');
            $table->enum('odds_mode', ['global_floor', 'per_slot_bands'])->default('global_floor')->after('bench_slots');
            $table->integer('global_odds_floor')->default(-250)->after('odds_mode');
            $table->json('slot_bands')->nullable()->after('global_odds_floor');
            $table->integer('bench_floor')->nullable()->after('slot_bands');
            $table->string('sport', 20)->default('nfl')->after('current_week');
            $table->index('sport');
        });

        Schema::table('leagues', function (Blueprint $table) {
            $table->dropColumn(['roster_config', 'sports', 'aggregate_odds_floor']);
        });
    }
};
