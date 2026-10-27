<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Example only: copy into package-releases/<version>/migrations/ and adapt.
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vehicles') && !Schema::hasColumn('vehicles', 'example_colour')) {
            Schema::table('vehicles', fn (Blueprint $table) => $table->string('example_colour')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('vehicles') && Schema::hasColumn('vehicles', 'example_colour')) {
            Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn('example_colour'));
        }
    }
};
