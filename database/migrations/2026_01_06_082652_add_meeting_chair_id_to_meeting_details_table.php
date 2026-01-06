<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('meeting_details', function (Blueprint $table) {
            $table->unsignedBigInteger('meeting_chair_id')->nullable()->after('meeting_id');
            $table->index('meeting_chair_id');

            $table->foreign('meeting_chair_id')
                ->references('id')->on('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('meeting_details', function (Blueprint $table) {
            $table->dropForeign(['meeting_chair_id']);
            $table->dropIndex(['meeting_chair_id']);
            $table->dropColumn('meeting_chair_id');
        });
    }
};

