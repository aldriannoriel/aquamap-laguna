<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_email_verifications', function (Blueprint $table) {
            $table->string('active_session_key', 64)
                ->nullable();

            $table->unique(
                'active_session_key',
                'report_otp_active_session_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('report_email_verifications', function (Blueprint $table) {
            $table->dropUnique('report_otp_active_session_unique');
            $table->dropColumn('active_session_key');
        });
    }
};
