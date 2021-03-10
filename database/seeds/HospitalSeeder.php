<?php

use App\Models\DoctorProfile;
use App\Models\Hospital;
use App\User;
use App\Models\DoctorHospital;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
class HospitalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create();
        foreach(range(1,32) as $index){
            $address = Hospital::create([
                'name' => $faker->name,
                'address' => $faker->address,
                'city' => $faker->city,
            ]);
        }

    }
}
