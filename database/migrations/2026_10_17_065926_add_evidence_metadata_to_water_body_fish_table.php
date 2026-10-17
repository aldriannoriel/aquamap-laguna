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
        Schema::table('water_body_fish', function (Blueprint $table) {
            $table->string('evidence_type', 32)->nullable();
            $table->string('sampling_period', 100)->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('water_body_fish', function (Blueprint $table) {
            $table->dropColumn(['evidence_type', 'sampling_period', 'publication_year']);
        });
    }
};
