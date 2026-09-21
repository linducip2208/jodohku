<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('id_card');
            $table->string('status', 20)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_request_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50)->default('id_card');
            $table->string('file_path');
            $table->string('mime_type', 100)->nullable();
            $table->json('extracted_data')->nullable();
            $table->timestamps();
            $table->index('verification_request_id');
        });

        Schema::create('fraud_risk_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score')->default(0);
            $table->string('level', 20)->default('low');
            $table->json('signals')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('device_fingerprint')->nullable();
            $table->timestamp('scored_at')->useCurrent();
            $table->timestamps();
            $table->index(['user_id', 'scored_at']);
            $table->index(['level', 'score']);
        });

        Schema::create('fraud_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 60);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('severity')->default(1);
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'event_type']);
            $table->index(['is_resolved', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_events');
        Schema::dropIfExists('fraud_risk_scores');
        Schema::dropIfExists('verification_documents');
        Schema::dropIfExists('verification_requests');
    }
};
