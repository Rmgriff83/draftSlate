<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('draft_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained();
            $table->foreignId('slate_pool_id')->constrained();
            $table->integer('week');
            $table->enum('status', ['lobby', 'preparing', 'active', 'completed'])->default('lobby');
            $table->json('draft_order');
            $table->json('draft_order_weights')->nullable();
            $table->integer('current_round')->default(1);
            $table->integer('current_pick_index')->default(0);
            $table->foreignId('current_drafter_id')->nullable()->constrained('league_memberships');
            $table->timestamp('current_pick_started_at')->nullable();
            $table->integer('total_rounds');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_states');
    }
};
