<?php

use App\Models\Language;
use App\Models\DoctorProfile;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
class LanguageSeeder extends Seeder
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
            $address = Language::create([
                'doctor_profile_id' => $faker->randomElement($users),
                'language' => $faker->name,
            ]);
        }

    }
}
