<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_stores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('workflow_class');
            $table->string('status');
            $table->json('records')->nullable();
            $table->timestamps();

            $table->index('team_id');
            $table->index(['team_id', 'workflow_class']);
            $table->index(['team_id', 'subject_type', 'subject_id']);
            $table->index(['workflow_class', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_stores');
    }
};
