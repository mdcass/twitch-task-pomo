<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('follower_goal_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('widget_id')->unique()->constrained('widgets')->cascadeOnDelete();
            $table->unsignedInteger('current_count')->default(0);
            $table->timestamp('frozen_at')->nullable();
            $table->timestamp('last_followed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follower_goal_states');
    }
};
