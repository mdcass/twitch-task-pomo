<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stream_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source');
            $table->string('submitted_by_username');
            $table->string('submitted_by_provider_user_id')->nullable();
            $table->text('body');
            $table->string('status');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['stream_session_id', 'status']);
            $table->index(['stream_session_id', 'submitted_by_username']);
            $table->index(['stream_session_id', 'submitted_by_provider_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_items');
    }
};
