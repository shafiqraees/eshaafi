<?php

use App\Models\DoctorProfile;
use App\Models\Membership;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
class MemberShipSeeder extends Seeder
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
            $address = Membership::create([
                'doctor_profile_id' => $faker->randomElement($users),
                'details' => $faker->name,
            ]);
        }

    }
}
