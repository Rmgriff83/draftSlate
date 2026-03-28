<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('career_gold_medals')->default(0)->after('career_player_prop_hits');
            $table->unsignedInteger('career_silver_medals')->default(0)->after('career_gold_medals');
            $table->unsignedInteger('career_bronze_medals')->default(0)->after('career_silver_medals');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['career_gold_medals', 'career_silver_medals', 'career_bronze_medals']);
        });
    }
};
