<?php

use App\Models\DoctorProfile;
use Illuminate\Database\Seeder;
use App\User;
class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */

    public function run()
    {
        $address = DoctorProfile::create([
            'user_id' => '2',
            'pmdc' => rand(1,55555),
            'address' => 'asdasda',
            'city' => 'asdasdasd',
            'dob' => '2020-11-20',
            'icon' => 'no-image',
            'gender' => 'male',
            'country' => 'pakistan',
        ]);

        $address = DoctorProfile::create([
            'user_id' => '3',
            'pmdc' => rand(1,55555),
            'address' => 'asdasda',
            'city' => 'asdasdasd',
            'dob' => '2020-11-20',
            'gender' => 'male',
            'country' => 'pakistan',
            'icon' => 'no-image',
        ]);
        $address = DoctorProfile::create([
            'user_id' => '4',
            'pmdc' => rand(1,55555),
            'address' => 'asdasda',
            'city' => 'asdasdasd',
            'dob' => '2020-11-20',
            'gender' => 'male',
            'country' => 'pakistan',
            'icon' => 'no-image',
        ]);
        $address = DoctorProfile::create([
            'user_id' => '5',
            'pmdc' => rand(1,55555),
            'address' => 'asdasda',
            'city' => 'asdasdasd',
            'dob' => '2020-11-20',
            'gender' => 'male',
            'country' => 'pakistan',
            'icon' => 'no-image',
        ]);
        $address = DoctorProfile::create([
            'user_id' => '6',
            'pmdc' => rand(1,55555),
            'address' => 'asdasda',
            'city' => 'asdasdasd',
            'dob' => '2020-11-20',
            'gender' => 'male',
            'country' => 'pakistan',
            'icon' => 'no-image',
        ]);

        $address = DoctorProfile::create([
            'user_id' => '7',
            'pmdc' => rand(1,55555),
            'address' => 'asdasda',
            'city' => 'asdasdasd',
            'dob' => '2020-11-20',
            'gender' => 'male',
            'country' => 'pakistan',
            'icon' => 'no-image',
        ]);

        $address = DoctorProfile::create([
            'user_id' => '8',
            'pmdc' => rand(1,55555),
            'address' => 'asdasda',
            'city' => 'asdasdasd',
            'dob' => '2020-11-20',
            'gender' => 'male',
            'country' => 'pakistan',
            'icon' => 'no-image',
        ]);

        $address = DoctorProfile::create([
            'user_id' => '9',
            'pmdc' => rand(1,55555),
            'address' => 'asdasda',
            'city' => 'asdasdasd',
            'dob' => '2020-11-20',
            'gender' => 'male',
            'country' => 'pakistan',
            'icon' => 'no-image',
        ]);


        $address = DoctorProfile::create([
            'user_id' => '10',
            'pmdc' => rand(1,55555),
            'address' => 'asdasda',
            'city' => 'asdasdasd',
            'dob' => '2020-11-20',
            'gender' => 'male',
            'country' => 'pakistan',
            'icon' => 'no-image',
        ]);

    }
}
