<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odds_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pick_selection_id')->constrained()->cascadeOnDelete();
            $table->integer('odds');
            $table->decimal('line', 5, 1)->nullable();
            $table->timestamp('captured_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pick_selection_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odds_snapshots');
    }
};
