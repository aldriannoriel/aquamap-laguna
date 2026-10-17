<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_body_fish', function (Blueprint $table) {
            $table->id();

            $table->foreignId('water_body_id')
                ->constrained('water_bodies')
                ->cascadeOnDelete();

            $table->foreignId('fish_species_id')
                ->constrained('fish_species')
                ->cascadeOnDelete();

            $table->string('compatibility_status')
                ->default('insufficient_data');

            $table->text('explanation')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->timestamps();

            $table->unique(['water_body_id', 'fish_species_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_body_fish');
    }
};
