<?php


use App\Models\DoctorProfile;
use App\Models\DoctorVideoConsultancy;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
class DoctorVideoConsultancySeeder extends Seeder
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
        foreach(range(1,32) as $index){
            $address = DoctorVideoConsultancy::create([
                'doctor_profile_id' => $faker->randomElement($users),
                'fee' => '1500',
                'waiting_time' => '15',
                'is_email_notification_enabled' => 'true',
            ]);
        }

    }
}
