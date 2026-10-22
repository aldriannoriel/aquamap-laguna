<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = DB::connection();
        if (! (app()->environment('testing') && $connection->getDriverName() === 'sqlite')
            && ($connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== 'aquamap_laguna'
                || $connection->getPdo()->query('SELECT DATABASE()')->fetchColumn() !== 'aquamap_laguna')) {
            throw new RuntimeException('Evidence schema changes require the verified AquaMap database.');
        }
        Schema::create('report_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_review_id')->constrained('report_reviews')->restrictOnDelete();
            $table->string('path')->unique();
            $table->string('original_name', 180);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('report_evidence') && DB::table('report_evidence')->exists()) {
            throw new RuntimeException('Evidence records must be preserved; use a forward schema change.');
        }
        Schema::dropIfExists('report_evidence');
    }
};
