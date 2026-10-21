<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $t): void {
            $t->id();
            $t->string('name', 160);
            $t->string('municipality', 160);
            $t->string('barangay', 120)->nullable();
            $t->string('email', 254);
            $t->boolean('active')->default(true);
            $t->string('verification_status', 20)->default('provisional');
            $t->text('notes')->nullable();
            $t->string('identity_key', 64)->unique();
            $t->timestamps();
        });
        Schema::create('agency_water_body', function (Blueprint $t): void {
            $t->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $t->foreignId('water_body_id')->constrained()->restrictOnDelete();
            $t->primary(['agency_id', 'water_body_id']);
        });
        Schema::create('agency_directory_initializations', function (Blueprint $t): void {
            $t->string('key')->primary();
            $t->timestamp('completed_at')->nullable();
        });
        Schema::create('referrals', function (Blueprint $t): void {
            $t->id();
            $t->uuid('reference')->unique();
            $t->string('dedupe_key', 100)->unique();
            $t->foreignId('pollution_report_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $t->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at');
            $t->json('recipient_snapshot');
            $t->longText('payload');
            $t->boolean('include_photo')->default(false);
            $t->string('status', 24)->default('queued');
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->uuid('claim_token')->nullable();
            $t->timestamp('queued_at');
            $t->timestamp('sending_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('failed_at')->nullable();
            $t->timestamp('inbox_confirmed_at')->nullable();
            $t->foreignId('inbox_confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('provider_message_id', 255)->nullable();
            $t->string('error_code', 80)->nullable();
            $t->timestamps();
        });
        Schema::create('referral_events', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('referral_id')->constrained()->restrictOnDelete();
            $t->string('event', 80);
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new LogicException('Forward-only: preserve agency and referral history.');
    }
};
