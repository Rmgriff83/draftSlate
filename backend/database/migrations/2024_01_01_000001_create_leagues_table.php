<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commissioner_id')->constrained('users');
            $table->string('name', 100);
            $table->enum('type', ['public', 'private'])->default('public');
            $table->enum('state', ['pending', 'active', 'playoffs', 'completed', 'cancelled'])
                  ->default('pending');
            $table->integer('max_teams');
            $table->decimal('buy_in', 8, 2);
            $table->json('payout_structure');
            $table->integer('starter_slots')->default(5);
            $table->integer('bench_slots')->default(3);
            $table->enum('odds_mode', ['global_floor', 'per_slot_bands'])->default('global_floor');
            $table->integer('global_odds_floor')->default(-250);
            $table->json('slot_bands')->nullable();
            $table->integer('bench_floor')->nullable();
            $table->tinyInteger('draft_day')->default(2);
            $table->time('draft_time')->default('20:00:00');
            $table->string('draft_timezone', 50)->default('America/New_York');
            $table->integer('pick_timer_seconds')->default(60);
            $table->integer('regular_season_weeks')->default(14);
            $table->enum('playoff_format', ['A', 'B', 'C', 'D'])->default('B');
            $table->string('invite_code', 12)->nullable()->unique();
            $table->integer('current_week')->default(0);
            $table->string('sport', 20)->default('nfl');
            $table->timestamps();

            $table->index('state');
            $table->index('type');
            $table->index('sport');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leagues');
    }
};
