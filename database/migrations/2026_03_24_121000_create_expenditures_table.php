<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenditures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('financial_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 14, 2);
            $table->unsignedTinyInteger('month')->nullable();
            $table->timestamps();

            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenditures');
    }
};
