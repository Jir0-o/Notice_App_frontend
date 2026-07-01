<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('contact_no', 40)->unique();

            $table->string('name', 150);
            $table->string('email', 180);
            $table->string('phone', 40)->nullable();

            $table->string('subject', 180);
            $table->longText('message');

            $table->enum('priority', ['normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', ['new', 'read', 'replied', 'closed'])->default('new');

            $table->string('user_id', 100)->nullable()->comment('If the user is logged in, store the user ID here');

            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->text('admin_note')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['email']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};