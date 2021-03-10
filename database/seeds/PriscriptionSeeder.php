<?php

use App\Models\Appointment;
use App\Models\DoctorProfile;
use App\Models\Prescription;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class PriscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create();
        $users = Appointment::all()->pluck('id');
        foreach(range(1,32) as $index){
            $address = Prescription::create([
                'appointments_id' => $faker->randomElement($users),
                'uploaded_by' => "self",
            ]);
        }

    }
}
