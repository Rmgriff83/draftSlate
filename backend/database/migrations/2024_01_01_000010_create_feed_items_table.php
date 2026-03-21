<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('event_type', 50);
            $table->text('message');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['league_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_items');
    }
};
