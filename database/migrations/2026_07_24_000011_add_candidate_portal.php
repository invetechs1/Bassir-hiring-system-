<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Login credentials for the candidate self-service portal. Kept separate from
        // `candidates` (the recruiter-facing CRM record) so internal candidate imports/leads
        // never carry a password, and a candidate only gets one once they self-register.
        Schema::create('candidate_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            // A candidate may hold one account per company (each tenant's candidate pool is
            // private), but the same email can register separately with different companies.
            $table->unique(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_accounts');
    }
};
