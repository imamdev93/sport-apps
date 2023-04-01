<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GroupTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::first();

        $group = Group::create([
            'name' => 'JDS Futsal',
            'created_by' => $user->id
        ]);

        $user->group()->attach($group->id,['is_admin' => true]);
    }
}
