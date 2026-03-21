<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('league_id')->constrained();
            $table->string('team_name', 50);
            $table->string('team_logo_url')->nullable();
            $table->integer('wins')->default(0);
            $table->integer('losses')->default(0);
            $table->integer('ties')->default(0);
            $table->integer('total_correct_picks')->default(0);
            $table->integer('total_opponent_correct_picks')->default(0);
            $table->integer('playoff_seed')->nullable();
            $table->enum('playoff_bracket', ['winners', 'losers'])->nullable();
            $table->integer('final_position')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'league_id']);
            $table->index('league_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_memberships');
    }
};
