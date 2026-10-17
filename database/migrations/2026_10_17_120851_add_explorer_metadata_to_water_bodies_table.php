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
        Schema::table('water_bodies', function (Blueprint $table) {
            $table->string('category', 20)->default('lake');
            $table->string('site_type', 20)->default('water_body');
            $table->string('spring_type', 40)->nullable();
            $table->string('official_url', 2048)->nullable();
            $table->json('aliases')->nullable();
            $table->json('geography')->nullable();
            $table->json('research_references')->nullable();
            $table->json('image')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('water_bodies', function (Blueprint $table) {
            $table->dropColumn(['category', 'site_type', 'spring_type', 'official_url', 'aliases', 'geography', 'research_references', 'image']);
        });
    }
};
