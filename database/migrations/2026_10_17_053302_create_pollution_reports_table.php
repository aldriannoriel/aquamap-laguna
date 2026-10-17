<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pollution_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('water_body_id')
                ->constrained('water_bodies')
                ->cascadeOnDelete();

            $table->string('reporter_name')->nullable();
            $table->string('reporter_email')->nullable();

            $table->string('pollution_type');
            $table->text('description');
            $table->string('photo_path')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pollution_reports');
    }
};
