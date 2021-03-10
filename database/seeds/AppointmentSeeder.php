<?php

use App\Models\DoctorProfile;
use App\Models\Appointment;
use App\Models\PatientProfile;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class AppointmentSeeder extends Seeder
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
        $patient = PatientProfile::all()->pluck('id');
        foreach(range(1,32) as $index){
            $address = Appointment::create([
                'doctor_profile_id' => $faker->randomElement($users),
                'patient_profile_id' => $faker->randomElement($patient),
                /*'hospital_id' => "1",
                'relation_id' => "1",*/
                'type' => "online_consultation",
                'patient_type' => 'self',
                'appointment_status' => 'pending',
                'fee_status' => 'unpaid',
                'booking_date' => $faker->dateTime,
                'start_time' => '15:30',
            ]);
        }

    }
}
