<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mock_transactions', 'type')) {
            return;
        }

        $ids = DB::table('mock_transactions')
            ->where(function ($q) {
                $q->where('type', 'income')->orWhereNull('type');
            })
            ->orderBy('id')
            ->pluck('id');

        foreach ($ids as $id) {
            if ($id % 6 === 0) {
                DB::table('mock_transactions')->where('id', $id)->update(['type' => 'refund']);
            }
        }
    }

    public function down(): void
    {
        // no-op
    }
};
