<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Role::create([
            'id' => '1',
            'name' => 'Super Admin'
        ]);
        Role::create([
            'id' => '2',
            'name' => 'Admin'
        ]);
        Role::create([
            'id' => '3',
            'name' => 'Trainer'
        ]);
        Role::create([
            'id' => '4',
            'name' => 'Learner'
        ]);
        Role::create([
            'id' => '5',
            'name' => 'Human Resource Manager'
        ]);
    }
}
