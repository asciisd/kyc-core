<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kycs', function (Blueprint $table) {
            $table->id();
            $table->morphs('kycable'); // kycable_id, kycable_type
            $table->string('driver')->default('shuftipro');
            $table->string('status')->default('not_started');
            $table->string('reference')->unique();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['kycable_id', 'kycable_type']);
            $table->index('status');
            $table->index('driver');
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kycs');
    }
};
