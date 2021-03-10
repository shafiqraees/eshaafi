<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;

class WordOfTheDay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appointment:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'It update the status of instant appointment.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $today = date("Y-m-d");
        $appointments = Appointment::Where('appointment_status', 'pending')->where('booking_date', '<=', $today)
            ->whereHas('channel', function ($query) {
                $query->where('is_patient_called', 'false');
                $query->where('is_doctor_called', 'true');
            })->get();
        foreach ($appointments as $appointment) {
            $currenttime = date("H:i");
            $time = strtotime($appointment->start_time);
            $endTime = date("H:i", strtotime('+30 minutes', $time));
            $minusTime = date("H:i", strtotime('+31 minutes', $time));
            if (($appointment->booking_date < $today) || (($appointment->booking_date == $today) && ($currenttime > $endTime))) {
                $appointment->update([
                    'appointment_status' => 'not_appeared',
                ]);
            }
        }

        $appointments = Appointment::Where('appointment_status', 'pending')->where('booking_date', '<=', $today)
            ->whereHas('channel', function ($query) {
                $query->where('is_patient_called', 'true');
                $query->where('is_doctor_called', 'true');
            })->get();
        foreach ($appointments as $appointment) {
            $currenttime = date("H:i");
            $time = strtotime($appointment->start_time);
            $endTime = date("H:i", strtotime('+30 minutes', $time));
            $minusTime = date("H:i", strtotime('+31 minutes', $time));
            if (($appointment->booking_date < $today) || (($appointment->booking_date == $today) && ($currenttime > $endTime))) {
                $appointment->update([
                    'appointment_status' => 'completed',
                ]);
            }
        }

        $appointments = Appointment::Where('appointment_status', 'pending')->where('booking_date', '<=', $today)
            ->whereHas('channel', function ($query) {
                $query->where('is_doctor_called', 'false');
                $query->where('is_patient_called', 'false');
            })->get();
        foreach ($appointments as $appointment) {
            $currenttime = date("H:i");
            $time = strtotime($appointment->start_time);
            $endTime = date("H:i", strtotime('+30 minutes', $time));
            $minusTime = date("H:i", strtotime('+31 minutes', $time));
            if (($appointment->booking_date < $today) || (($appointment->booking_date == $today) && ($currenttime > $endTime))) {
                $appointment->update([
                    'appointment_status' => 'expired',
                ]);
            }
        }
    }
}
