<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            AppSettingSeeder::class,
            DailyVerseSeeder::class,
            TestimonySeeder::class,
        ]);

        // Admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@testiapp.com'],
            [
                'id' => (string) Str::uuid(),
                'display_name' => 'Administrateur',
                'password' => Hash::make('password'),
                'role' => UserRole::Administrateur,
                'status' => UserAccountStatus::Active,
                'is_active' => true,
                'country' => 'Bénin',
            ]
        );
        $admin->settings()->firstOrCreate(['user_id' => $admin->id]);

        // Moderateur user
        $mod = User::firstOrCreate(
            ['email' => 'moderateur@testiapp.com'],
            [
                'id' => (string) Str::uuid(),
                'display_name' => 'Modérateur',
                'password' => Hash::make('password'),
                'role' => UserRole::Moderateur,
                'status' => UserAccountStatus::Active,
                'is_active' => true,
                'country' => 'Bénin',
            ]
        );
        $mod->settings()->firstOrCreate(['user_id' => $mod->id]);

        // Regular user
        $user = User::firstOrCreate(
            ['email' => 'utilisateur@testiapp.com'],
            [
                'id' => (string) Str::uuid(),
                'display_name' => 'Jean Dupont',
                'password' => Hash::make('password'),
                'role' => UserRole::Utilisateur,
                'status' => UserAccountStatus::Active,
                'is_active' => true,
                'country' => 'Bénin',
            ]
        );
        $user->settings()->firstOrCreate(['user_id' => $user->id]);

        $this->command->info('✅ Base de données initialisée avec succès.');
        $this->command->info('Admin : admin@testiapp.com / password');
        $this->command->info('Modérateur : moderateur@testiapp.com / password');
        $this->command->info('Utilisateur : utilisateur@testiapp.com / password');
    }
}
