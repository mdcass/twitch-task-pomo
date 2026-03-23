<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pomodoro_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stream_session_id')->constrained()->cascadeOnDelete();
            $table->string('state');
            $table->unsignedSmallInteger('focus_minutes');
            $table->unsignedSmallInteger('break_minutes');
            $table->timestamp('started_at');
            $table->timestamp('ends_at');
            $table->timestamp('paused_at')->nullable();
            $table->unsignedInteger('sequence')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'state']);
            $table->index(['stream_session_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pomodoro_sessions');
    }
};
