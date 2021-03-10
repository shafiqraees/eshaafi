<?php
namespace App;

use App\Models\Appointment;

class AppointmentStatus
{
    public function proceed()
    {
        $today = date("Y-m-d");
        $appointments = Appointment::Where('appointment_status', 'pending')->where('booking_date', '<=', $today )
            ->whereHas('channel', function ($query) {
                $query->where('is_patient_called', false);
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
    }
}
