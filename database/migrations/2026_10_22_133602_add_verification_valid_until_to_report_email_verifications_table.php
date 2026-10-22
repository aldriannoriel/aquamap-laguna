
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_email_verifications', function (Blueprint $table) {
            $table->timestamp('verification_valid_until')
                ->nullable()
                ->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('report_email_verifications', function (Blueprint $table) {
            $table->dropColumn('verification_valid_until');
        });
    }
};
