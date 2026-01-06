<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRejectionReasonToNoticeTemplatesTable extends Migration
{
    public function up()
    {
        Schema::table('notice_templates', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('approved_at');
        });
    }

    public function down()
    {
        Schema::table('notice_templates', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
}