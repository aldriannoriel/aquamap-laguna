<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('report_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pollution_report_id')->constrained()->restrictOnDelete();
            $table->foreignId('administrator_id')->constrained('users')->restrictOnDelete();
            $table->string('previous_status', 20);
            $table->string('new_status', 20);
            $table->text('review_note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_reviews');
    }
};
