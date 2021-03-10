<?php

namespace App\Http\Controllers\Api;

use App\Agora\RtcTokenBuilder;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Channel;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\User;
use App\UserDevice;
use App\Models\DoctorAward;
use App\Models\DoctorEducation;
use App\Models\DoctorExperiance;
use App\Models\DoctorHospital;
use App\Models\DoctorHospitalDay;
use App\Models\DoctorHospitalService;
use App\Models\DoctorHospitalVocation;
use App\Models\DoctorProfile;
use App\Models\DoctorService;
use App\Models\DoctorSpeciality;
use App\Models\DoctorVideoConsultancy;
use App\Models\DoctorVideoConsultancyDay;
use App\Traits\Transformer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
class HomeController extends Controller
{
    // get all doctors
    public function doctors()
    {
        try {
            DB::beginTransaction();
            $limit = request('limit') ? request('limit') : 5;
            $users = User::whereUserType('doctor')->whereHas('profile', function ($query) {
                $query->whereIsVideoEnable('true');
                $query->whereIsInstant('false');
                if (request('city') || request('gender')) {
                    $city = request('city') ? request('city') : '';
                    $gender = request('gender') ? request('gender') : '';
                    if (!empty($city)) {
                        $query->whereCity($city);
                    }
                    if (!empty($gender)) {
                        $query->whereGender($gender);
                    }
                }
                $query->whereHas('videoConsultancyDays', function ($query) {
                        $currentDate = date('D');
                    $lastDate = date("D", strtotime("+1 week"));
//                    $query->whereBetween('day', [$currentDate, $lastDate]);
                    // availability filter.
                    if (request('availability')) {
                        $availability = request('availability') ? request('availability') : '';
                        if (!empty($availability)) {
                            $query->whereDay($availability);
                        }
                    }
                });
                $query->whereHas('videoConsultancy', function ($sub_query) {
                    // fee range
                    if (request('fee_range_start') || request('fee_range_end')) {
                        $fee_range_start = request('fee_range_start') ? request('fee_range_start') : '';
                        $fee_range_end = request('fee_range_end') ? request('fee_range_end') : '';
                        if (!empty($fee_range_start) && !empty($fee_range_start)) {
                            $sub_query->whereBetween('fee', [$fee_range_start, $fee_range_end]);
                        }
                    }
                });
                $query->whereHas('specialities', function ($query) {
                    $query->whereHas('speciality', function ($sub_query) {
                        if (request('speciality')) {
                            $speciality = request('speciality') ? request('speciality') : '';
                            if (!empty($speciality)) {
                                $sub_query->whereName($speciality);
                            }
                        }
                    });
                });
            })->with(['profile' => function ($sub_query) {
                $sub_query->select('id','user_id','gender', 'city', 'country');
                $sub_query->with(['ratings', 'education' => function($sub_query1) {
                    $sub_query1->select('doctor_profile_id','degree');
                }, 'experiences' => function($sub_query2) {
                    $sub_query2->select('id', 'doctor_profile_id', 'start_date', 'end_date');
                }, 'specialities' => function ($query) {
                    $query->select('id', 'doctor_profile_id', 'speciality_id');
                    $query->whereHas('speciality')->with(['speciality' => function($sub_query2) {
                        $sub_query2->select('id', 'name');
                    }]);
                }, 'videoConsultancy' => function($sub_query_3) {
                    $sub_query_3->select('id', 'doctor_profile_id', 'fee');
                }, 'videoConsultancyDays' => function($sub_query_3) {
                    $sub_query_3->select('id', 'doctor_profile_id', 'doctor_video_consultancy_id', 'day');
                }]);
            }])->orderBy('id', 'asc')->paginate($limit);
            $transformedDoctors = Transformer::transformDoctors($users);
            $meta = Transformer::transformCollection($users);
            return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedDoctors, $meta);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    // Get Doctor profile.
    public function getDoctor($id)
    {
        try {
            DB::beginTransaction();
            $user = User::whereId($id)->whereUserType('doctor')->first();
            $doctor = User::whereId($id)->whereUserType('doctor')->whereHas('profile', function ($query) {
                $query->whereIsVideoEnable('true');
            })->with(['profile' => function ($sub_query) {
                $sub_query->select('id','user_id');
                $sub_query->with(['education', 'awards', 'experiences', 'specialities' => function ($sub_query) {
                    $sub_query->with(['speciality' => function($sub_query2) {
                        $sub_query2->select('id', 'name');
                    }]);
                }, 'services' => function ($sub_query) {
                    $sub_query->with(['service']);
                }, 'videoConsultancy' => function($sub_query_3) {
                    $sub_query_3->select('id', 'doctor_profile_id', 'fee');
                }, 'videoConsultancyDays' => function($sub_query_3) {
                    $sub_query_3->select('id', 'doctor_profile_id', 'doctor_video_consultancy_id', 'day');
                }, 'awards', 'memberships', 'ratings', 'languag', 'appointments']);
            }])->first();
            if ($doctor) {
                $transformedDoctors = Transformer::transformDoctorProfile($doctor,$user);
                return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedDoctors);
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    // make a call.
    public function SendCallLink($id)
    {
        try {
            DB::beginTransaction();
            $channle = Channel::whereName($id)->first();
            if ($channle) {
                $appointment = Appointment::whereId($channle->appointment_id)->first();
                if ($appointment){

                    /*******************************calling time logic here start*******************************************/

                    $currenttime = date("H:i");
                    $today = date("Y-m-d");
                    $time = strtotime($appointment->start_time);
                    $endTime = date("H:i", strtotime('+30 minutes', $time));
                    $minusTime = date("H:i", strtotime('+31 minutes', $time));
                    //dd($endTime);
                    if(($appointment->booking_date < $today) ||(($appointment->booking_date == $today) && ($currenttime > $endTime))){
                        //dd('session expire');
                        $can_call = false;
                        $is_expired = true;
                    }
                    elseif(($appointment->booking_date == $today) && (($currenttime >= $appointment->start_time) && ($currenttime <= $minusTime))){
                        //dd('session start');
                        $can_call = true;
                        $is_expired = false;
                    }
                    elseif(($appointment->booking_date > $today) ||(($appointment->booking_date == $today) && ($currenttime < $appointment->start_time))){
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
                    $user = "0";
                    $role = RtcTokenBuilder::RolePublisher;
                    $expireTimeInSeconds = 86400;
                    $currentTimestamp = (new  \DateTime("now", new  \DateTimeZone('UTC')))->getTimestamp();
                    $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;
                    $token = RtcTokenBuilder::buildTokenWithUid($appID, $app_secret, $channelName, $uid, $role, $privilegeExpiredTs);
                    $pat_data = $appointment->with(['patient' => function ($query) {
                        $query->with(['user']);
                    }])->first();
                    /*Save Token for Every user for video calling*/
                    $time = $appointment->start_time;
                    $date = $appointment->booking_date;

                    $booking_date = date('d F, Y ', strtotime($date));
                    $booking_time = date('h:i A', strtotime($time));
                    $data = [
                        'channel_name' => $channle->name,
                        'agora_token' => $channle->patient_token,
                        'can_call' => $can_call,
                        'booking_date' => $booking_date,
                        'booking_time' => $booking_time,
                        'is_expired' => $is_expired,
                        'status' => $channle->status,
                        'uid' => $pat_data->patient->user->id,
                    ];
                    return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $data);
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment not found');
                }

            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'channle  not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    // make a call.
    public function callSync(Request $request,$id)
    {
        $rules = [
            'status' => 'required',
            'device_type' => 'required',
        ];
        $request->validate($rules, []);
        try {
            DB::beginTransaction();
            $channle = Channel::whereName($id)->first();
            if ($channle) {

                $channle->update([
                    'status' => $request->status,
                ]);

                $channle = Channel::whereName($id)->first();
                    $data = [
                        'channel_name' => $channle->name,
                        'agora_token' => $channle->patient_token,
                        'status' => $channle->status,
                    ];
                    return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $data);
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Channel not found');
                }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    // Get Doctor Video Slots
    public function doctorVideoSlots($id)
    {
        try {
            DB::beginTransaction();
            $doctor = User::whereUserType('doctor')->whereId($id)->first();
            $doctor_profile = DoctorProfile::whereUserId($id)->first();
            if ($doctor) {
                $doctor_data = User::whereUserType('doctor')->whereId($id)->whereHas('profile', function ($query) {
                })->with(['profile' => function ($query) {
                    $query->with(['videoConsultancyDays' => function($query) {
                        $query->select('id', 'doctor_profile_id', 'day', 'duration', 'start_time', 'end_time');
                    }]);
                }])->first();
                $transformedDoctorSlots = Transformer::transformDoctorSlots($doctor_data,$doctor_profile);
                return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedDoctorSlots);
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }

    }

    // Get Doctor Video Slots Details according to given date
    public function doctorSlotsDetails($id)
    {
        try {
            DB::beginTransaction();
            $doctor = User::whereUserType('doctor')->whereId($id)->first();
            $req_Date = request('date');
            $doctor_profile = DoctorProfile::whereUserId($id)->first();

            if ($doctor) {
                $doctor_data = User::whereUserType('doctor')->whereId($id)->whereHas('profile', function ($query) {
                    //$query->whereUserId();
                    $query->select('id','user_id');
                    $query->whereHas('videoConsultancyDays', function ($sub_query) {
                        if (request('date')) {
                            if (!empty(request('date'))) {
                                $currentDate = request('date');
                                $sub_query->where('day', date('D', strtotime($currentDate)));
                            }
                        }
                    });
                })->with(['profile' => function ($query) {
                    $query->with(['videoConsultancyDays' => function ($query) {
                        $query->select('id', 'doctor_profile_id', 'day', 'duration', 'start_time', 'end_time');
                        if (request('date')) {
                            if (!empty(request('date'))) {
                                $currentDate = request('date');
                                $query->where('day', date('D', strtotime($currentDate)));
                            }
                        }
                    }]);
                }])->first();
                $transformedDoctorSlots = Transformer::transformDoctorSlotsDetails($doctor_data, $req_Date,$doctor_profile);
                return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedDoctorSlots);
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }

    }

    // Get Doctor Video Slots Details according to given date
    public function doctorSlotBooking($id)
    {
        try {
            DB::beginTransaction();
            $doctor = User::whereUserType('doctor')->whereId($id)->first();
            if ($doctor) {
                $doctor_data = $doctor->whereHas('profile', function ($query) {
                    $query->whereHas('videoConsultancyDays', function ($sub_query) {
                        if (request('date')) {
                            if (!empty(request('date'))) {
                                $currentDate = date('Y-m-d');
                                $sub_query->whereDate('day', $currentDate);
                            }
                        }

                    });
                })->with(['profile' => function ($query) {
                    $query->with(['videoConsultancy', 'videoConsultancyDays', 'appointments']);
                }])->first();
                $transformedDoctorSlots = Transformer::transformDoctorSlots($doctor_data, true);
                return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedDoctorSlots);
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }

    }

    public function test(Request $request) {
//        dd(Storage::disk('s3')->exists('images/Pmcu0YDRqE840tmKI932608YkxxwRzUABLKx4TXy.jpeg'));
        $path = $request->file('file')->store('perhjh', 's3');
//        dd(Storage::disk('s3')->response($path));
        dd(Storage::disk('s3')->url($path));

    }

}
