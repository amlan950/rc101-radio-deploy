<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Create or update test user to avoid unique constraint errors
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        // Demo admin login for testing (per project requirements):
        //   Username: admin   Password: password
        // The admin login form accepts either this username or an email address.
        // IMPORTANT: change this password (or delete/replace this user) before
        // the site goes into production.
        User::updateOrCreate(
            ['name' => 'admin'],
            [
                'email' => 'admin@' . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost') . '.local',
                'password' => bcrypt('password'),
            ]
        );
    }
}
