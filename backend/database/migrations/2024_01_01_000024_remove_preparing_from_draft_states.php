<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Move any 'preparing' rows to 'active'
        DB::table('draft_states')
            ->where('status', 'preparing')
            ->update(['status' => 'active']);

        // Remove 'preparing' from the enum
        DB::statement("ALTER TABLE draft_states MODIFY status ENUM('lobby','active','completed') DEFAULT 'lobby'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE draft_states MODIFY status ENUM('lobby','preparing','active','completed') DEFAULT 'lobby'");
    }
};
