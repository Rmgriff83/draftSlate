<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_matchups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained();
            $table->integer('week');
            $table->foreignId('home_team_id')->constrained('league_memberships');
            $table->foreignId('away_team_id')->constrained('league_memberships');
            $table->integer('home_score')->nullable();
            $table->integer('away_score')->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('league_memberships');
            $table->boolean('is_tie')->default(false);
            $table->boolean('is_playoff')->default(false);
            $table->string('playoff_round', 30)->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed'])->default('scheduled');
            $table->timestamps();

            $table->index(['league_id', 'week']);
            $table->unique(['league_id', 'week', 'home_team_id']);
            $table->unique(['league_id', 'week', 'away_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_matchups');
    }
};
