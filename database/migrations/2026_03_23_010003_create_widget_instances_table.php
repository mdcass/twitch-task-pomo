<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('widgets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('name');
            $table->unsignedInteger('schema_version')->default(1);
            $table->json('config')->nullable();
            $table->json('appearance')->nullable();
            $table->string('lifecycle_state');
            $table->timestamp('published_at')->nullable();
            $table->uuid('publication_key')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'type']);
            $table->index(['team_id', 'lifecycle_state']);
            $table->index(['team_id', 'published_at']);
        });

        Schema::create('canvas_widgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('canvas_id')->constrained()->cascadeOnDelete();
            $table->foreignId('widget_id')->nullable()->constrained('widgets')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('source_kind')->default('proprietary');
            $table->string('name')->nullable();
            $table->string('embed_url', 2048)->nullable();
            $table->integer('position_x');
            $table->integer('position_y');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('content_width');
            $table->unsignedInteger('content_height');
            $table->unsignedInteger('crop_top')->default(0);
            $table->unsignedInteger('crop_right')->default(0);
            $table->unsignedInteger('crop_bottom')->default(0);
            $table->unsignedInteger('crop_left')->default(0);
            $table->unsignedInteger('z_index')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->json('settings')->nullable();
            $table->string('preview_status')->default('ready');
            $table->text('preview_message')->nullable();
            $table->timestamp('preview_checked_at')->nullable();
            $table->timestamps();

            $table->index(['canvas_id', 'z_index']);
            $table->index(['canvas_id', 'source_kind']);
            $table->index(['team_id', 'source_kind']);
            $table->index('preview_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canvas_widgets');
        Schema::dropIfExists('widgets');
    }
};
