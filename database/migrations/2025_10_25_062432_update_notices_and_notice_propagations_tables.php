<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateNoticesAndNoticePropagationsTables extends Migration
{
    public function up()
    {
        // Update notices: Add priority_level and status
        Schema::table('notices', function (Blueprint $table) {
            // $table->string('priority_level')->default('normal');
            $table->string('status')->default('draft');
        });

        // Update notice_propagations: Add name and sent_at
        Schema::table('notice_propagations', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->timestamp('sent_at')->nullable();
        });
    }

    public function down()
    {
        // Rollback for notices
        Schema::table('notices', function (Blueprint $table) {
            $table->dropColumn(['priority_level', 'status']);
        });

        // Rollback for notice_propagations
        Schema::table('notice_propagations', function (Blueprint $table) {
            $table->dropColumn(['name', 'sent_at']);
        });
    }
}


