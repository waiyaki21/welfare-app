<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_financials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_year_id')->constrained()->cascadeOnDelete();
            $table->decimal('contributions_brought_forward', 12, 2)->default(0);
            $table->decimal('contributions_carried_forward', 12, 2)->default(0);
            $table->decimal('total_welfare', 12, 2)->default(0);
            $table->decimal('development', 12, 2)->default(0);
            $table->decimal('welfare_owing', 12, 2)->default(0); // can be negative (deficit)
            $table->decimal('total_investment', 12, 2)->default(0);
            $table->decimal('pct_share', 8, 6)->default(0);      // investment % of total pool
            $table->text('notes')->nullable();
            $table->unique(['member_id', 'financial_year_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_financials');
    }
};
