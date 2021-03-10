<?php

namespace App\Traits;

use App\Agora\RtcTokenBuilder;
use App\Models\Appointment;
use App\Models\Channel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use DateTime;
use Illuminate\Support\Str;
use phpDocumentor\Reflection\Types\Float_;
use phpDocumentor\Reflection\Types\Self_;
use Ramsey\Uuid\Type\Integer;
use Illuminate\Support\Arr;

trait PatientTransformer
{
    // transform doctor profile
    public static function transformProfile($patient)
    {
        $transformed_patient = [];
        if (!empty($patient)) {

            if (!empty($patient->patientProfile)) {
                $user_data = $patient;
                $profile = $patient->patientProfile;
                $transformed_patient['dob'] = (string)$profile->dob ? $profile->dob : '';
                $transformed_patient['blood_group'] = (string)$profile->blood_group ? $profile->blood_group : '';
                $transformed_patient['weight'] = (float)$profile->weight ? $profile->weight : 0.0;
                //$transformed_patient['height'] = $profile->height ? SELF::cm2feet($profile->height) : '';
                $transformed_patient['height'] = $profile->height ? $profile->height : '';
                $transformed_patient['gender'] = (string)$profile->gender ? $profile->gender : '';
                $transformed_patient['marital_status'] = (string)$profile->marital_status ? $profile->marital_status : '';
                $transformed_patient['age'] = (string)$profile->age ? $profile->age : '';
                $transformed_patient['address'] = (string)$profile->address ? $profile->address : '';
                $transformed_patient['email'] = (string)$user_data->email ? $user_data->email : '';
                $transformed_patient['name'] = (string)$user_data->name ? $user_data->name : '';
                $transformed_patient['image'] = (string)$user_data->profile_image ? Storage::disk('s3')->url($user_data->profile_image) : url('public/files/no-image.png');
            }

        }
        return $transformed_patient;
    }

    // transform patient appointments
    public static function transformPatientAppointments($appointment_data, $is_instant = false)
    {
        $transformed_appointments = [];
        if (!empty($appointment_data)) {
            foreach ($appointment_data as $appointment) {
                $doctor = $appointment->doctor;
                if (!empty($doctor->user->id)) {
                    $currenttime = date("H:i");
                    $today = date("Y-m-d");
                    $time = strtotime($appointment->start_time);
                    $endTime = date("H:i", strtotime('+30 minutes', $time));
                    $minusTime = date("H:i", strtotime('+31 minutes', $time));
                    if (($appointment->booking_date < $today) || (($appointment->booking_date == $today) && ($currenttime > $endTime))) {
                        $is_expired = true;
                    } elseif (($appointment->booking_date == $today) && (($currenttime >= $appointment->start_time) && ($currenttime <= $minusTime))) {
                        $is_expired = false;
                    } elseif (($appointment->booking_date > $today) || (($appointment->booking_date == $today) && ($currenttime < $appointment->start_time))) {
                        $is_expired = false;
                    }
                    $temp = [
                        'doctor_id' => $doctor->user->id,
                        'id' => $appointment->id,
                        'doctor_image' => Storage::disk('s3')->exists($doctor->profile_image) ? Storage::disk('s3')->url($doctor->profile_image) : url(Storage::url('files/no-image.png')),
                        'appointment_status' => $appointment->appointment_status,
                        'appointment_is_satisfied' => !empty($appointment->rating) ? $appointment->rating->is_like : false,
                        'doctor_name' => $doctor->user->name,
                        'appointment_type' => $appointment->type,
                        'appointment_for' => $appointment->patient_type,
                        'appointment_fee' => $doctor->videoConsultancy->fee,
                        'appointment_date' => $appointment->booking_date,
                        'appointment_slot' => $appointment->start_time,
                        'appointment_is_prescribed' => false,
                        'appointment_rating' => !empty($appointment->rating) ? $appointment->rating->star_value : 0.0,
                        'appointment_is_rated' => !empty($appointment->rating) ? true : false,
                        'appointment_is_expired' => $is_expired,
                    ];
                    if ($is_instant === false) {
                        $temp['doctor_specialty'] = !empty($doctor->specialities[0]) ? $doctor->specialities[0]->speciality->name : "";
                    } else {
                        if (!empty($appointment->symptoms)) {
                            $symptom_array = [];
                            foreach ($appointment->symptoms as $value) {
                                $symptom_temp = [
                                    'id' => $value->symptom->id,
                                    'name_en' => $value->symptom->name_en,
                                    'name_ur' => $value->symptom->name_ur,
                                    'icon' => Storage::disk('s3')->exists($appointment->patient->user->profile_image) ? Storage::disk('s3')->url($value->symptom->icon) : url(Storage::url('icon/no-image.png')),
                                ];
                                array_push($symptom_array, $symptom_temp);
                            }
                            $temp['symptoms'] = $symptom_array;
                        }
                    }

                    if (!empty($appointment->prescription)) {
                        $patient_files_record = [];
                        $files_record = [];
                        foreach ($appointment->prescription as $prescription) {
                            if (!empty($prescription->medicalRecords)) {
                                $records = $prescription->medicalRecords;
                                foreach ($records as $record) {
                                    $file_type = "image";
                                    $ext = pathinfo($record->image_url, PATHINFO_EXTENSION);
                                    if ($ext == "pdf") {
                                        $file_type = "file";
                                    }
                                    $medical_reports = [
                                        'id' => $record->id,
                                        'file' => Storage::disk('s3')->exists($record->image_url) ? Storage::disk('s3')->url($record->image_url) : url(Storage::url('files/no-image.png')),
                                        'type' => $record->Type,
                                        'file_type' => $file_type,
                                    ];
                                    if ($prescription->uploaded_by === 'doctor') {
                                        array_push($files_record, $medical_reports);
                                    } else {
                                        array_push($patient_files_record, $medical_reports);
                                    }
                                }

                            } else {
                                $temp['doctor_files'] = [];
                                $temp['patient_files'] = [];
                            }
                        }
                        $temp['doctor_files'] = (array)$files_record;
                        $temp['patient_files'] = (array)$patient_files_record;
                    } else {
                        $temp['doctor_files'] = [];
                        $temp['patient_files'] = [];
                    }
                    array_push($transformed_appointments, $temp);
                }
            }
        }
        return $transformed_appointments;

    }

    // transform patient appointments
    public static function transformAppointment($appointment, $id, $user, $is_instant = false)
    {
        $transformed_appointments = [];
        if (!empty($appointment)) {
            /*******************************calling time logic here start*******************************************/
            $auth_user = Auth::user();
            $auth_user_name = $auth_user->name;
            $currenttime = date("H:i");
            $today = date("Y-m-d");
            $time = strtotime($appointment->start_time);
            $endTime = date("H:i", strtotime('+15 minutes', $time));
            $minusTime = date("H:i", strtotime('+16 minutes', $time));
            //dd($endTime);
            if (($appointment->booking_date < $today) || (($appointment->booking_date == $today) && ($currenttime > $endTime))) {
                //dd('session expire');
                $can_call = false;
                $is_expired = true;
            } elseif (($appointment->booking_date == $today) && (($currenttime >= $appointment->start_time) && ($currenttime <= $minusTime))) {
                //dd('session start');
                $can_call = true;
                $is_expired = false;
            } elseif (($appointment->booking_date > $today) || (($appointment->booking_date == $today) && ($currenttime < $appointment->start_time))) {
                //dd('you are too early');
                $can_call = false;
                $is_expired = false;
            }
            /*******************************calling time logic here end*******************************************/

            $random = Str::random(30);
            $uid = $id;
            $appID = config('app.agora_app_id');
            $app_secret = config('app.agora_secret_key');
            $channelName = $random;
            $rout = "https://patient.eshaafi.com/video-calling?channel_name=" . $random;
            $role = RtcTokenBuilder::RolePublisher;
            $expireTimeInSeconds = 86400;
            $currentTimestamp = (new  \DateTime("now", new  \DateTimeZone('UTC')))->getTimestamp();
            $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;
            $doctor_token = RtcTokenBuilder::buildTokenWithUid($appID, $app_secret, $channelName, $uid, $role, $privilegeExpiredTs);
            // for patient token
            $patient_token = RtcTokenBuilder::buildTokenWithUid($appID, $app_secret, $channelName, $auth_user->id, $role, $privilegeExpiredTs);
            /*Save Token for Every user for video calling*/
            $phone = Auth::user()->phone;
            Channel::Create([
                'patient_token' => $patient_token,
                'doctor_token' => $doctor_token,
                'name' => $channelName,
                'appointment_id' => $appointment->id,
            ]);

            if (!empty($phone)) {
                $username = config('app.send_pk_username');///Your Username
                $password = config('app.send_pk_password');///Your Password
                $mobile = $phone;
                $sender = "Eshaafi";

                $message = "Dear $auth_user_name;
                    Your video consultation with $user->name has been booked at $appointment->booking_date, $appointment->start_time  use the link
                    below to join the video consultation;
                    $rout
                    make sure you have a camera enabled smartphone/laptop with the latest browser and stable internet
                    connection for having video consultation.
                    Regard: eshaafi.com
                    Tel: 03011166522";

                $post = "sender=" . urlencode($sender) . "&mobile=" . urlencode($mobile) . "&message=" . urlencode($message) . "";
                $url = "https://sendpk.com/api/sms.php?username=" . $username . "&password=" . $password . "";
                $ch = curl_init();
                $timeout = 10; // set to zero for no timeout
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)');
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                curl_exec($ch);

            }
            // send SMS to user
            $doctor = $appointment->doctor;
            if (!empty($doctor->user->id)) {
                $currenttime = date("H:i");
                $today = date("Y-m-d");
                $time = strtotime($appointment->start_time);
                $endTime = date("H:i", strtotime('+30 minutes', $time));
                $minusTime = date("H:i", strtotime('+31 minutes', $time));
                if (($appointment->booking_date < $today) || (($appointment->booking_date == $today) && ($currenttime > $endTime))) {
                    $is_expired = true;
                } elseif (($appointment->booking_date == $today) && (($currenttime >= $appointment->start_time) && ($currenttime <= $minusTime))) {
                    $is_expired = false;
                } elseif (($appointment->booking_date > $today) || (($appointment->booking_date == $today) && ($currenttime < $appointment->start_time))) {
                    $is_expired = false;
                }
                $temp = [
                    'doctor_id' => $doctor->user->id,
                    'id' => $appointment->id,
                    'doctor_image' => Storage::disk('s3')->exists($doctor->profile_image) ? Storage::disk('s3')->url($doctor->profile_image) : url(Storage::url('files/no-image.png')),
                    'appointment_status' => $appointment->appointment_status,
                    'appointment_is_satisfied' => !empty($appointment->rating) ? $appointment->rating->is_like : false,
                    'doctor_name' => $doctor->user->name,
                    'appointment_type' => $appointment->type,
                    'appointment_for' => $appointment->patient_type,
                    'appointment_fee' => $doctor->videoConsultancy->fee,
                    'appointment_date' => $appointment->booking_date,
                    'appointment_slot' => $appointment->start_time,
                    'appointment_is_prescribed' => false,
                    'appointment_rating' => !empty($appointment->rating) ? $appointment->rating->star_value : 0.0,
                    'appointment_is_rated' => !empty($appointment->rating) ? true : false,
                    'appointment_is_expired' => $is_expired,
                ];
                if ($is_instant === false) {
                    $temp['doctor_specialty'] = !empty($doctor->specialities[0]) ? $doctor->specialities[0]->speciality->name : "";
                } else {
                    if (!empty($appointment->symptoms)) {
                        $symptom_array = [];
                        foreach ($appointment->symptoms as $value) {
                            $symptom_temp = [
                                'id' => $value->symptom->id,
                                'name_en' => $value->symptom->name_en,
                                'name_ur' => $value->symptom->name_ur,
                                'icon' => Storage::disk('s3')->exists($appointment->patient->user->profile_image) ? Storage::disk('s3')->url($value->symptom->icon) : url(Storage::url('icon/no-image.png')),
                            ];
                            array_push($symptom_array, $symptom_temp);
                        }
                        $temp['symptoms'] = $symptom_array;
                    }
                }
                $transformed_appointments = $temp;
            }
        }
        return $transformed_appointments;

    }

    // transform Doctor appointments
    public static function transformDoctorAppointments($doctor)
    {
        $tranformed_appointments = [];
        if (!empty($doctor)) {
            foreach ($doctor->appointments as $appointment) {
                $doctor = $appointment->doctor;
                $booking_date = strtotime($appointment->booking_date);
                $current_date = strtotime(date('Y-m-d'));
                if ($current_date > $booking_date) {
                    $is_expired = true;
                }
                if ($current_date == $booking_date) {
                    $startTime = new DateTime($appointment->start_time);
                    $startTime = $startTime->modify('+30 minutes');
                    $startTime = strtotime($startTime->format('H:i'));
                    $current_time = strtotime(date('H:i'));
                    if ($current_time > $startTime) {
                        $is_expired = true;
                    } else {
                        $is_expired = false;
                    }
                }
                if ($current_date < $booking_date) {
                    $is_expired = true;
                }
                $temp = [
                    'patient_id' => $doctor->patient_profile_id,
                    'patient_name' => $doctor->id,
                    'doctor_id' => $doctor->id,
                    'doctor_name' => $doctor->user->name,
                    'doctor_specialty' => $doctor->specialities[0]->speciality->name,
                    'appointment_type' => $appointment->type,
                    'appointment_for' => $appointment->patient_type,
                    'appointment_fee' => $doctor->videoConsultancy->fee,
                    'appointment_date' => $appointment->booking_date,
                    'appointment_slot' => $appointment->start_time,
                    'appointment_is_prescribed' => false,
                    'appointment_rating' => 4.5,
                    'appointment_is_rated' => false,
                    'appointment_is_expired' => $is_expired,
                ];
                array_push($tranformed_appointments, $temp);
            }
        }
        return $tranformed_appointments;

    }

    // transform patient appointments
    public static function transformAppointmentRecords($appointments)
    {
        $tranformed_appointments = [];
        $tranformed_medical_record = [];
        if (!empty($appointments->patientPrescription)) {
            foreach ($appointments->patientPrescription as $prescription) {
                $reports = [];
                $prescriptions = [];
                $invoices = [];
                $temp = [
                    "prescription_id" => $prescription->id,
                    "prescription_date" => $prescription->created_at,
                    "prescription_uploaded_by" => $prescription->uploaded_by,
                    "relative_id" => $prescription->relation_id,
                    "relative_name" => $prescription->relative_name,
                    "relative_relation" => $prescription->relative_relation,
                ];
                foreach ($prescription->medicalRecords as $record) {
                    if ($record->Type == 'report') {
                        $temp_report = [
                            'id' => $record->id,
                            'file' => Storage::disk('s3')->exists($record->image_url) ? Storage::disk('s3')->url($record->image_url) : url(Storage::url('files/no-image.png')),
                            'type' => $record->Type,
                        ];
                        array_push($reports, $temp_report);
                    }
                    if ($record->Type == 'prescription') {
                        $temp_prescription = [
                            'id' => $record->id,
                            'file' => Storage::disk('s3')->exists($record->image_url) ? Storage::disk('s3')->url($record->image_url) : url(Storage::url('files/no-image.png')),
                            'type' => $record->Type,
                        ];
                        array_push($prescriptions, $temp_prescription);
                    }
                    if ($record->Type == 'invoice') {
                        $temp_invoice = [
                            'id' => $record->id,
                            'file' => Storage::disk('s3')->exists($record->image_url) ? Storage::disk('s3')->url($record->image_url) : url(Storage::url('files/no-image.png')),
                            'type' => $record->Type,
                        ];
                        array_push($invoices, $temp_invoice);
                    }
                }
                $tranformed_appointments['reports'] = $reports;
                $tranformed_appointments['prescriptions'] = $prescriptions;
                $tranformed_appointments['invoices'] = $invoices;
                $tranformed_appointments['invoices'] = $invoices;
                $temp['files'] = $tranformed_appointments;
                array_push($tranformed_medical_record, $temp);
            }
        }
        return $tranformed_medical_record;
    }

    // transform patient relations
    public static function transformRelation($collection)
    {
        $transformed_relations = [];
        foreach ($collection as $relation) {
            $temp = [
                'id' => $relation->id,
                'name' => $relation->name,
                'relation' => $relation->relation,
                'number' => $relation->phone ? $relation->phone : "",
                'gender' => $relation->gender ? $relation->gender : "",
            ];
            array_push($transformed_relations, $temp);
        }
        return $transformed_relations;
    }

    //transform doctors of a patient
    public static function transformDoctors($patient, $appointments)
    {
        $tranformed_doctors = [];
        if (!empty($patient)) {
            foreach ($appointments as $appointment) {
                $temp = [];
                $temp['id'] = $appointment->doctor->user_id;
                $temp['is_instant'] = ($appointment->doctor->is_instant === 'true') ? true : false;
                $temp['name'] = $appointment->doctor->user->name;
                $temp['image'] = Storage::disk('s3')->exists($appointment->doctor->user->profile_image) ? Storage::disk('s3')->url($appointment->doctor->user->profile_image) : url(Storage::url('files/no-image.png'));
                $temp['gender'] = $appointment->doctor->gender;
                $doctor = $appointment->doctor;
                if (!empty($doctor)) {
                    $temp['fee'] = $doctor->videoConsultancy->fee;
                    //speciality
                    $specialities = '';
                    foreach ($doctor->specialities as $speciality) {
                        $specialities .= $speciality->speciality->name . '/ ';
                    }
                    $temp['speciality'] = rtrim($specialities, "/ ");
                    foreach ($doctor->videoConsultancyDays as $consultancyDay) {
                        if ($consultancyDay->day == date('D')) {
                            $temp['availability_status'] = true;
                        } else {
                            $temp['availability_status'] = false;
                        }
                    }
                }
                array_push($tranformed_doctors, $temp);
            }
        }
        return $tranformed_doctors;
    }

    //transform patient records
    public static function transformPatientRecords($records)
    {
        $tranformed_records = [];
        if (!empty($records)) {
            foreach ($records as $record) {
                $prescription_records = [];
                $prescription_records['prescription_id'] = $record->id;
                if ($record->appointments_id === null) {
                    $prescription_records['is_appointment_id'] = false;
                    $prescription_records['prescription_uploaded_by'] = $record->uploaded_by;
                    $prescription_records['name'] = $record->name;
                    $prescription_records['prescription_date'] = date('Y-m-d', strtotime($record->created_at));
                } else {
                    $prescription_records['is_appointment_id'] = true;
                    $prescription_records['prescription_uploaded_by'] = $record->uploaded_by;
                    $prescription_records['relative_name'] = $record->name;
                    $prescription_records['prescription_date'] = date('Y-m-d', strtotime($record->created_at));
                    $specialities = !empty($record->specialities[0]) ? $record->specialities[0]->speciality->name : "";
                    if (empty($record->specialities[0])) {
                        $prescription_records['doctor_name'] = $record->doctorProfile->user->name;
                    } else {
                        $prescription_records['doctor_name'] = $record->doctorProfile->user->name . ', ' . $specialities;
                    }
                }
                $reports = [];
                $invoices = [];
                $prescriptions = [];
                foreach ($record->medicalRecords as $medical_record) {
                    $file_type = "image";
                    $ext = pathinfo($medical_record->image_url, PATHINFO_EXTENSION);
                    if ($ext == "pdf") {
                        $file_type = "file";
                    }
                    if ($medical_record->Type === 'report') {
                        $temp_report = [
                            'id' => $medical_record->id,
                            'type' => $file_type,
                            'file' => Storage::disk('s3')->exists($medical_record->image_url) ? Storage::disk('s3')->url($medical_record->image_url) : url(Storage::url('files/no-image.png')),
                        ];
                        array_push($reports, $temp_report);
                    }
                    if ($medical_record->Type === 'invoice') {
                        $temp_invoice = [
                            'id' => $medical_record->id,
                            'type' => $file_type,
                            'file' => Storage::disk('s3')->exists($medical_record->image_url) ? Storage::disk('s3')->url($medical_record->image_url) : url(Storage::url('files/no-image.png')),
                        ];
                        array_push($invoices, $temp_invoice);
                    }
                    if ($medical_record->Type === 'prescription') {
                        $temp_prescription = [
                            'id' => $medical_record->id,
                            'type' => $file_type,
                            'file' => Storage::disk('s3')->exists($medical_record->image_url) ? Storage::disk('s3')->url($medical_record->image_url) : url(Storage::url('files/no-image.png')),
                        ];
                        array_push($prescriptions, $temp_prescription);
                    }
                }
                $prescription_files = [];
                $prescription_files['reports'] = $reports;
                $prescription_files['invoices'] = $invoices;
                $prescription_files['prescriptions'] = $prescriptions;
                $prescription_records['files'] = $prescription_files;
                array_push($tranformed_records, $prescription_records);
            }
        }
        return $tranformed_records;
    }

    //transform prescription record
    public static function transformPrescriptionRecord($record)
    {
        $prescription_records = [];
        $prescription_records['prescription_id'] = $record->id;
        if ($record->appointments_id === null) {
            $prescription_records['is_appointment_id'] = false;
            $prescription_records['uploaded_by'] = $record->uploaded_by;
            $prescription_records['name'] = $record->name;
            $prescription_records['created_at'] = date('Y-m-d', strtotime($record->created_at));
        } else {
            $prescription_records['is_appointment_id'] = true;
            $prescription_records['uploaded_by'] = $record->uploaded_by;
            $prescription_records['name'] = $record->name;
            $prescription_records['created_at'] = date('Y-m-d', strtotime($record->created_at));
            $specialities = !empty($record->specialities[0]) ? $record->specialities[0]->speciality->name : "";
            if (empty($record->specialities[0])) {
                $prescription_records['doctor_name'] = $record->doctorProfile->user->name;
            } else {
                $prescription_records['doctor_name'] = $record->doctorProfile->user->name . ', ' . $specialities;
            }
        }
        $reports = [];
        $invoices = [];
        $prescriptions = [];
        foreach ($record->medicalRecords as $medical_record) {
            $file_type = "image";
            $ext = pathinfo($medical_record->image_url, PATHINFO_EXTENSION);
            if ($ext == "pdf") {
                $file_type = "file";
            }
            if ($medical_record->Type === 'report') {
                $temp_report = [
                    'id' => $medical_record->id,
                    'file_type' => $file_type,
                    'file_url' => Storage::disk('s3')->exists($medical_record->image_url) ? Storage::disk('s3')->url($medical_record->image_url) : url(Storage::url('files/no-image.png')),
                ];
                array_push($reports, $temp_report);
            }
            if ($medical_record->Type === 'invoice') {
                $temp_invoice = [
                    'id' => $medical_record->id,
                    'file_type' => $file_type,
                    'file_url' => Storage::disk('s3')->exists($medical_record->image_url) ? Storage::disk('s3')->url($medical_record->image_url) : url(Storage::url('files/no-image.png')),
                ];
                array_push($invoices, $temp_invoice);
            }
            if ($medical_record->Type === 'prescription') {
                $temp_prescription = [
                    'id' => $medical_record->id,
                    'file_type' => $file_type,
                    'file_url' => Storage::disk('s3')->exists($medical_record->image_url) ? Storage::disk('s3')->url($medical_record->image_url) : url(Storage::url('files/no-image.png')),
                ];
                array_push($prescriptions, $temp_prescription);
            }
        }
        $prescription_records['reports'] = $reports;
        $prescription_records['invoices'] = $invoices;
        $prescription_records['prescriptions'] = $prescriptions;
        return $prescription_records;
    }

    static function cm2feet($cm)
    {
        $inches = $cm / 2.54;
        $feet = intval($inches / 12);
        $inches = $inches % 12;
        return sprintf('%d ft %d ins', $feet, $inches);
    }

    //Online Consultation Booking Information
    public static function transformOnlineSlotBooking($appointment, $id, $user)
    {

        if (!empty($appointment)) {
            /*******************************calling time logic here start*******************************************/
            $auth_user = Auth::user();
            $auth_user_name = $auth_user->name;
            $currenttime = date("H:i");
            $today = date("Y-m-d");
            $time = strtotime($appointment->start_time);
            $endTime = date("H:i", strtotime('+30 minutes', $time));
            $minusTime = date("H:i", strtotime('+31 minutes', $time));
            //dd($endTime);
            if (($appointment->booking_date < $today) || (($appointment->booking_date == $today) && ($currenttime > $endTime))) {
                //dd('session expire');
                $can_call = false;
                $is_expired = true;
            } elseif (($appointment->booking_date == $today) && (($currenttime >= $appointment->start_time) && ($currenttime <= $minusTime))) {
                //dd('session start');
                $can_call = true;
                $is_expired = false;
            } elseif (($appointment->booking_date > $today) || (($appointment->booking_date == $today) && ($currenttime < $appointment->start_time))) {
                //dd('you are too early');
                $can_call = false;
                $is_expired = false;
            }
            /*******************************calling time logic here end*******************************************/

            $random = Str::random(30);
            $uid = $id;
            $appID = config('app.agora_app_id');
            $app_secret = config('app.agora_secret_key');
            $channelName = $random;
            $rout = "https://patient.eshaafi.com/video-calling?channel_name=" . $random;
            $role = RtcTokenBuilder::RolePublisher;
            $expireTimeInSeconds = 86400;
            $currentTimestamp = (new  \DateTime("now", new  \DateTimeZone('UTC')))->getTimestamp();
            $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;
            $doctor_token = RtcTokenBuilder::buildTokenWithUid($appID, $app_secret, $channelName, $uid, $role, $privilegeExpiredTs);
            // for patient token
            $patient_token = RtcTokenBuilder::buildTokenWithUid($appID, $app_secret, $channelName, $auth_user->id, $role, $privilegeExpiredTs);
            /*Save Token for Every user for video calling*/
            $phone = Auth::user()->phone;
            Channel::Create([
                'patient_token' => $patient_token,
                'doctor_token' => $doctor_token,
                'name' => $channelName,
                'appointment_id' => $appointment->id,
            ]);

            if (!empty($phone)) {
                $username = config('app.send_pk_username');///Your Username
                $password = config('app.send_pk_password');///Your Password
                $mobile = $phone;
                $sender = "Eshaafi";

                $message = "Dear $auth_user_name;
                    Your video consultation with $user->name has been booked at $appointment->booking_date, $appointment->start_time  use the link
                    below to join the video consultation;
                    $rout
                    make sure you have a camera enabled smartphone/laptop with the latest browser and stable internet
                    connection for having video consultation.
                    Regard: eshaafi.com
                    Tel: 03011166522";

                $post = "sender=" . urlencode($sender) . "&mobile=" . urlencode($mobile) . "&message=" . urlencode($message) . "";
                $url = "https://sendpk.com/api/sms.php?username=" . $username . "&password=" . $password . "";
                $ch = curl_init();
                $timeout = 10; // set to zero for no timeout
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)');
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                curl_exec($ch);

            }
            // send SMS to user
            $data = [
                'appointment_id' => $appointment->id,
                'message' => 'Appointment created successfully'
            ];

            return $data;
        }

    }
}
