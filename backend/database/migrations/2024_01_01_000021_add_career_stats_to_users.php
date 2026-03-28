<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('career_picks_graded')->default(0);
            $table->unsignedInteger('career_picks_hit')->default(0);
            $table->unsignedInteger('career_moneyline_hits')->default(0);
            $table->unsignedInteger('career_spread_hits')->default(0);
            $table->unsignedInteger('career_total_hits')->default(0);
            $table->unsignedInteger('career_player_prop_hits')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'career_picks_graded',
                'career_picks_hit',
                'career_moneyline_hits',
                'career_spread_hits',
                'career_total_hits',
                'career_player_prop_hits',
            ]);
        });
    }
};
