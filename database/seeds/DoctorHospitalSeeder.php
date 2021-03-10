<?php

use App\Models\DoctorHospital;
use App\Models\DoctorProfile;
use App\Models\Hospital;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
class DoctorHospitalSeeder extends Seeder
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
        $Hospital = Hospital::all()->pluck('id');
        foreach(range(1,32) as $index){
            $address = DoctorHospital::create([
                'doctor_profile_id' => $faker->randomElement($users),
                'hospital_id' => $faker->randomElement($Hospital),
                'fee' => '1500',
                'slot_duration' => '30',
                'reminder_time' => '15',
                'waiting_time' => '10',
            ]);
        }

    }
}
