<?php

use App\Models\DoctorHospital;
use App\Models\DoctorProfile;
use App\Models\Service;
use App\Models\DoctorService;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
class DoctorServiceSeeder extends Seeder
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
        $service = Service::all()->pluck('id');
        foreach(range(1,32) as $index){
            $address = DoctorService::create([
                'doctor_profile_id' => $faker->randomElement($users),
                'service_id' => $faker->randomElement($service),
            ]);
        }

    }
}
