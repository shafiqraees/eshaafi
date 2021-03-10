<?php

use App\Models\DoctorSpeciality;
use App\Models\DoctorProfile;
use App\Models\Speciality;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
class DoctorSpecialitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create();
        $users = DoctorProfile::all()->pluck('id');
        $speciality = Speciality::all()->pluck('id');
        foreach(range(1,32) as $index){
            $address = DoctorSpeciality::create([
                'doctor_profile_id' => $faker->randomElement($users),
                'speciality_id' => $faker->randomElement($speciality),
            ]);
        }

    }
}
