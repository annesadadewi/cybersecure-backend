<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('mock_transactions', 'type')) {
                $table->string('type', 20)->default('income')->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mock_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('mock_transactions', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
