<?php

namespace App\Traits;

use App\Models\Appointment;
use App\Models\Relation;

use App\User;
use Illuminate\Support\Facades\Storage;
use DateTime;
use phpDocumentor\Reflection\Types\Float_;
use phpDocumentor\Reflection\Types\Self_;
use Ramsey\Uuid\Type\Integer;
use Illuminate\Support\Arr;

trait DoctorTransformer
{
    // transform doctor profile
    public static function transformProfile($patient)
    {
        $transformed_patient = [];
        if (!empty($patient)) {
            $profile = $patient;
            $transformed_patient['dob'] = (string)$profile->dob;
            $transformed_patient['blood_group'] = (string)$profile->blood_group;
            $transformed_patient['weight'] = (float)$profile->weight;
            //$transformed_patient['height'] = SELF::cm2feet($profile->height);
            $transformed_patient['height'] = (string)$profile->height;
            $transformed_patient['gender'] = (string)$profile->gender;
            $transformed_patient['marital_status'] = (string)$profile->marital_status;
        }
        return $transformed_patient;
    }

    // transform patient appointments
    public static function transformPatientAppointments($patient)
    {
        $tranformed_appointments = [];
        if (!empty($patient)) {
            foreach ($patient->appointments as $appointment) {
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
    public static function transformPatients($doctor, $appointments, $device_type)
    {
        $tranformed_patients = [];
        if (!empty($doctor)) {
            foreach ($appointments as $appointment) {
                $tranformed_appointments = [];
                $tranformed_appointments['patient_id'] = $appointment->patient->user->id;
                $tranformed_appointments['patient_name'] = $appointment->patient->user->name;
                $tranformed_appointments['patient_image'] = Storage::disk('s3')->exists($appointment->patient->user->profile_image) ? Storage::disk('s3')->url($appointment->patient->user->profile_image) : url(Storage::url('files/no-image.png'));
                $tranformed_appointments['patient_phone_number'] = $appointment->patient->user->phone;
                $tranformed_appointments['patient_email'] = $appointment->patient->user->email;
                $tranformed_appointments['patient_dob'] = (string)$appointment->patient->dob ? $appointment->patient->dob : '';
                $tranformed_appointments['patient_blood_group'] = (string)$appointment->patient->blood_group ? $appointment->patient->blood_group : '';
                $tranformed_appointments['patient_weight'] = (float)$appointment->patient->weight ? $appointment->patient->weight : 0.0;
                $tranformed_appointments['patient_height'] = $appointment->patient->height ? $appointment->patient->height : '';
                $tranformed_appointments['patient_gender'] = (string)$appointment->patient->gender ? $appointment->patient->gender : '';
                $tranformed_appointments['patient_marital_status'] = (string)$appointment->patient->marital_status ? $appointment->patient->marital_status : '';
                $tranformed_appointments['patient_age'] = (string)$appointment->patient->age ? $appointment->patient->age : '';
                $tranformed_appointments['patient_address'] = (string)$appointment->patient->address ? $appointment->patient->address : '';
                array_push($tranformed_patients, $tranformed_appointments);
            }
        }
        return $tranformed_patients;
    }

    // transform Doctor appointments
    public static function transformDoctorAppointments($doctor, $appointments, $is_instant = false)
    {
        $tranformed_appoints = [];
        $tranformed_appointments['files'] = [];
        if (!empty($doctor)) {
            foreach ($appointments as $appointment) {

                $tranformed_appointments = [];
                $tranformed_appointments['appointment_id'] = $appointment->id;
                $tranformed_appointments['patient_id'] = $appointment->patient_profile_id;
                $tranformed_appointments['patient_name'] = $appointment->patient->user->name;
                $tranformed_appointments['patient_image'] = Storage::disk('s3')->exists($appointment->patient->user->profile_image) ? Storage::disk('s3')->url($appointment->patient->user->profile_image) : url(Storage::url('files/no-image.png'));
                $tranformed_appointments['patient_phone_number'] = $appointment->patient->user->phone;
                $tranformed_appointments['patient_gender'] = $appointment->patient->gender;

                // if reletive data exist

                if ($appointment->patient_type == "other") {
                    $relative = self::getRelativeName($appointment->relation_id);
                    $tranformed_appointments['patient_gender'] = $relative->gender;
                    $tranformed_appointments['patient_name'] = $relative->name;
                    $tranformed_appointments['patient_relation'] = $relative->relation;
                    $tranformed_appointments['patient_phone_number'] = $relative->phone;
                }
                $tranformed_appointments['fee_status'] = $appointment->fee_status;
                $tranformed_appointments['fee'] = !empty($doctor->profile->videoConsultancy) ? $doctor->profile->videoConsultancy->fee : 0.0;
                $tranformed_appointments['patient_email'] = $appointment->patient->user->email;
                $tranformed_appointments['appointment_date'] = $appointment->booking_date;
                $tranformed_appointments['appointment_time'] = $appointment->start_time;
                $tranformed_appointments['appointment_status'] = $appointment->appointment_status;
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
                $tranformed_appointments['can_call'] = $can_call;
                $tranformed_appointments['is_expired'] = $is_expired;
                $tranformed_appointments['is_patient_called'] = !empty($appointment->channel) ? $appointment->channel->is_patient_called : false;
                $tranformed_appointments['is_doctor_called'] = !empty($appointment->channel) ? $appointment->channel->is_doctor_called : false;
                if ($is_instant === false) {
                    $temp['doctor_specialty'] = !empty($doctor->specialities[0]) ? $doctor->specialities[0]->speciality->name : "";
                } else {
                    if (!empty($appointment->symptoms)) {
                        $symptom_array = [];
                        foreach ($appointment->symptoms as $value) {
                            if (!empty($value->symptom)) {
                                $symptom_temp = [
                                    'id' => $value->symptom->id,
                                    'name_en' => $value->symptom->name_en,
                                    'name_ur' => $value->symptom->name_ur,
                                    'icon' => Storage::disk('s3')->exists($appointment->patient->user->profile_image) ? Storage::disk('s3')->url($value->symptom->icon) : url(Storage::url('icon/no-image.png')),
                                ];
                                array_push($symptom_array, $symptom_temp);
                            }
                        }
                        $tranformed_appointments['symptoms'] = $symptom_array;
                    }

                }
                if ($appointment->prescription->count() > 0) {
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
                            $tranformed_appointments['doctor_files'] = [];
                            $tranformed_appointments['patient_files'] = [];
                        }
                    }
                    $tranformed_appointments['doctor_files'] = (array)$files_record;
                    $tranformed_appointments['patient_files'] = (array)$patient_files_record;
                } else {
                    $tranformed_appointments['doctor_files'] = [];
                    $tranformed_appointments['patient_files'] = [];
                }
                array_push($tranformed_appoints, $tranformed_appointments);
            }
        }
        return $tranformed_appoints;
    }

    // transform patient appointments
    public static function transformAppointmentRecords($appointments)
    {
        $tranformed_appointments = [];
        if (!empty($appointments->prescription)) {
            $tranformed_appointments['date'] = date('Y-m-d', strtotime($appointments->prescription->created_at));
            $tranformed_appointments['Upload_by'] = $appointments->prescription->uploaded_by;
            $tranformed_appointments['relative_id'] = $appointments->relation_id;
            $tranformed_appointments['relative_name'] = $appointments->relation->name;
            $tranformed_appointments['relative_relation'] = $appointments->relation->relation;
        }
        $reports = [];
        $prescriptions = [];
        $invoices = [];
        foreach ($appointments->records as $record) {
            if ($record->Type == 'report') {
                $temp = [
                    'id' => $record->id,
                    'file' => Storage::disk('s3')->exists($record->image_url) ? Storage::disk('s3')->url($record->image_url) : url(Storage::url('files/no-image.png')),
                    'type' => $record->Type,
                ];
                array_push($reports, $temp);
            }
            if ($record->Type == 'prescription') {
                $temp = [
                    'id' => $record->id,
                    'file' => Storage::disk('s3')->exists($record->image_url) ? Storage::disk('s3')->url($record->image_url) : url(Storage::url('files/no-image.png')),
                    'type' => $record->Type,
                ];
                array_push($prescriptions, $temp);
            }
            if ($record->Type == 'invoice') {
                $temp = [
                    'id' => $record->id,
                    'file' => Storage::disk('s3')->exists($record->image_url) ? Storage::disk('s3')->url($record->image_url) : url(Storage::url('files/no-image.png')),
                    'type' => $record->Type,
                ];
                array_push($invoices, $temp);
            }
        }
        $tranformed_appointments['reports'] = $reports;
        $tranformed_appointments['prescriptions'] = $prescriptions;
        $tranformed_appointments['invoices'] = $invoices;

        return $tranformed_appointments;
    }

    static function cm2feet($cm)
    {
        $inches = $cm / 2.54;
        $feet = intval($inches / 12);
        $inches = $inches % 12;
        return sprintf('%d ft %d ins', $feet, $inches);
    }

    // transform edit doctor
    public static function transformDoctorProfile($doctor)
    {
        $transformed_doctor = [];
        $temp = [
            'id' => !empty($doctor->id) ? $doctor->id : "",
            'image' => $doctor->profile_image ? url(Storage::disk('s3')->url($doctor->profile_image)) : url('files/no_image.png'),
            'name' => $doctor->name ? $doctor->name : '',
            'email' => $doctor->email ? $doctor->email : '',
            'gender' => $doctor->profile->gender ? $doctor->profile->gender : '',
            'city' => $doctor->profile->city ? $doctor->profile->city : '',
            'pmdc' => $doctor->profile->pmdc ? $doctor->profile->pmdc : '',
            'address' => $doctor->profile->address ? $doctor->profile->address : '',
            'date_of_birth' => $doctor->profile->dob ? $doctor->profile->dob : '',
            'country' => $doctor->profile->country ? $doctor->profile->country : '',
            'is_video_enable' => $doctor->profile->is_video_enable ? $doctor->profile->is_video_enable : false,
            'is_instant' => $doctor->profile->is_instant === 'true' ? true : false,
            'phone' => $doctor->phone ? $doctor->phone : '',
            'created_at' => !empty($doctor->created_at) ? $doctor->created_at : '',
            'rating' => 0,
            'summary' => !empty($doctor->profile->summary) ? $doctor->profile->summary : '',
            //'speciality' => $doctor->profile->specialities[0]->speciality['name'] ? $doctor->profile->specialities[0]->speciality['name'] : '',
        ];
        //education

        if (!empty($doctor->profile->education)) {
            $qualifications = [];
            foreach ($doctor->profile->education as $education) {
                $temp_edu = [
                    'id' => (integer)$education->id,
                    'degree' => (string)$education->degree,
                    'edu_start_date' => (string)$education->start_date,
                    'edu_end_date' => (string)$education->end_date,
                    'institute' => (string)$education->institute_name,
                    'edu_country' => (string)$education->country,
                ];
                array_push($qualifications, $temp_edu);
            }
        }

        //speciality
        if ($doctor->profile->is_instant == 'false') {
            if (!empty($doctor->profile->specialities)) {
                $specialities = [];
                foreach ($doctor->profile->specialities as $speciality) {
                    if (isset($speciality->speciality->id)) {
                        $temp_specialities = [
                            'id' => $speciality->speciality->id,
                            'name' => $speciality->speciality->name,
                            'doctor_specilaity' => $speciality->id,
                        ];
                        array_push($specialities, $temp_specialities);
                    }


                }
            }
        } else {
            $specialities = [];
        }

        //availability_status
        if (!empty($doctor->profile->videoConsultancyDays)) {
            foreach ($doctor->profile->videoConsultancyDays as $consultancyDay) {
                if (date('D') == $consultancyDay->day) {
                    $temp['availability_status'] = true;
                    break;
                } else {
                    $temp['availability_status'] = false;
                }
            }
        }
        // speciality list
        $temp['speciality'] = $specialities;
        //education List
        $temp['education'] = $qualifications;

        // doctor services
        if (!empty($doctor->profile->services)) {
            $doctor_services = [];
            foreach ($doctor->profile->services as $doctor_service) {
                $temp_service = [
                    'id' => $doctor_service->service->id ? $doctor_service->service->id : '',
                    'name' => (string)$doctor_service->service->name ? $doctor_service->service->name : '',
                    'doctor_serice_id' => $doctor_service->id ? $doctor_service->id : '',
                ];
                array_push($doctor_services, $temp_service);
            }
        }

        $temp['services'] = $doctor_services;

        //doctor awards
        if (!empty($doctor->profile->awards)) {
            $awards = [];
            foreach ($doctor->profile->awards as $award) {
                $temp_award = [
                    'id' => $award->id,
                    'award_achivements' => (string)$award->achievement ? $award->achievement : '',
                    'award_event_name' => (string)$award->event_name ? $award->event_name : '',
                    'award_desigination' => (string)$award->designation ? $award->designation : '',
                    'award_recive_award' => (string)$award->award ? $award->award : '',
                    'award_recive_from' => (string)$award->received_from ? $award->received_from : '',
                    'award_recived_dated' => (string)$award->dated ? $award->dated : '',
                    'award_country' => (string)$award->country ? $award->country : '',
                ];
                array_push($awards, $temp_award);
            }
        }

        $temp['awards'] = $awards;

        // languages
        if (!empty($doctor->profile->languag)) {
            $language = [];
            foreach ($doctor->profile->languag as $languag) {
                $temp_language = [
                    'name' => (string)$languag->language ? $languag->language : '',
                ];
                array_push($language, $temp_language);
            }
        }

        $temp['language'] = $language;

        // experience section
        if (!empty($doctor->profile->experiences)) {
            $experience_detail = [];
            $total_experience = 0;
            foreach ($doctor->profile->experiences as $experience) {
                $start_time = new DateTime($experience->start_date);
                $end_time = new DateTime($experience->end_date);
                $interval = $start_time->diff($end_time);
                $total_experience += $interval->format('%y');

                $y = $interval->format('%y');
                $m = $interval->format('%m');
                if ($y == 0 && $m >= 10) {
                    $total_experience += 1;
                }

                $temp_exp = [
                    'id' => $experience->id ? $experience->id : '',
                    'exp_hosp_name' => $experience->hospital_name ? $experience->hospital_name : '',
                    'exp_desigination' => $experience->designation ? $experience->designation : '',
                    'exp_start_date' => $experience->start_date ? $experience->start_date : '',
                    'exp_end_date' => $experience->end_date ? $experience->end_date : '',
                    'exp_country' => $experience->country ? $experience->country : '',
                ];
                array_push($experience_detail, $temp_exp);
            }
            $temp['total_experience'] = $total_experience;
        }
        $temp['experience'] = $experience_detail;
        //video consultancy
        if (isset($doctor->profile->videoConsultancy->id)) {
            $boolean = strtolower($doctor->profile->videoConsultancy->is_online) == 'true' ? true : false;
            //dd($boolean);
            $is_online = $doctor->profile->videoConsultancy->is_online === "true" ? true : false;
            $emailNotification = $doctor->profile->videoConsultancy->is_email_notification_enabled === "true" ? true : false;
            //dd($emailNotification);
            $consultancy_temp = [];
            $consultancy_temp['video_consult_id'] = $doctor->profile->videoConsultancy->id ? $doctor->profile->videoConsultancy->id : '';
            $consultancy_temp['video_consultation_fee'] = $doctor->profile->videoConsultancy->fee ? $doctor->profile->videoConsultancy->fee : '';
            $consultancy_temp['v_c_waiting_time'] = $doctor->profile->videoConsultancy->waiting_time ? $doctor->profile->videoConsultancy->waiting_time : '';
            $consultancy_temp['is_online'] = $is_online;
            $consultancy_temp['emailNotification'] = $emailNotification;

        }
        //video consultancy days
        if (!empty($doctor->profile->videoConsultancyDays)) {
            $videoConsultancyDays = [];
            foreach ($doctor->profile->videoConsultancyDays as $consult_days) {
                $temp_video_days = [
                    'id' => $consult_days->id ? $consult_days->id : '',
                    'video_day' => $consult_days->day ? $consult_days->day : '',
                    'video_duration' => $consult_days->duration ? $consult_days->duration : '',
                    'video_start_time' => $consult_days->start_time ? $consult_days->start_time : '',
                    'video_end_time' => $consult_days->end_time ? $consult_days->end_time : '',
                ];
                array_push($videoConsultancyDays, $temp_video_days);
            }
        }
        $consultancy_temp['video_consultation_day'] = $videoConsultancyDays;
        $temp['video_consultation'] = $consultancy_temp;
        $transformed_doctor = $temp;
        return $transformed_doctor;
    }

    static function getRelativeName($relative_id)
    {

        /*$realative = Relation::whereId($relative_id)->whereHas('patientProfle')->with(['patientProfle' => function ($sub_query) {
            $sub_query->with(['user']);
        }])->first();*/
        $realative = Relation::whereId($relative_id)->first();
        return $realative;
    }

    // transform doctor profile
    public static function transformPatientProfile($patient)
    {
        $transformed_patient = [];
        if (!empty($patient)) {
            if (!empty($patient->patientProfile)) {
                $user_data = $patient;
                $profile = $patient->patientProfile;
                $transformed_patient['dob'] = (string)$profile->dob ? $profile->dob : '';
                $transformed_patient['blood_group'] = (string)$profile->blood_group ? $profile->blood_group : '';
                $transformed_patient['weight'] = (float)$profile->weight ? $profile->weight : 0.0;
                $transformed_patient['height'] = $profile->height ? $profile->height : '';
                //$transformed_patient['height'] = $profile->height ? SELF::cm2feet($profile->height) : '';
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
}
