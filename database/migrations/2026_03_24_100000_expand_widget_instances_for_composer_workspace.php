<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('widget_instances', function (Blueprint $table): void {
            $table->string('source_kind')->default('built_in')->after('team_id');
            $table->string('embed_url', 2048)->nullable()->after('name');
            $table->string('preview_status')->default('ready')->after('settings');
            $table->text('preview_message')->nullable()->after('preview_status');
            $table->timestamp('preview_checked_at')->nullable()->after('preview_message');

            $table->index(['canvas_id', 'source_kind']);
            $table->index('preview_status');
        });

        Schema::table('widget_instances', function (Blueprint $table): void {
            $table->string('type')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('widget_instances')
            ->whereNull('type')
            ->update(['type' => 'task_list']);

        Schema::table('widget_instances', function (Blueprint $table): void {
            $table->string('type')->nullable(false)->change();

            $table->dropIndex(['canvas_id', 'source_kind']);
            $table->dropIndex(['preview_status']);
            $table->dropColumn([
                'source_kind',
                'embed_url',
                'preview_status',
                'preview_message',
                'preview_checked_at',
            ]);
        });
    }
};
