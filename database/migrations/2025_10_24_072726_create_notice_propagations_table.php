<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notice_propagations', function (Blueprint $table) {
            $table->id();
            $table->string('user_email')->nullable();
            $table->boolean('is_read')->default(false);
            $table->unsignedBigInteger('notice_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('notice_id')
                ->references('id')
                ->on('notices');
            $table->foreign('user_id')
                ->references('id')
                ->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notice_propagations');
    }
};
