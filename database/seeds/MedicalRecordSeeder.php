<?php

use App\Models\Appointment;
use App\Models\Prescription;
use App\Models\MedicalRecord;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class MedicalRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create();
        $users = Prescription::all()->pluck('id');
        foreach(range(1,32) as $index){
            $address = MedicalRecord::create([
                'prescriptions_id' => $faker->randomElement($users),
                'Type' => "report",
                'image_url' => "files/no-image.png",
            ]);
        }

    }
}
