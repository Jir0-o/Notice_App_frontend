<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_updates', function (Blueprint $table) {
            $table->id();

            $table->string('platform', 50)->unique();
            $table->string('latest_version', 30);
            $table->boolean('is_active')->default(true);
            $table->dateTime('published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_updates');
    }
};