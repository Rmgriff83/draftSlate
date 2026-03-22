<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pick_selections', function (Blueprint $table) {
            $table->json('result_data')->nullable()->after('outcome');
        });

        DB::statement("ALTER TABLE pick_selections MODIFY outcome ENUM('pending','hit','miss','void','push') DEFAULT 'pending'");

        DB::statement("ALTER TABLE slate_pools MODIFY status ENUM('building','ready','draft_active','draft_complete','active','completed') DEFAULT 'building'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE slate_pools MODIFY status ENUM('building','ready','draft_active','draft_complete') DEFAULT 'building'");

        DB::statement("ALTER TABLE pick_selections MODIFY outcome ENUM('pending','hit','miss','void') DEFAULT 'pending'");

        Schema::table('pick_selections', function (Blueprint $table) {
            $table->dropColumn('result_data');
        });
    }
};
