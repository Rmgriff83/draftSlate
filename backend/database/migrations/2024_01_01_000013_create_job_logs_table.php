<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_type', 80);
            $table->foreignId('league_id')->nullable()->constrained();
            $table->integer('week')->nullable();
            $table->enum('status', ['started', 'completed', 'failed']);
            $table->json('context')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['job_type', 'status']);
            $table->index(['league_id', 'week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_logs');
    }
};
