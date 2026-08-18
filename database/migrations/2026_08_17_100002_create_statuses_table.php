<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });

        DB::table('statuses')->insert(
            collect(['Open', 'Pending', 'In Progress', 'Rejected', 'Resolved'])
                ->map(fn (string $name) => ['name' => $name])
                ->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
