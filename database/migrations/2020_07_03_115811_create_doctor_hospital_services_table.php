<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDoctorHospitalServicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('doctor_hospital_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_profile_id');
            $table->unsignedBigInteger('doctor_hospital_id');
            $table->unsignedBigInteger('service_id');
            $table->enum('is_active', ['true', 'false'])->default('true');
            $table->timestamps();

            $table->foreign('doctor_profile_id')->references('id')->on('doctor_profiles')->onDelete('cascade');
            $table->foreign('doctor_hospital_id')->references('id')->on('doctor_hospitals')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('doctor_hospital_services');
    }
}
