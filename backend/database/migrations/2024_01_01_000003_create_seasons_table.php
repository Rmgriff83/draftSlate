<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained();
            $table->integer('year');
            $table->integer('start_week');
            $table->integer('end_week');
            $table->integer('playoff_start_week')->nullable();
            $table->enum('status', ['active', 'playoffs', 'completed'])->default('active');
            $table->timestamps();

            $table->unique(['league_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
