<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    /**
     * Create test users for local development.
     * Usage: php artisan db:seed --class=TestUsersSeeder
     *
     * Users created: testuser1@draftslate.dev through testuser10@draftslate.dev
     * Password for all: password
     */
    public function run(): void
    {
        $users = [
            ['display_name' => 'Alice Alpha', 'email' => 'testuser1@draftslate.dev'],
            ['display_name' => 'Bob Beta', 'email' => 'testuser2@draftslate.dev'],
            ['display_name' => 'Carol Charlie', 'email' => 'testuser3@draftslate.dev'],
            ['display_name' => 'Dave Delta', 'email' => 'testuser4@draftslate.dev'],
            ['display_name' => 'Eve Echo', 'email' => 'testuser5@draftslate.dev'],
            ['display_name' => 'Frank Foxtrot', 'email' => 'testuser6@draftslate.dev'],
            ['display_name' => 'Grace Golf', 'email' => 'testuser7@draftslate.dev'],
            ['display_name' => 'Hank Hotel', 'email' => 'testuser8@draftslate.dev'],
            ['display_name' => 'Iris India', 'email' => 'testuser9@draftslate.dev'],
            ['display_name' => 'Jack Juliet', 'email' => 'testuser10@draftslate.dev'],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                array_merge($userData, [
                    'password' => Hash::make('password'),
                    'max_leagues' => 10,
                ])
            );
        }

        $this->command->info('Created 10 test users (testuser1-10@draftslate.dev, password: password)');
    }
}
