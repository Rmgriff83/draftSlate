<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slate_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained();
            $table->integer('week');
            $table->timestamp('snapshot_at');
            $table->enum('status', ['building', 'ready', 'draft_active', 'draft_complete'])
                  ->default('building');
            $table->json('api_metadata')->nullable();
            $table->timestamps();

            $table->unique(['league_id', 'week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slate_pools');
    }
};
