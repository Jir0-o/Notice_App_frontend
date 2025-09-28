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
        Schema::create('meeting_detailspropagations', function (Blueprint $table) {
            $table->id();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->boolean('is_read')->default(false);
            $table->unsignedBigInteger('meeting_detail_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->foreign('meeting_detail_id')
                ->references('id')
                ->on('meeting_details')
                ->onDelete('cascade');

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
        Schema::dropIfExists('meeting_detailspropagations');
    }
};
