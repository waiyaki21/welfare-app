<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // First hard-delete any soft-deleted members so no orphaned data remains
            \DB::table('members')->whereNotNull('deleted_at')->delete();
            $table->dropSoftDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
};
