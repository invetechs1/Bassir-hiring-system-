<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Full extracted CV text. Until now only a 900-1200 character excerpt survived (and was then
        // overwritten by the AI summary), so a recruiter searching "Navisworks" or "Aramco" could only
        // ever hit the handful of skills the parser had pre-recognised. Search needs the whole CV.
        Schema::table('candidates', function (Blueprint $table) {
            $table->longText('cv_text')->nullable()->after('ai_summary');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('cv_text');
        });
    }
};
