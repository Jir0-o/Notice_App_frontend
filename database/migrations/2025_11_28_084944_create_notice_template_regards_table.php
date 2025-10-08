<?php
// database/migrations/2025_10_08_000003_create_notice_template_regards_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notice_template_regards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('notice_template_id')
                  ->constrained('notice_templates')
                  ->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('active');
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_template_regards');
    }
};