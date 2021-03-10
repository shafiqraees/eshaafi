<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDoctorProfilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('doctor_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('icon')->nulllable();
            $table->string('pmdc');
            $table->text('address')->nulllable();
            $table->string('city')->nulllable();
            $table->string('dob')->nulllable();
            $table->string('gender')->nulllable();
            $table->string('country')->nulllable();
            $table->text('summary')->nulllable();
            $table->enum('is_active', ['true', 'false'])->default('true');
            $table->enum('is_video_enable', ['true', 'false'])->default('true');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('doctor_profiles');
    }
}
