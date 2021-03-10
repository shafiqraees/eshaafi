<?php

use App\Models\DoctorHospital;
use App\Models\DoctorProfile;
use App\Models\Hospital;
use App\Models\DoctorHospitalDay;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
class DoctorHospitalDaySeeder extends Seeder
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
        $Hospital = DoctorHospital::all()->pluck('id');
        $user = DoctorHospitalDay::create([
            'doctor_profile_id' => $faker->randomElement($users),
            'doctor_hospital_id' => $faker->randomElement($Hospital),
            'achievement' => bcrypt('123456'),
            'days' => 'Mon',
            'start_time' => '09:30',
            'end_time' => '18:00',
        ]);



    }
}
