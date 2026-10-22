
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_email_verifications', function (Blueprint $table) {
            $table->id();

            // Store an HMAC hash instead of the plain email.
            $table->string('email_hash', 64)->index();

            // Bind the OTP to the browser session.
            $table->string('session_hash', 64)->index();

            // Store only the hashed verification code.
            $table->string('otp_hash');

            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();

            $table->timestamps();

            $table->index(
                ['email_hash', 'session_hash', 'created_at'],
                'report_otp_email_session_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_email_verifications');
    }
};
