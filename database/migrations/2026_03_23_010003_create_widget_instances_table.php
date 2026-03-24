<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('widget_instances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('canvas_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('name')->nullable();
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
            $table->timestamps();

            $table->index(['canvas_id', 'z_index']);
            $table->index(['team_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_instances');
    }
};
