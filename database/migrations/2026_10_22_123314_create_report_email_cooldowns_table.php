
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_email_cooldowns', function (Blueprint $table) {
            $table->id();

            $table->string('email_hash', 64)->unique();

            $table->timestamp('last_report_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_email_cooldowns');
    }
};
