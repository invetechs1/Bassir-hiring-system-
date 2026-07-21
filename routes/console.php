<?php

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

Artisan::command('bassir:health', function () {
    $this->info('Bassir Laravel shared-hosting package is installed.');
});

Artisan::command('bassir:create-owner {--username= : Owner username} {--email= : Owner email} {--name= : Owner full name} {--company= : Company name} {--password= : Owner password; omit to enter securely}', function () {
    $username = (string) ($this->option('username') ?: $this->ask('Owner username'));
    $email = (string) ($this->option('email') ?: $this->ask('Owner email'));
    $name = (string) ($this->option('name') ?: $this->ask('Owner full name', 'Bassir Owner'));
    $companyName = (string) ($this->option('company') ?: $this->ask('Company name', 'Bassir Technology'));
    $password = (string) ($this->option('password') ?: $this->secret('Owner password'));

    if (strlen($password) < 10) {
        $this->error('Password must be at least 10 characters.');
        return 1;
    }

    $company = Company::firstOrCreate(
        ['slug' => Str::slug($companyName) ?: 'bassir-company'],
        ['name' => $companyName, 'status' => 'ACTIVE', 'default_currency' => 'SAR']
    );
    $role = Role::where('name', 'SUPER_ADMIN')->firstOrFail();

    $user = User::updateOrCreate(
        ['username' => $username],
        [
            'company_id' => $company->id,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $role->id,
            'is_active' => true,
            'must_change_password' => true,
        ]
    );

    $this->info("Owner account ready: {$user->username}");
    $this->warn('The owner must change password after first login.');

    return 0;
});
