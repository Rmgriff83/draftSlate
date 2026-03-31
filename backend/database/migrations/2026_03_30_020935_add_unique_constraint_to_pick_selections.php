<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clean up undrafted duplicates before adding constraint
        DB::statement('
            DELETE ps FROM pick_selections ps
            INNER JOIN (
                SELECT external_id, slate_pool_id, MIN(id) AS keep_id
                FROM pick_selections
                GROUP BY external_id, slate_pool_id
                HAVING COUNT(*) > 1
            ) dups ON ps.external_id = dups.external_id
                  AND ps.slate_pool_id = dups.slate_pool_id
                  AND ps.id != dups.keep_id
            WHERE ps.is_drafted = 0
        ');

        // For remaining duplicates (both drafted by different users),
        // disambiguate by appending the row id to the duplicate's external_id
        $stillDuped = DB::select('
            SELECT ps.id, ps.external_id
            FROM pick_selections ps
            INNER JOIN (
                SELECT external_id, slate_pool_id, MIN(id) AS keep_id
                FROM pick_selections
                GROUP BY external_id, slate_pool_id
                HAVING COUNT(*) > 1
            ) dups ON ps.external_id = dups.external_id
                  AND ps.slate_pool_id = dups.slate_pool_id
                  AND ps.id != dups.keep_id
        ');

        foreach ($stillDuped as $row) {
            DB::table('pick_selections')
                ->where('id', $row->id)
                ->update(['external_id' => $row->external_id . '_dup_' . $row->id]);
        }

        Schema::table('pick_selections', function (Blueprint $table) {
            $table->unique(['slate_pool_id', 'external_id'], 'pick_selections_pool_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pick_selections', function (Blueprint $table) {
            $table->dropUnique('pick_selections_pool_external_unique');
        });
    }
};
