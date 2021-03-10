<?php

use App\Models\Service;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
class ServiceSeeder extends Seeder
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
            $address = Service::create([
                'name' => $faker->name,
                'icon' => 'no_image.png',
            ]);
        }

    }
}
