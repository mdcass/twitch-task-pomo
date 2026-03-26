<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Greenfield cutover: widget and canvas placement tables now ship with the
        // full generalized widget shape from their create migration.
    }

    public function down(): void
    {
        // No-op. See up().
    }
};
