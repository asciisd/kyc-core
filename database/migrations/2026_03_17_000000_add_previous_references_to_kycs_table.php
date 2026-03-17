<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kycs', function (Blueprint $table) {
            $table->json('previous_references')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('kycs', function (Blueprint $table) {
            $table->dropColumn('previous_references');
        });
    }
};
