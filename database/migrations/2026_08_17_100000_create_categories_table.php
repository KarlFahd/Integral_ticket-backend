<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });

        // Seeded here, not left to a separate `db:seed` step — the very
        // next migration backfills every existing ticket's category from
        // these rows, so they must already exist by the time migrate runs.
        DB::table('categories')->insert(
            collect(['Hardware', 'Software', 'Network', 'Account'])
                ->map(fn (string $name) => ['name' => $name])
                ->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
