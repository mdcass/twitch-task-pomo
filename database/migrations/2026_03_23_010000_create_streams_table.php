<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('streams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_auth_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_channel_id');
            $table->string('channel_login');
            $table->string('display_name');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'provider']);
            $table->index(['provider', 'provider_channel_id']);
            $table->index('channel_login');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streams');
    }
};
