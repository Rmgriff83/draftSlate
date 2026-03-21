<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pick_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slate_pool_id')->constrained();
            $table->string('external_id', 100);
            $table->text('description');
            $table->string('pick_type', 30);
            $table->string('category', 50)->nullable();
            $table->string('player_name', 100)->nullable();
            $table->string('home_team', 50);
            $table->string('away_team', 50);
            $table->string('game_display', 100);
            $table->timestamp('game_time');
            $table->string('sport', 20)->default('nfl');
            $table->integer('snapshot_odds');
            $table->integer('current_odds')->nullable();
            $table->timestamp('odds_updated_at')->nullable();
            $table->enum('outcome', ['pending', 'hit', 'miss', 'void'])->default('pending');
            $table->boolean('is_drafted')->default(false);
            $table->timestamps();

            $table->index('slate_pool_id');
            $table->index('game_time');
            $table->index('outcome');
            $table->index(['slate_pool_id', 'is_drafted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_selections');
    }
};
