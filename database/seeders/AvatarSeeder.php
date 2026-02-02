<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AvatarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
     public function run(): void
    {
        DB::table('avatars')->insert([
            ['nombre' => 'avatar_01'],
            ['nombre' => 'avatar_02'],
            ['nombre' => 'avatar_03'],
            ['nombre' => 'avatar_04'],
            ['nombre' => 'avatar_05'],
            ['nombre' => 'avatar_06'],
            ['nombre' => 'avatar_07'],
            ['nombre' => 'avatar_08'],
            ['nombre' => 'avatar_09'],
        ]);
    }
}
