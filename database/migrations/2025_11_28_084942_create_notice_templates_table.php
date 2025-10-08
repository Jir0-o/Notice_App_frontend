<?php
// database/migrations/2025_10_08_000001_create_notice_templates_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notice_templates', function (Blueprint $table) {
            $table->id();
            $table->string('memorial_no')->unique();
            $table->date('date');
            $table->string('subject');
            $table->longText('body');
            $table->longText('signature_body')->nullable();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            // keep status flexible; adjust defaults to your workflow
            $table->string('status')->default('published');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_templates');
    }
};