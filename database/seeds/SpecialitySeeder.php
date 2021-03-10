<?php

use App\Models\DoctorProfile;
use App\Models\Speciality;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class SpecialitySeeder extends Seeder
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
            $address = Speciality::create([
                'name' => $faker->name,
                'icon' => 'no-icon',
            ]);
        }

    }
}
