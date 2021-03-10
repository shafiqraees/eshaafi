<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppointmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('doctor_profile_id');
            $table->unsignedBigInteger('patient_profile_id');
            $table->unsignedBigInteger('hospital_id')->nullable();
            $table->unsignedBigInteger('relation_id')->nullable();
            $table->enum('type', ['hospital', 'online_consultation']);
            $table->enum('patient_type', ['self', 'other'])->default('self');
            $table->enum('appointment_status', ['pending', 'canceled', 'completed', 'not_appeared', 'expired'])->default('pending');
            $table->enum('fee_status', ['unpaid', 'paid']);
            $table->date('booking_date')->nullable();
            $table->string('start_time')->nullable();
            $table->timestamps();

            $table->foreign('doctor_profile_id')->references('id')->on('doctor_profiles')->onDelete('cascade');
            $table->foreign('patient_profile_id')->references('id')->on('patient_profiles')->onDelete('cascade');
            $table->foreign('hospital_id')->references('id')->on('hospitals')->onDelete('cascade');
            $table->foreign('relation_id')->references('id')->on('relations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('appointments');
    }
}
