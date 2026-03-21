<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slate_picks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_membership_id')->constrained();
            $table->foreignId('pick_selection_id')->constrained();
            $table->foreignId('slate_pool_id')->constrained();
            $table->integer('week');
            $table->enum('position', ['starter', 'bench'])->default('starter');
            $table->integer('slot_number');
            $table->integer('drafted_odds');
            $table->integer('locked_odds')->nullable();
            $table->integer('odds_drift')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->integer('draft_round');
            $table->integer('draft_pick_number');
            $table->timestamps();

            $table->unique(['league_membership_id', 'pick_selection_id']);
            $table->index(['league_membership_id', 'week']);
            $table->index(['slate_pool_id', 'week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slate_picks');
    }
};
