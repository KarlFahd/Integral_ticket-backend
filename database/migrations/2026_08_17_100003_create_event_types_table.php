<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });

        DB::table('event_types')->insert(
            collect(['meeting', 'deadline', 'reminder', 'other'])
                ->map(fn (string $name) => ['name' => $name])
                ->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('event_types');
    }
};
