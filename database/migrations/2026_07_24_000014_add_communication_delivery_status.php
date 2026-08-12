<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->string('status')->default('SENT')->after('body'); // SENT|FAILED
            $table->string('template')->nullable()->after('status'); // e.g. application_received, interview_invite
        });
    }

    public function down(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->dropColumn(['status', 'template']);
        });
    }
};
