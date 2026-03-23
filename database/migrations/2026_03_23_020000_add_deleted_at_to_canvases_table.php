<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('canvases', function (Blueprint $table): void {
            $table->softDeletes()->after('updated_at');
            $table->index(['team_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('canvases', function (Blueprint $table): void {
            $table->dropIndex(['team_id', 'deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
