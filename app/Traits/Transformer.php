<?php

namespace App\Traits;

use App\Models\Appointment;
use App\Models\Faq;
use Illuminate\Support\Facades\Storage;
use DateTime;
use PhpParser\Node\Expr\Cast\Double;
use Ramsey\Uuid\Type\Integer;
use Illuminate\Support\Arr;

trait Transformer
{
    //transform Collection
    public static function transformCollection($collection)
    {
        $params = http_build_query(request()->except('page'));
        $next = $collection->nextPageUrl();
        $previous = $collection->previousPageUrl();
        $current = $collection->currentPage();
        if ($params) {
            if ($next) {
                $next .= "&{$params}";
            }
            if ($previous) {
                $previous .= "&{$params}";
            }
        }
        $meta = [
            "next" => (string)$next,
            "previous" => (string)$previous,
            "per_page" => (integer)$collection->perPage(),
            "current_page" => (integer)$current,
            "total" => (integer)$collection->total()
        ];
        return $meta;
    }

    // auth user login and also for register response body
    public static function transformUser($user, $token = '', $device_key = null, $is_auth = false)
    {
        //$avatar = $user->profile_image ? url(Storage::url($user->profile_image)) : 'files/no-image.png';
        $avatar = Storage::disk('s3')->exists($user->profile_image) ? Storage::disk('s3')->url($user->profile_image) : url(Storage::url('files/no-image.png'));
        $transformed_user = [
            'id' => (int)$user->id,
            'name' => (string)$user->name,
            'profile_image' => (string)$avatar,
            'email' => (string)$user->email,
            'phone' => (string)$user->phone,
            'user_name' => (string)$user->user_name,
            'device_key' => (string)$device_key ? $device_key : '',

        ];
        if($user->user_type === 'doctor') {
            $transformed_user['is_instant'] = ($user->profile->is_instant === 'true') ? true : false;
        }
        if ($token) {
            $transformed_user['token'] = (string)$token;
        }
        return $transformed_user;
    }

    // transform doctors
    public static function transformDoctors($collection)
    {
        $transformed_doctor = [];

        foreach ($collection as $doctor) {
            /*using temp array */
            $temp = [
                'id' => $doctor->id,
                'image' => Storage::disk('s3')->exists($doctor->profile_image) ? Storage::disk('s3')->url($doctor->profile_image) : url(Storage::url('files/no-image.png')),
                'name' => $doctor->name ? $doctor->name : '',
                'email' => $doctor->email ? $doctor->email : '',
                'gender' => !empty($doctor->profile) ? $doctor->profile->gender : '',
                'city' => !empty($doctor->profile) ? $doctor->profile->city : '',
                'country' => !empty($doctor->profile) ? $doctor->profile->country : '',
                'phone' => $doctor->phone ? $doctor->phone : '',
                'created_at' => !empty($doctor->created_at) ? $doctor->created_at : '',
                'speciality' => !empty($doctor->specialities) ? $doctor->profile->specialities[0]->speciality['name'] : '',
            ];


            /*using temp array */
            //education
            if (!empty($doctor->profile)) {
                $qualifications = '';
                foreach ($doctor->profile->education as $education) {
                    $qualifications .= $education->degree . ', ';
                }
                $temp['qualification'] = rtrim($qualifications, ", ");
                //speciality
                $specialities = '';
                foreach ($doctor->profile->specialities as $speciality) {
                    $specialities .= $speciality->speciality->name . ', ';
                }
                $temp['speciality'] = rtrim($specialities, ", ");
                //ratings

                $temp['rating'] = empty($doctor->profile->ratings) ? $doctor->profile->ratings->star_value : 0.0;
                $total_experience = 0;
                foreach ($doctor->profile->experiences as $experience) {
                    $start_time = new DateTime($experience->start_date);
                    $end_time = new DateTime($experience->end_date);
                    $interval = $start_time->diff($end_time);
                    $y = $interval->format('%y');
                    $m = $interval->format('%m');
                    //dd($m);
                    $total_experience += $interval->format('%y');
                    if ($y == 0 && $m >= 10) {
                        $total_experience += 1;
                    }
                }
                $temp['experience'] = $total_experience;
                $temp['fee'] = $doctor->profile->videoConsultancy->fee;
                $temp['city'] = $doctor->profile->city;
                foreach ($doctor->profile->videoConsultancyDays as $consultancyDay) {

                    if ($consultancyDay->day == date('D')) {
                        $temp['availability_status'] = true;
                        break;
                    } else {
                        $temp['availability_status'] = false;
                    }
                }
            }
            array_push($transformed_doctor, $temp);
        }

        return $transformed_doctor;
    }

// transform Admin Doctor List
    public static function transformAdminDoctors($doctorCollection)
    {
        $transformed_doctor = [];

        if (!empty($doctorCollection)) {
            foreach ($doctorCollection as $doctor) {
                /*using temp array */
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

                // faqs
                $faqs = Self::getFaqs($doctor->id);

                $faqs_array = [];
                if (!empty($faqs)) {

                    foreach ($faqs as $faq) {
                        $temp_faq = [
                            'faq_question' => (string)$faq['question'] ? $faq['question'] : '',
                            'faq_answer' => (string)$faq['answer'] ? $faq['answer'] : '',
                        ];
                        array_push($faqs_array, $temp_faq);
                    }
                }
                $temp['faqs'] = $faqs_array;
                $consultancy_temp['video_consultation_day'] = $videoConsultancyDays;
                $temp['video_consultation'] = $consultancy_temp;
                array_push($transformed_doctor, $temp);
                /*using temp array */
            }
        }
        return $transformed_doctor;

    }

// transform doctor profile
    public static function transformDoctorProfile($doctor, $user)
    {

        $transformed_doctor = [];
        $qualifications = [];
        $total_qualification = '';
        $specialities = '';
        $doctor_services = [];
        $specialits_detail = [];
        $experiences = [];
        $memberships = [];
        $faqs_array = [];
        $awards = [];
        $total_experience = 0;
        $transformed_doctor['id'] = $user->id;
        $transformed_doctor['image'] = Storage::disk('s3')->exists($user->profile_image) ? Storage::disk('s3')->url($user->profile_image) : url(Storage::url('files/no-image.png'));
        $transformed_doctor['name'] = (string)$user->name ? $user->name : '';
        //education

        if (!empty($doctor->profile)) {
            if (!empty($doctor->profile->education)) {
                foreach ($doctor->profile->education as $education) {
                    $temp = [
                        'id' => (integer)$education->id,
                        'degree' => (string)$education->degree,
                        'start_date' => (string)$education->start_date,
                        'end_date' => (string)$education->end_date,
                        'institute_name' => (string)$education->institute_name,
                        'country' => (string)$education->country,
                    ];
                    $total_qualification .= $education->degree . ', ';
                    array_push($qualifications, $temp);
                }
                $transformed_doctor['qualification'] = rtrim($total_qualification, ", ");
                //education List
                $transformed_doctor['educations'] = $qualifications;
            }
            if (!empty($doctor->profile->specialities)) {
                //speciality
                foreach ($doctor->profile->specialities as $speciality) {
                    $specialities .= $speciality->speciality->name . ', ';

                    // speciality detail
                    $temp_specialities = [
                        'id' => (integer)$speciality->speciality->id,
                        'name' => (string)$speciality->speciality->name,

                    ];

                    array_push($specialits_detail, $temp_specialities);
                }
                $transformed_doctor['speciality'] = rtrim($specialities, ", ");
                $transformed_doctor['speciality_detail'] = $specialits_detail;
            }

            $transformed_doctor['rating'] = 0;
            //experience
            if (!empty($doctor->profile->experiences)) {
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
                    // experiences detail here

                    $temp_experiences = [
                        'id' => (integer)$experience->id,
                        'hospital_name' => (string)$experience->hospital_name,
                        'designation' => (string)$experience->designation,
                        'country' => (string)$experience->country,
                        'start_date' => (string)$experience->start_date,
                        'end_date' => (string)$experience->end_date,
                    ];

                    array_push($experiences, $temp_experiences);
                }
                $transformed_doctor['experience'] = $total_experience;
                $transformed_doctor['experiences'] = $experiences;
            }

            $transformed_doctor['fee'] = !empty($doctor->profile->videoConsultancy) ? $doctor->profile->videoConsultancy->fee : 0;
            $transformed_doctor['city'] = $doctor->profile->city;
            //availability_status
            if (!empty($doctor->profile->videoConsultancyDays)) {
                foreach ($doctor->profile->videoConsultancyDays as $consultancyDay) {
                    if (date('D') == $consultancyDay->day) {
                        $transformed_doctor['availability_status'] = true;
                        break;
                    } else {
                        $transformed_doctor['availability_status'] = false;
                    }
                }
            }
            // doctor services
            if (!empty($doctor->profile->services)) {
                foreach ($doctor->profile->services as $doctor_service) {
                    $temp = [
                        'id' => $doctor_service->service->id ? $doctor_service->service->id : '',
                        'name' => (string)$doctor_service->service->name ? $doctor_service->service->name : '',
                    ];
                    array_push($doctor_services, $temp);
                }
                $transformed_doctor['services'] = $doctor_services;
            }
            //doctor awards
            if (!empty($doctor->profile->awards)) {
                foreach ($doctor->profile->awards as $award) {
                    $temp = [
                        'id' => $award->id,
                        'achievement' => (string)$award->achievement ? $award->achievement : '',
                        'event_name' => (string)$award->event_name ? $award->event_name : '',
                        'designation' => (string)$award->designation ? $award->designation : '',
                        'award' => (string)$award->award ? $award->award : '',
                        'received_from' => (string)$award->received_from ? $award->received_from : '',
                        'dated' => (string)$award->dated ? $award->dated : '',
                        'country' => (string)$award->country ? $award->country : '',
                    ];
                    array_push($awards, $temp);
                }
                $transformed_doctor['awards'] = $awards;
            }

            //doctor membership
            if (!empty($doctor->profile->memberships)) {
                foreach ($doctor->profile->memberships as $membership) {
                    $temp = [
                        'id' => $membership->id ? $membership->id : '',
                        'name' => (string)$membership->details ? $membership->details : '',
                    ];
                    array_push($memberships, $temp);
                }
                $transformed_doctor['membership'] = $memberships;
            }
        }
        $faqs = Self::getFaqs($doctor->id);
        if (!empty($faqs)) {
            foreach ($faqs as $faq) {
                $temp_faq = [
                    'question' => (string)$faq['question'] ? $faq['question'] : '',
                    'answer' => (string)$faq['answer'] ? $faq['answer'] : '',
                ];
                array_push($faqs_array, $temp_faq);
            }
        }
        $transformed_doctor['faqs'] = $faqs_array;

        return $transformed_doctor;
    }


// transform slots
    public static function transformDoctorSlots($doctor, $doctor_profile, $details = false)
    {
        $doctorSlots = [];
        $slot_object = [];
        if (!empty($doctor)) {
            foreach ($doctor->profile->videoConsultancyDays as $key => $consultancyDay) {
                $start_time = $consultancyDay->start_time;
                $end_time = $consultancyDay->end_time;
                $date = $consultancyDay->day;
                $duration = $consultancyDay->duration ? $consultancyDay->duration : 30;
                /* date wise slotting*/
                $max_dates = 7;
                for ($i = 0; $i <= $max_dates; $i++) {
                    $Newday = Date('D', strtotime("+" . $i . " days"));
                    $NewDate = Date('Y-m-d', strtotime("+" . $i . " days"));
                    if ($Newday == $date) {
                        if (!empty($start_time) && !empty($end_time)) {
                            $timeslots = Self::getSlotsByDay($start_time, $end_time, $NewDate, $duration);
                            $appointment = Self::getOnlineBookedSloteByDate($doctor_profile->id, $NewDate);
                            if (!empty($appointment)) {
                                $apps = array();
                                foreach ($appointment as $app) {
                                    $apps[] = $app['start_time'];
                                }
                                $intersection = array_intersect($apps, $timeslots);
                                $slots = array_diff($timeslots, $intersection);
                                $timeslots = array_values($slots);
                            }
                            $countDates = count($timeslots);
                            $NewDate = explode('"', $NewDate);
                            $slot_object[] = [
                                "date" => $NewDate[0],
                                "slot_count" => $countDates,
                            ];
                        }
                    }

                }
                /* date wise slotting*/
                $response = self::getSlotsByDay($start_time, $end_time, $date, $duration);
                if ($details) {
                    $doctorSlots = $response;
                } else {
                    $doctorSlots = $slot_object;
                }

            }
        }
        $name = 'date';
        usort($doctorSlots, function ($a, $b) use (&$name) {
            return strtotime($a[$name]) - strtotime($b[$name]);
        });
        return $doctorSlots;
    }

// transform slot details for single doctor day.
    public static function transformDoctorSlotsDetails($doctor, $req_Date, $doctor_profile)
    {
        $doctorSlots = [];
        $slotobject = [];
        if (!empty($doctor)) {
            foreach ($doctor->profile->videoConsultancyDays as $key => $consultancyDay) {
                $start_time = $consultancyDay->start_time;
                $end_time = $consultancyDay->end_time;
                $date = $consultancyDay->day;
                $duration = $consultancyDay->duration ? $consultancyDay->duration : 30;
                /* date wise slotting*/
                $NewDate = $req_Date;
                if (!empty($start_time) && !empty($end_time)) {
                    $timeslots = Self::getSlotsByDay($start_time, $end_time, $NewDate, $duration);
                    $appointment = Self::getOnlineBookedSloteByDate($doctor_profile->id, $NewDate);

                    if (!empty($appointment)) {
                        $apps = array();
                        foreach ($appointment as $app) {
                            $apps[] = $app['start_time'];
                        }
                        $intersection = array_intersect($apps, $timeslots);
                        $slots = array_diff($timeslots, $intersection);
                        $timeslots = array_values($slots);
                    }
//                    $countDates = count($timeslots);
                    $slotobject = [
                        "date" => $NewDate,
                        "slots" => $timeslots,
                    ];
                }
                $doctorSlots = $slotobject;
                /* date wise slotting*/
            }
        }
        return $doctorSlots;
    }

// convert slots of a Day.
    static function getSlotsByDay($start_time, $end_time, $date, $duration)
    {
        $time = array();
        $start = new DateTime($start_time);
        $end = new DateTime($end_time);
        $current_date_time = date('Y-m-d H:i:s');
        $match_date = date('Y-m-d', strtotime($date));
        $current_date = date('Y-m-d');
        $current_time = date('H:i', strtotime($current_date_time));
        $start_time = $start->format('H:i');
        $end_time = $end->format('H:i');

        $i = 0;
        while ((strtotime($start_time) <= strtotime($end_time))) {
            $start = $start_time;
            $end = date('H:i', strtotime('+' . $duration . ' minutes', strtotime($start_time)));
            $start_time = date('H:i', strtotime('+' . $duration . ' minutes', strtotime($start_time)));
            $i++;
            if (strtotime($start_time) <= strtotime($end_time)) {
                $times[$i]['start'] = $start;
                if (($current_date == $match_date)) {
                    if ($times[$i]['start'] >= $current_time) {
                        $time[$i]['start'] = $start;
                    }
                } else {
                    $time[$i]['start'] = $start;
                }
            }
        }
        $result = array();
        if (!empty($time)) {
            foreach ($time as $key => $value) {
                if ((date('Y-m-d') == $match_date) && ($current_time > $value['start'])) {
                } else {
                    if (is_array($value)) {
                        $result = array_merge($result, Arr::flatten($value));
                    } else {
                        $result[$key] = $value;
                    }
                }

            }
        }
        if (count($result) > 0) {
            foreach ($result as $res) {
                if (strtotime($res) <= strtotime($current_time)) {
                    $result[] = $res;
                }
            }
        }
        return array_unique($result);
    }

//
    static function getOnlineBookedSloteByDate($doc_id, $date = null)
    {

        $appointment = Appointment::where([['type', 'online_consultation'], ['doctor_profile_id', $doc_id]])->whereDate('booking_date', $date)
            ->whereNotIn('appointment_status', ['canceled'])
            ->get()->toArray();
        return $appointment;
    }

    static function getFaqs($doc_id)
    {
        $faqs = Faq::where('added_by', $doc_id)->get()->toArray();
        return $faqs;
    }

    static function date_sort($a, $b)
    {
        return strtotime($a) - strtotime($b);
    }

    //tranfor get last uploaded medical record
    static function transformUploadedMedicalRecords($medical_record)
    {
        $dr_records = [];
        if (!empty($medical_record)) {
            foreach ($medical_record as $record) {
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
                array_push($dr_records, $medical_reports);
            }
        }
        return $dr_records;
    }

    // transform specialities
    // transform specialities
    public static function transformASpecialities($specialty)
    {
        $speciality_list = [];
        if (!empty($specialty)) {
            foreach ($specialty as $speciality) {
                $medical_reports = [
                    'id' => $speciality->id,
                    'file' => Storage::disk('s3')->exists($speciality->icon) ? Storage::disk('s3')->url($speciality->icon) : url(Storage::url('files/no-image.png')),
                    'name' => $speciality->name,
                ];
                array_push($speciality_list, $medical_reports);
            }
        }
        return $speciality_list;
    }

    public static function transformAppointments($appointments)
    {
        $tranformed_appoints = [];
        $tranformed_appointments = [];
        if (!empty($appointments)) {
            foreach ($appointments as $appointment) {
                $tranformed_appoints['appointment_id'] = $appointment->id;
                $tranformed_appoints['patient_id'] = $appointment->patient_profile_id;
                $tranformed_appoints['patient_name'] = $appointment->patient->user->name;
                $tranformed_appoints['patient_image'] = Storage::disk('s3')->exists($appointment->patient->user->profile_image) ? Storage::disk('s3')->url($appointment->patient->user->profile_image) : url(Storage::url('files/no-image.png'));
                $tranformed_appoints['patient_phone_number'] = $appointment->patient->user->phone;
                $tranformed_appoints['patient_gender'] = $appointment->patient->gender;

                // if reletive data exist

                if ($appointment->patient_type == "other") {
                    $relative = self::getRelativeName($appointment->relation_id);
                    $tranformed_appoints['patient_gender'] = $relative->gender;
                    $tranformed_appoints['patient_name'] = $relative->name;
                    $tranformed_appoints['patient_relation'] = $relative->relation;
                    $tranformed_appoints['patient_phone_number'] = $relative->phone;
                }

                $tranformed_appoints['fee_status'] = $appointment->fee_status;
                $tranformed_appoints['fee'] = !empty($appointment->doctor->videoConsultancy) ? $appointment->doctor->videoConsultancy->fee : 0.0;
                $tranformed_appoints['patient_email'] = $appointment->patient->user->email;
                $tranformed_appoints['appointment_date'] = $appointment->booking_date;
                $tranformed_appoints['appointment_time'] = $appointment->start_time;
                $tranformed_appoints['appointment_status'] = $appointment->appointment_status;
                $tranformed_appoints['doctor_name'] = $appointment->doctor->user->name;
                array_push($tranformed_appointments, $tranformed_appoints);
            }
        }
        return $tranformed_appointments;
    }

    public static function transformPatients($patients)
    {
        $transformed_patient = [];
        if (!empty($patients)) {
            foreach ($patients as $patient) {
                $temp = [];
                $user_data = $patient;
                $profile = $patient->patientProfile;
                $temp['id'] = $patient->id;
                $temp['dob'] = (string)$profile->dob ? $profile->dob : '';
                $temp['blood_group'] = (string)$profile->blood_group ? $profile->blood_group : '';
                $temp['weight'] = (float)$profile->weight ? $profile->weight : 0.0;
                $temp['height'] = $profile->height ? $profile->height : '';
                $temp['gender'] = (string)$profile->gender ? $profile->gender : '';
                $temp['marital_status'] = (string)$profile->marital_status ? $profile->marital_status : '';
                $temp['age'] = (string)$profile->age ? $profile->age : '';
                $temp['address'] = (string)$profile->address ? $profile->address : '';
                $temp['email'] = (string)$user_data->email ? $user_data->email : '';
                $temp['name'] = (string)$user_data->name ? $user_data->name : '';
                $temp['phone'] = (string)$user_data->phone ? $user_data->phone : '';
                $temp['image'] = (string)$user_data->profile_image ? Storage::disk('s3')->url($user_data->profile_image) : url('public/files/no-image.png');
                array_push($transformed_patient, $temp);
            }
        }
        return $transformed_patient;
    }

    public static function transformSymptoms($symptoms)
    {
        $symptoms_list = [];
        if (!empty($symptoms)) {
            foreach ($symptoms as $symptom) {
                $temp = [
                    'id' => $symptom->id,
                    'file' => Storage::disk('s3')->exists($symptom->icon) ? Storage::disk('s3')->url($symptom->icon) : url(Storage::url('files/no-image.png')),
                    'name_en' => $symptom->name_en,
                    'name_ur' => $symptom->name_ur,
                ];
                array_push($symptoms_list, $temp);
            }
        }
        return $symptoms_list;
    }
}
