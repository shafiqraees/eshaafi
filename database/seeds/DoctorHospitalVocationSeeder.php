<?php

use App\Models\DoctorHospital;
use App\Models\DoctorHospitalVocation;
use App\Models\DoctorProfile;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
class DoctorHospitalVocationSeeder extends Seeder
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
        $doctor_Hospital = DoctorHospital::all()->pluck('id');
        foreach(range(1,32) as $index){
            $address = DoctorHospitalVocation::create([
                'doctor_profile_id' => $faker->randomElement($users),
                'doctor_hospital_id' => $faker->randomElement($doctor_Hospital),
                'start_date' => $faker->datetime,
                'end_date' => $faker->datetime,
            ]);
        }

    }
}
