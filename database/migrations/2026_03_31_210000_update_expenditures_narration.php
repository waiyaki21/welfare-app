<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenditures', function (Blueprint $table): void {
            if (Schema::hasColumn('expenditures', 'month')) {
                $table->dropIndex(['month']);
                $table->dropColumn('month');
            }

            if (!Schema::hasColumn('expenditures', 'narration')) {
                $table->string('narration')->nullable()->after('financial_year_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenditures', function (Blueprint $table): void {
            if (Schema::hasColumn('expenditures', 'narration')) {
                $table->dropColumn('narration');
            }

            if (!Schema::hasColumn('expenditures', 'month')) {
                $table->unsignedTinyInteger('month')->nullable()->after('amount');
                $table->index('month');
            }
        });
    }
};
