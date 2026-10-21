<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pollution_reports', function (Blueprint $t): void {
            $t->string('landmark', 200)->nullable();
            $t->date('observed_on')->nullable();
            $t->decimal('location_accuracy_m', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        throw new LogicException('Forward-only: preserve report location evidence.');
    }
};
