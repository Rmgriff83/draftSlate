<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draft_states', function (Blueprint $table) {
            $table->json('auto_draft_members')->nullable();
            $table->json('consecutive_auto_picks')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('draft_states', function (Blueprint $table) {
            $table->dropColumn(['auto_draft_members', 'consecutive_auto_picks']);
        });
    }
};
