<?php

use App\User;
use App\Models\DoctorProfile;
use App\Models\PatientProfile;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class PatientProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        $address = PatientProfile::create([
            'user_id' => '13',
            'dob' => '2020-12-15',
            'gender' => 'Male',
            'blood_group' => 'O+',
            'marital_status' => 'Unmarried',
            'height' => '6.5',
            'weight' => '65',
            'age' => '35',
            'address' => 'sdfsdfsd',
        ]);

    }
}
