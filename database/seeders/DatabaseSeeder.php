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
        // Создаём пользователя
//        $user = User::factory()->create([
//            'name' => 'Test User',
//            'email' => 'test@example.com',
//            'role' => 'admin', // Вот тут сразу задаём роль!
//        ]);

        // Это назначит роль уже существующему пользователю (например, с id=1)
        $user = \App\Models\User::find(1);
        if ($user) {
            $user->role = 'admin';
            $user->save();
        }

        // Если хочешь назначать роль уже существующему пользователю:
        // $user = User::find(1);
        // $user->role = 'admin';
        // $user->save();
    }

}
