<?php

use App\User;
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@domain.com',
            'user_name' => 'admin@domain.com',
            'password' => bcrypt('123456'),
            'user_type' => 'admin',
            'is_active' => 'true',
            'phone' => '123456789',
        ]);
        $user->assignRole('Admin');

        $user = User::create([
            'name' => 'Doctor',
            'email' => 'doctor@domain.com',
            'user_name' => 'doctor@domain.com',
            'password' => bcrypt('123456'),
            'user_type' => 'doctor',
            'is_active' => 'true',
            'phone' => '123456789',
        ]);
        $user->assignRole('Doctor');
        $user = User::create([
            'name' => 'Doctor',
            'email' => 'doctor1@domain.com',
            'user_name' => 'doctor1@domain.com',
            'password' => bcrypt('123456'),
            'user_type' => 'doctor',
            'is_active' => 'true',
            'phone' => '123456789',
        ]);
        $user->assignRole('Doctor');
        $user = User::create([
            'name' => 'Doctor',
            'email' => 'doctor2@domain.com',
            'user_name' => 'doctor2@domain.com',
            'password' => bcrypt('123456'),
            'user_type' => 'doctor',
            'is_active' => 'true',
            'phone' => '123456789',
        ]);
        $user->assignRole('Doctor');
        $user = User::create([
            'name' => 'Doctor',
            'email' => 'doctor3@domain.com',
            'user_name' => 'doctor3@domain.com',
            'password' => bcrypt('123456'),
            'user_type' => 'doctor',
            'is_active' => 'true',
            'phone' => '123456789',
        ]);
        $user->assignRole('Doctor');

        $user = User::create([
            'name' => 'Doctor',
            'email' => 'doctor4@domain.com',
            'user_name' => 'doctor4@domain.com',
            'password' => bcrypt('123456'),
            'user_type' => 'doctor',
            'is_active' => 'true',
            'phone' => '123456789',
        ]);
        $user->assignRole('Doctor');



        $user = User::create([
            'name' => 'Doctor',
            'email' => 'doctor5@domain.com',
            'user_name' => 'doctor5@domain.com',
            'password' => bcrypt('123456'),
            'user_type' => 'doctor',
            'is_active' => 'true',
            'phone' => '123456789',
        ]);
        $user->assignRole('Doctor');



        $user = User::create([
            'name' => 'Doctor',
            'email' => 'doctor6@domain.com',
            'user_name' => 'doctor6@domain.com',
            'password' => bcrypt('123456'),
            'user_type' => 'doctor',
            'is_active' => 'true',
            'phone' => '123456789',
        ]);
        $user->assignRole('Doctor');



        $user = User::create([
            'name' => 'Doctor',
            'email' => 'doctor7@domain.com',
            'user_name' => 'doctor7@domain.com',
            'password' => bcrypt('123456'),
            'user_type' => 'doctor',
            'is_active' => 'true',
            'phone' => '123456789',
        ]);
        $user->assignRole('Doctor');



        $user = User::create([
            'name' => 'Doctor',
            'email' => 'doctor8@domain.com',
            'user_name' => 'doctor8@domain.com',
            'password' => bcrypt('123456'),
            'user_type' => 'doctor',
            'is_active' => 'true',
            'phone' => '123456789',
        ]);
        $user->assignRole('Doctor');



        $user = User::create([
            'name' => 'Doctor',
            'email' => 'doctor9@domain.com',
            'user_name' => 'doctor9@domain.com',
            'password' => bcrypt('123456'),
            'user_type' => 'doctor',
            'is_active' => 'true',
            'phone' => '123456789',
        ]);
        $user->assignRole('Doctor');

        $user = User::create([
            'name' => 'User',
            'email' => 'user@domain.com',
            'user_name' => 'user@domain.com',
            'password' => bcrypt('123456'),
            'user_type' => 'user',
            'is_active' => 'true',
            'phone' => '123456789',
        ]);
        $user->assignRole('User');

        $user = User::create([
            'name' => 'Patient',
            'email' => 'patient@domain.com',
            'user_name' => 'patient@domain.com',
            'password' => bcrypt('123456'),
            'user_type' => 'patient',
            'is_active' => 'true',
            'phone' => '123456789',
        ]);
        $user->assignRole('Patient');


    }
}
