<?php

namespace App\Http\Controllers\Api\Doctor;

use App\Agora\RtcTokenBuilder;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Channel;
use App\Models\DoctorAward;
use App\Models\DoctorEducation;
use App\Models\DoctorExperiance;
use App\Models\DoctorProfile;
use App\Models\DoctorService;
use App\Models\DoctorSpeciality;
use App\Models\DoctorVideoConsultancy;
use App\Models\DoctorVideoConsultancyDay;
use App\Models\InstantAppointment;
use App\Models\Language;
use App\Models\MedicalRecord;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\Speciality;
use App\User;
use App\Traits\Transformer;
use App\Traits\DoctorTransformer;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Kreait\Firebase\Messaging\CloudMessage;

class DoctorController extends Controller
{
    // get doctor appointments.
    public function doctorAppointments($id, Request $request)
    {
        $rules = [
            'device_type' => 'required',
        ];
        $request->validate($rules, []);
        try {
            DB::beginTransaction();
            $limit = !empty($request->limit) ? $request->limit : 10000;
            $doctor = User::whereUserType('doctor')->whereId($id)->first();
            $device_type = $request->device_type;
            if ($doctor) {
                $doctor_data = $doctor->whereHas('profile', function ($query) use ($id) {
                    $query->whereUserId($id);
                })->with(['profile' => function ($sub_query) {
                    $sub_query->whereHas('videoConsultancy')->with(['videoConsultancy']);
                }]);
                $doctor_data = $doctor_data->first();
                if (!empty($doctor_data)) {
                    $appointments = Appointment::whereDoctorProfileId($doctor_data->profile->id);
                    if (!empty($appointments)) {
                        if (request('filter')) {
                            $filter = request('filter') ? request('filter') : '';
                            if (!empty($filter)) {
                                $appointments->whereAppointmentStatus($filter);
                            }
                        };
                        $appointments = $appointments->whereHas('patient')->where('type', '!=', 'instant')->with(['patient' => function ($query) {
                            $query->with(['user']);
                        }, 'channel', 'prescription' => function ($query) {
                            $query->with(['medicalRecords']);
                        }])->orderBy('booking_date', 'asc')->orderby(DB::raw('case when appointment_status= "pending" then 1 when appointment_status= "completed" then 2 when appointment_status= "canceled" then 3 end'))->paginate($limit);

                        $transformedPatient = DoctorTransformer::transformDoctorAppointments($doctor_data, $appointments, $device_type);

                        $meta = Transformer::transformCollection($appointments);
                        return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedPatient, $meta);
                    } else {
                        return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment record not found');
                    }
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor data not found');
                }
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }
    // get doctor instant appointments
    public function doctorInstantAppointments($id, Request $request)
    {
        $rules = [
            'device_type' => 'required',
        ];
        $request->validate($rules, []);
        try {
            DB::beginTransaction();
            $limit = !empty($request->limit) ? $request->limit : 10000;
            $doctor = User::whereUserType('doctor')->whereId($id)->first();
            $device_type = $request->device_type;
            if ($doctor) {
                $doctor_data = $doctor->whereHas('profile', function ($query) use ($id) {
                    $query->whereUserId($id);
                })->with(['profile' => function ($sub_query) {
                    $sub_query->where('is_instant', 'true');
                    $sub_query->whereHas('videoConsultancy')->with(['videoConsultancy']);
                }]);
                $doctor_data = $doctor_data->first();
                if (!empty($doctor_data)) {
                    $appointments = Appointment::whereDoctorProfileId($doctor_data->profile->id);
                    if (!empty($appointments)) {
                        if (request('filter')) {
                            $filter = request('filter') ? request('filter') : '';
                            if (!empty($filter)) {
                                $appointments->whereAppointmentStatus($filter);
                            }
                        };
                        $appointments = $appointments->whereHas('patient')->where('type', 'instant')->with(['patient' => function ($query) {
                            $query->with(['user']);
                        }, 'channel', 'prescription' => function ($query) {
                            $query->with(['medicalRecords']);
                        }, 'symptoms'])->orderBy('booking_date', 'asc')->orderby(DB::raw('case when appointment_status= "pending" then 1 when appointment_status= "completed" then 2 when appointment_status= "canceled" then 3 end'))->paginate($limit);

                        $transformedPatient = DoctorTransformer::transformDoctorAppointments($doctor_data, $appointments, true);

                        $meta = Transformer::transformCollection($appointments);
                        return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedPatient, $meta);
                    } else {
                        return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment record not found');
                    }
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor data not found');
                }
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    // get patients against doctor appointments.
    public function patientsAppointments($id, Request $request)
    {
        $rules = [
            'device_type' => 'required',
        ];
        $request->validate($rules, []);
        try {
            DB::beginTransaction();
            $limit = !empty($request->limit) ? $request->limit : 5;
            $doctor = User::whereUserType('doctor')->whereId($id)->first();
            $device_type = $request->device_type;
            if ($doctor) {
                $doctor_data = $doctor->whereHas('profile', function ($query) use ($id) {
                    $query->whereUserId($id);
                })->with(['profile'])->first();
                if (!empty($doctor_data)) {
                    $appointments = Appointment::whereDoctorProfileId($doctor_data->profile->id)->groupBy('patient_profile_id');
                    if (!empty($appointments)) {
                        if (request('filter')) {
                            $filter = request('filter') ? request('filter') : '';
                            if (!empty($filter)) {
                                $appointments->whereAppointmentStatus($filter);
                            }
                        };
                        $appointments = $appointments->whereHas('patient')->with(['patient' => function ($query) {
                            $query->with(['user']);
                        }])->orderBy('booking_date', 'asc')
                            ->orderby(DB::raw('case when appointment_status= "pending" then 1 when appointment_status= "completed" then 2 when appointment_status= "canceled" then 3 end'))
                            ->paginate($limit);
                        $transformedPatient = DoctorTransformer::transformPatients($doctor_data, $appointments, $device_type);

                        $meta = Transformer::transformCollection($appointments);
                        return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedPatient, $meta);
                    } else {
                        return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment record not found');
                    }
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor data not found');
                }
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    //update  appointment staus
    public function updateAppointmentStatus(Request $request, $id, $appointment_id)
    {
        try {
            DB::beginTransaction();
            $user = Auth::user();
            $doctor = User::whereUserType('doctor')->whereId($user->id)->first();
            if (!empty($doctor)) {
                $appointment = Appointment::whereId($appointment_id)->first();
                if (!empty($appointment)) {
                    $appointment->update(['appointment_status' => $request->status]);
                    DB::commit();
                    return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Appointment has been updated successfully');
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment not found');
                }

            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'doctor not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    // doctor make a call
    public function doctorCall($id, $appointment_id)
    {
        try {
            DB::beginTransaction();
            $user = Auth::user();
            $doctor = User::whereUserType('doctor')->whereId($id)->first();
            if (!empty($doctor)) {
                $appointment = Appointment::find($appointment_id);
                if (!empty($appointment)) {
                    /*******************************calling time logic here start*******************************************/
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
                    $random = Str::random(30);
                    $uid = $id;
                    $appID = "3d348f46b9a14ee6a9ad97c41283f95c";
                    $appCertificate = "d4581571e4f54510a91f5b9ccc13ae69";
                    $channelName = $random;
                    $user = "0";
                    $role = RtcTokenBuilder::RolePublisher;
                    $expireTimeInSeconds = 86400;
                    $currentTimestamp = (new  \DateTime("now", new  \DateTimeZone('UTC')))->getTimestamp();
                    $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;
                    $token = RtcTokenBuilder::buildTokenWithUid($appID, $appCertificate, $channelName, $uid, $role, $privilegeExpiredTs);
                    /*Save Token for Every user for video calling*/
                    $patient_data = Appointment::whereId($appointment_id)->with(['patient' => function ($query) {
                        $query->with(['user']);
                    }, 'doctor' => function ($query) {
                        $query->with(['user']);
                    }])->first();
                    $channel_data = Channel::whereAppointmentId($appointment->id)->first();
                    if ($channel_data) {
                        $channel_data->update([
                            'doctor_token' => $token,
                            //'name' => $channelName,
                            'is_doctor_called' => true,
                            'appointment_id' => $appointment->id,
                        ]);
                        $channel = Channel::whereAppointmentId($appointment->id)->first();
                    } else {
                        $channel = Channel::Create([
                            'doctor_token' => $token,
                            'is_doctor_called' => true,
                            'name' => $channelName,
                            'appointment_id' => $appointment->id,
                        ]);
                    }

                    $data = [
                        'channel_name' => $channel->name,
                        'agora_token' => $channel->doctor_token,
                        'can_call' => $can_call,
                        'is_expired' => $is_expired,
                        'status' => $channel->status,
                        'is_doctor_called' => $channel->is_doctor_called,
                        'is_patient_called' => $channel->is_patient_called,
                        'patient_user_id' => $patient_data->patient->user->id,
                        'time' => date('Y/m/d H:i:s'),

                    ];

                    $messaging = app('firebase.messaging');
                    $user = $patient_data->patient->user;
                    $devices = $user->userToken()->pluck('token')
                        ->reject(function ($it) {
                            return is_numeric($it);
                        })
                        ->toArray();
                    $notification_data = $data;
                    $notification_data['agora_token'] = $channel->patient_token;
                    $notification_data['current_time'] = date('Y/m/d H:i:s');
                    $notification_data['test'] = 1;
                    $notification_data['priority'] = 'high';
                    $notification_data['is_rejected'] = false;
                    $notification_data['doctor_name'] = $patient_data->doctor->user->name;
                    $notification_data['doctor_user_id'] = $patient_data->doctor->user->id;
                    $notification_data['appointment_id'] = $appointment->id;
                    $notification_data['doctor_image'] = (string)$patient_data->doctor->user->profile_image ? Storage::disk('s3')->url($patient_data->doctor->user->profile_image) : url('public/files/no-image.png');
                    if (!empty($devices)) {
                        $message = CloudMessage::withTarget('token', $devices[0])
                            ->withData($notification_data) // optional
                        ;
                        $messaging->send($message);
                    }

                    DB::commit();
                    return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $data);
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment not found');
                }

            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    //upload prescription
    public function uploadPrescription(Request $request, $id, $appointment_id)
    {
        //dd($request);
        if ($request->device_type != 'web') {
            $rules = [
                'prescriptions' => 'required',
                'device_type' => 'required',
            ];
            $request->validate($rules, []);
        } else {
            $rules = [
                'device_type' => 'required',
            ];
            $request->validate($rules, []);
        }

        try {
            /*DB::beginTransaction();*/
            $user = Auth::user();
            $doctor = User::whereUserType('doctor')->whereId($user->id)->first();
            if (!empty($doctor)) {
                $appointment = Appointment::whereId($appointment_id)->first();
                if (!empty($appointment)) {
                    $precription = Prescription::create([
                        'doctor_profile_id' => $appointment->doctor_profile_id,
                        'patient_profile_id' => $appointment->patient_profile_id,
                        'appointments_id' => $appointment->id,
                        'uploaded_by' => 'doctor',
                    ]);
                    if ($request->device_type == 'web') {
                        $req = json_decode(file_get_contents("php://input"));
                        foreach ($req->prescriptions as $key => $value) {

                            $image_parts = explode(";base64,", $value);
                            $file_parts = explode("/", $image_parts[0]);
                            //dd($file_parts);

                            if ($file_parts[1] == 'pdf') {
                                $image_type_aux = explode("image/", $image_parts[0]);
                                $file_type = "pdf";
                                $image_type = $image_type_aux[0];
                            } else {
                                $image_type_aux = explode("image/", $image_parts[1]);
                                $file_type = "image";
                                //$image_type = $image_type_aux[1];
                            }


                            $image_base64 = base64_decode($image_parts[1]);
                            $path = 'prescription/' . uniqid() . '.' . $file_type;
                            MedicalRecord::create([
                                'image_url' => $path,
                                'prescriptions_id' => $precription->id,
                                'Type' => 'prescription',
                                'file_type' => $file_type,
                            ]);
                            Storage::disk('s3')->put($path, $image_base64);
                        }
                    } else {
                        if ($request->hasFile('prescriptions')) {
                            //$files = $request->getContent()['prescriptions'];
                            $files = $request->prescriptions;
                            foreach ($files as $file) {

                                $path = Storage::disk('s3')->put('prescription', $file);
                                MedicalRecord::create([
                                    'image_url' => $path,
                                    'prescriptions_id' => $precription->id,
                                    'Type' => 'prescription'
                                ]);
                            }
                        }
                    }

                    $medical_record = MedicalRecord::wherePrescriptionsId($precription->id)->get();

                    $transformedDoctorSlots = Transformer::transformUploadedMedicalRecords($medical_record);

                    return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedDoctorSlots);
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment not found');
                }

            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    //delete prescription
    public function deletePrescription(Request $request)
    {
        $rules = [
            'id' => 'required',
            'device_type' => 'required',
        ];
        $request->validate($rules, []);

        try {
            $medical_record = MedicalRecord::whereId($request->id)->first();
            if (!empty($medical_record)) {
                $prescription_id = $medical_record->prescriptions_id;
                $records = MedicalRecord::where('prescriptions_id', $prescription_id);
                if($records->count() === 1) {
                    Prescription::where('id', $prescription_id)->delete();
                }
                $path = $medical_record->image_url;
                Storage::disk('s3')->delete($path);
                MedicalRecord::whereId($request->id)->delete();
                return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Record deleted successfully');
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Record not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    // get dashboard counter record
    public function dashboardCounts()
    {
        $today = date("Y-m-d");
        $total_consultation = 0;
        $user = User::findOrFail(Auth::id());
        $doctor = DoctorProfile::whereUserId($user->id)->first();

        $patient = Appointment::whereDoctorProfileId($doctor->id)->whereHas('patient')->with(['patient' => function ($query) {
            $query->with(['user']);
        }])->count();

        $total_consultation = Appointment::whereType('online_consultation')->whereDoctorProfileId($doctor->id)->whereAppointmentStatus('completed')->count();

        $pending_consultation = Appointment::whereType('online_consultation')->whereDoctorProfileId($doctor->id)->whereAppointmentStatus('pending')->count();

        $today_appointments = Appointment::whereType('online_consultation')->whereDoctorProfileId($doctor->id)->whereDate('created_at', $today)->count();

        $data = [
            'Total_patient' => $patient,
            'Total_consultation_done' => $total_consultation,
            'Consultation_Pending' => $pending_consultation,
            'today_aapointments' => $today_appointments,
        ];
        if ($data) {
            return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $data);
        } else {
            return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Record not found');
        }
    }

    /**
     *  patient profile.
     * @param Request $request
     * @return mixed
     */
    public function patientProfile($id)
    {
        try {
            DB::beginTransaction();
            $patient = User::whereUserType('patient')->whereId($id)->first();
            if ($patient) {
                $patient_data = $patient->whereHas('patientProfile', function ($query) use ($id) {
                    $query->whereUserId($id);
                })->with(['patientProfile'])->first();
                if ($patient_data) {
                    $transformedPatient = DoctorTransformer::transformPatientProfile($patient_data);
                    return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedPatient);
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Patient profile not found');
                }
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Patient not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  doctor profile.
     * @param
     * @return mixed
     */
    public function profile()
    {
        try {
            DB::beginTransaction();
            $user = auth()->guard('api')->user();
            $doctor = User::whereUserType('doctor')->whereId($user->id)->whereHas('profile')->with(['profile' => function ($sub_query) {
                $sub_query->with(['education', 'awards', 'languag', 'experiences', 'specialities' => function ($sub_query) {
                    $sub_query->with(['speciality']);
                }, 'services' => function ($sub_query) {
                    $sub_query->with(['service']);
                }, 'videoConsultancy', 'videoConsultancyDays', 'awards', 'memberships', 'ratings', 'languag', 'appointments']);
            }])->first();
            if ($doctor) {
                $transformedPatient = DoctorTransformer::transformDoctorProfile($doctor);
                return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedPatient);
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Patient not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  update profile.
     * @param Request $request
     * @return mixed
     */
    public function updateProfile(Request $request)
    {
        $rules = [];
        if ($request->personal_information) {
            $rules['name'] = 'required';
            $rules['email'] = 'required';
            $rules['phone'] = 'required';
//            $rules['pmdc'] = 'required|unique:doctor_profiles,pmdc';
            $rules['name'] = 'required';
            if ($request->is_instant) {
                if ($request->is_instant === false) {
                    $rules['speciality'] = 'required';
                } else {
                    $rules['speciality'] = 'nullable';
                }
            }
        }
        if ($request->password) {
            $rules['password'] = 'required';
            $rules['old_password'] = 'required';
        }

        $request->validate($rules, []);

        try {
            DB::beginTransaction();
            $id = auth()->guard('api')->user()->id;
            $user = User::whereId($id)->whereUserType('doctor')->first();
            if ($user) {
                $doctor = User::whereId($id)->whereUserType('doctor')->whereHas('profile')->with(['profile'])->first();
                if ($doctor) {
                    if ($request->password) {
                        if (!empty($request->password)) {
                            $hashedPassword = auth()->guard('api')->user()->password;
                            if (\Hash::check($request->old_password, $hashedPassword)) {
                                if (!\Hash::check($request->password, $hashedPassword)) {
                                    $data_users['password'] = bcrypt($request->password);
                                    User::whereId($id)->update(['password' => bcrypt($request->password)]);
                                } else {
                                    return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', 'New password can not be the old password!');
                                }
                            } else {
                                return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', 'Old password doesnt matched');
                            }
                        }
                    }
                    //update in users table
                    if ($request->personal_information) {
                        //upload profile pic
                        $path = $user->profile_image;
                        $req = json_decode(file_get_contents("php://input"));
                        if ($req->profile_image) {
                            if (Storage::disk('s3')->exists($user->profile_image)) {
                                Storage::disk('s3')->delete($user->profile_image);
                            }

                            $image_parts = explode(";base64,", $req->profile_image);

                            $image_type_aux = explode("image/", $image_parts[0]);

                            $image_type = $image_type_aux[1];

                            $image_base64 = base64_decode($image_parts[1]);
                            $path = 'profiles/' . uniqid() . '.' . $image_type;
                            //Storage::disk('public')->put($path, $image_base64);
                            Storage::disk('s3')->put($path, $image_base64);
                        }
                        $data_users = [
                            'name' => $request->name,
                            'email' => $request->email,
                            'phone' => $request->phone,
                            'profile_image' => $path,
                        ];
                        User::whereId($user->id)->update($data_users);
                        //update in doctor_profiles
                        $dob = $doctor->profile->dob;
                        if (!empty($request->date_of_birth)) {
                            $dob = $request->date_of_birth['year'] . "-" . $request->date_of_birth['month'] . "-" . $request->date_of_birth['day'];
                        }

                        //dd($converted_is_video_enable);
                        $data_profiles = [
                            'pmdc' => $request->pmdc,
                            'dob' => $dob,
                            'gender' => $request->gender,
                            'summary' => $request->summary,
                            'address' => $request->address,
                            'city' => $request->city,
                            'country' => $request->country,
                            'is_instant' => $request->is_instant === true ? 'true' : 'false',
                        ];
                        DoctorProfile::whereUserId($doctor->id)->update($data_profiles);
                        if ($request->language) {
                            if (!empty($request->language)) {
                                $data_language = [];
                                $data_languages = [];
                                $dataaward = [];
                                $check_language = Language::whereDoctorProfileId($doctor->profile->id)->first();
                                if ($check_language) {
                                    Language::whereDoctorProfileId($doctor->profile->id)->delete();
                                }
                                foreach ($request->language as $language) {
                                    $data_languages['doctor_profile_id'] = $doctor->profile->id;
                                    $data_languages['language'] = $language;
                                    $language_data = Language::Create($data_languages);
                                }
                            }
                        }
                    }


                    // update speciality against a given doctor
                    if ($request->speciality) {
                        if (!empty($request->speciality)) {
                            $check_data = DoctorSpeciality::whereDoctorProfileId($doctor->profile->id)->first();

                            if ($check_data) {
                                DoctorSpeciality::whereDoctorProfileId($doctor->profile->id)->delete();
                            }

                            foreach ($request->speciality as $speciality) {
                                $specialities = $speciality;
                                if (isset($speciality['id'])) {
                                    $specialities = $speciality['id'];
                                }
                                $doc_speciality['doctor_profile_id'] = $doctor->profile->id;
                                $doc_speciality['speciality_id'] = $specialities;
                                $doctor_speciality = DoctorSpeciality::Create($doc_speciality);

                            }
                        }
                    }

                    // update education against a given doctor
                    if ($request->education) {
                        if (!empty($request->education)) {

                            $edu_start_date = "";
                            $edu_end_date = "";
                            $education_array = $request->education;
                            $exist_education = DoctorEducation::whereDoctorProfileId($doctor->profile->id)->pluck('id');
                            $i = 0;
                            foreach ($request->education as $education) {
                                if (!empty($education['edu_start_date'])) {
                                    $edu_start_date = $education['edu_start_date']['year'] . "-" . $education['edu_start_date']['month'] . "-" . $education['edu_start_date']['day'];
                                }
                                if (!empty($education['edu_end_date'])) {
                                    $edu_end_date = $education['edu_end_date']['year'] . "-" . $education['edu_end_date']['month'] . "-" . $education['edu_end_date']['day'];
                                }

                                if (!empty($education['id'])) {
                                    $check_edu = DoctorEducation::whereId($education['id'])->first();
                                    if ($check_edu) {
                                        $data_education = [
                                            'degree' => $education['degree'] ? $education['degree'] : $check_edu->degree,
                                            'institute_name' => $education['institute'] ? $education['institute'] : $check_edu->institute_name,
                                            'start_date' => $edu_start_date ? $edu_start_date : $check_edu->start_date,
                                            'end_date' => $edu_end_date ? $edu_end_date : $check_edu->end_date,
                                            'country' => $education['edu_country'] ? $education['edu_country'] : $check_edu->country,
                                        ];
                                        DoctorEducation::whereId($education['id'])->update($data_education);
                                        if ($education['id'] == $exist_education[$i]) {
                                            unset($exist_education[$i]);
                                            $i++;
                                        }
                                    }
                                } else {
                                    $doc_education = [];
                                    $doc_education['doctor_profile_id'] = $doctor->profile->id;
                                    $doc_education['degree'] = $education['degree'] ? $education['degree'] : "";
                                    $doc_education['institute_name'] = $education['institute'] ? $education['institute'] : "";
                                    $doc_education['start_date'] = $edu_start_date;
                                    $doc_education['end_date'] = $edu_end_date;
                                    $doc_education['country'] = $education['edu_country'] ? $education['edu_country'] : "";
                                    DoctorEducation::Create($doc_education);
                                }
                            }

                            if (sizeof($exist_education) > 0) {
                                DoctorEducation::whereId($exist_education[$i])->delete();
                            }
                        }
                    }
                    // update education against a given doctor
                    if ($request->services) {
                        if (!empty($request->services)) {
                            $data_service = [];
                            $services_detail = [];
                            $check_services = DoctorService::whereDoctorProfileId($doctor->profile->id)->first();
                            if ($check_services) {
                                DoctorService::whereDoctorProfileId($doctor->profile->id)->delete();
                            }
                            foreach ($request->services as $services) {
                                if ((isset($services['id']) && $services['id'] != null)) {
                                    $check_data = Service::findOrFail($services['id']);
                                    if ($check_data) {
                                        $date_serve['doctor_profile_id'] = $doctor->profile->id;
                                        $date_serve['service_id'] = $services['id'];
                                        DoctorService::Create($date_serve);
                                    }
                                } else {
                                    $data_service['name'] = $services['name'];
                                    $data_services = Service::Create($data_service);
                                    $services_detail['doctor_profile_id'] = $doctor->profile->id;
                                    $services_detail['service_id'] = $data_services->id;
                                    DoctorService::Create($services_detail);
                                }
                            }
                        }
                    }
                    // update awards against a given doctor
                    if ($request->award) {
                        if (!empty($request->award)) {
                            $dataaward = [];
                            $data_award = [];
                            $exist_award = DoctorAward::whereDoctorProfileId($doctor->profile->id)->pluck('id');
                            $i = 0;
                            foreach ($request->award as $awards) {
                                if (!empty($awards['id'])) {
                                    $check_award = DoctorAward::whereId($awards['id'])->first();
                                    if ($check_award) {
                                        $data_award = [
                                            'achievement' => $awards['award_achivements'] ? $awards['award_achivements'] : $check_award->achievement,
                                            'event_name' => $awards['award_event_name'] ? $awards['award_event_name'] : $check_award->event_name,
                                            'designation' => $awards['award_desigination'] ? $awards['award_desigination'] : $check_award->designation,
                                            'award' => $awards['award_recive_award'] ? $awards['award_recive_award'] : $check_award->award,
                                            'received_from' => $awards['award_recive_from'] ? $awards['award_recive_from'] : $check_award->received_from,
                                            'dated' => $awards['award_recived_dated'] ? $awards['award_recived_dated'] : $check_award->dated,
                                            'country' => $awards['award_country'] ? $awards['award_country'] : $check_award->country,
                                        ];
                                        DoctorAward::whereId($awards['id'])->update($data_award);
                                        if ($awards['id'] == $exist_award[$i]) {
                                            unset($exist_award[$i]);
                                            $i++;
                                        }
                                    }
                                } else {
                                    $dataaward['doctor_profile_id'] = $doctor->profile->id;
                                    $dataaward['achievement'] = $awards['award_achivements'];
                                    $dataaward['event_name'] = $awards['award_event_name'];
                                    $dataaward['designation'] = $awards['award_desigination'];
                                    $dataaward['award'] = $awards['award_recive_award'];
                                    $dataaward['received_from'] = $awards['award_recive_from'];
                                    $dataaward['dated'] = $awards['award_recived_dated'];
                                    $dataaward['country'] = $awards['award_country'];
                                    DoctorAward::Create($dataaward);
                                }
                            }
                            if (sizeof($exist_award) > 0) {
                                DoctorAward::whereId($exist_award[$i])->delete();
                            }
                        }
                    }
                    // update experience_detail against a given doctor
                    if ($request->experience) {
                        if (!empty($request->experience)) {
                            $data_experience = [];
                            $data_experiences = [];
                            $exist_experience = DoctorExperiance::whereDoctorProfileId($doctor->profile->id)->pluck('id');
                            $i = 0;
                            foreach ($request->experience as $experience) {
                                $exp_start_date = "";
                                $exp_end_date = "";
                                if (!empty($experience['exp_start_date'])) {
                                    $exp_start_date = $experience['exp_start_date']['year'] . "-" . $experience['exp_start_date']['month'] . "-" . $experience['exp_start_date']['day'];
                                }
                                if (!empty($experience['exp_end_date'])) {
                                    $exp_end_date = $experience['exp_end_date']['year'] . "-" . $experience['exp_end_date']['month'] . "-" . $experience['exp_end_date']['day'];
                                }
                                if (!empty($experience['id'])) {
                                    $check_exp = DoctorExperiance::whereId($experience['id'])->first();
                                    if ($check_exp) {
                                        $data_experiences = [
                                            'hospital_name' => $experience['exp_hosp_name'] ? $experience['exp_hosp_name'] : $check_exp->hospital_name,
                                            'designation' => $experience['exp_desigination'] ? $experience['exp_desigination'] : $check_exp->designation,
                                            'start_date' => $exp_start_date ? $exp_start_date : $check_exp->start_date,
                                            'end_date' => $exp_end_date ? $exp_end_date : $check_exp->end_date,
                                            'country' => $experience['exp_country'] ? $experience['exp_country'] : $check_exp->country,
                                        ];
                                        DoctorExperiance::whereId($experience['id'])->update($data_experiences);
                                        if ($experience['id'] == $exist_experience[$i]) {
                                            unset($exist_experience[$i]);
                                            $i++;
                                        }
                                    }
                                } else {
                                    $data_experience['doctor_profile_id'] = $doctor->profile->id;
                                    $data_experience['hospital_name'] = $experience['exp_hosp_name'];
                                    $data_experience['designation'] = $experience['exp_desigination'];
                                    $data_experience['start_date'] = $exp_start_date;
                                    $data_experience['end_date'] = $exp_end_date;
                                    $data_experience['country'] = $experience['exp_country'];
                                    DoctorExperiance::Create($data_experience);
                                }

                            }
                            if (sizeof($exist_experience) > 0) {
                                DoctorExperiance::whereId($exist_experience[$i])->delete();
                            }
                        }
                    }
                    // update video_consultation against a given doctor
                    if ($request->video_consultation) {
                        if (!empty($request->video_consultation)) {
                            $is_online = $request->video_consultation['is_online'] ? 'true' : 'false';
                            DoctorProfile::whereUserId($doctor->id)->update(['is_video_enable' => $is_online]);
                            $video_consultation = [];
                            $video_consultation_day = [];
                            $video_consultation_add = [];
                            $check_video = "";
                            $is_online = (string)($request->video_consultation['is_online']);
                            $is_online = (String)($request->video_consultation['is_online']);
                            $is_online = $request->video_consultation['is_online'] ? 'true' : 'false';
                            $emailNotification = (string)($request->video_consultation['emailNotification']);
                            $emailNotification = (String)($request->video_consultation['emailNotification']);
                            $emailNotification = $request->video_consultation['emailNotification'] ? 'true' : 'false';
                            //dd($request->video_consultation['video_consult_id']);
                            if (isset($request->video_consultation['video_consult_id'])) {
                                $check_video = DoctorVideoConsultancy::findOrFail($request->video_consultation['video_consult_id']);
                                $consultation_id = $check_video->id;
                            }
                            //dd($check_video);
                            if ($check_video) {
                                $video_consultation['fee'] = $request->video_consultation['video_consultation_fee'];
                                $video_consultation['waiting_time'] = $request->video_consultation['v_c_waiting_time'];
                                $video_consultation['is_online'] = $is_online;
                                $video_consultation['is_email_notification_enabled'] = $emailNotification;
                                $video_consultation_data = DoctorVideoConsultancy::whereDoctorProfileId($doctor->profile->id)->update($video_consultation);

                            } else {
                                $video_consultation_add['doctor_profile_id'] = $doctor->profile->id;
                                $video_consultation_add['fee'] = $request->video_consultation['video_consultation_fee'];
                                $video_consultation_add['waiting_time'] = $request->video_consultation['v_c_waiting_time'];
                                $video_consultation_add['is_online'] = $is_online;
                                $video_consultation_add['is_email_notification_enabled'] = $emailNotification;
                                $video_consultation_data = DoctorVideoConsultancy::Create($video_consultation_add);
                                $consultation_id = $video_consultation_data->id;
                            }

                            if (!empty($request->video_consultation['video_consultation_day'])) {

                                $count_data = DoctorVideoConsultancyDay::whereDoctorProfileId($doctor->profile->id)->first();
                                if ($count_data) {
                                    DoctorVideoConsultancyDay::whereDoctorProfileId($doctor->profile->id)->delete();
                                }
                                foreach ($request->video_consultation['video_consultation_day'] as $consultancy) {

                                    $video_start_time = "";
                                    $video_end_time = "";
                                    if (!empty($consultancy['video_start_time'])) {
                                        $video_start_time = $consultancy['video_start_time']['hour'] . ":" . $consultancy['video_start_time']['minute'];
                                    }
                                    if (!empty($consultancy['video_end_time'])) {
                                        $video_end_time = $consultancy['video_end_time']['hour'] . ":" . $consultancy['video_end_time']['minute'];
                                    }
                                    $video_consultation_day['doctor_profile_id'] = $doctor->profile->id;
                                    $video_consultation_day['doctor_video_consultancy_id'] = $consultation_id;
                                    $video_consultation_day['day'] = $consultancy['video_day'];
                                    $video_consultation_day['start_time'] = $video_start_time;
                                    $video_consultation_day['end_time'] = $video_end_time;
                                    $video_consultation_day['duration'] = $consultancy['video_duration'];
                                    $video_consultation_day_data = DoctorVideoConsultancyDay::Create($video_consultation_day);
                                }

                            }
                        }
                    }
                    DB::commit();
                    return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Doctor profile updated successfully');
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
                }

            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'User not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  get specialities
     * @param no
     * @return mixed
     */
    public function getSpecialities()
    {
        $specialty = Speciality::all();
        if ($specialty) {
            $transformedspeciality = Transformer::transformASpecialities($specialty);
            return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedspeciality);
        } else {
            return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Specialities not found');
        }

    }

    /**
     *  cancel Appointment.
     * @param Request $request
     * @return mixed
     */
    public function cancelInstantAppointment($appointment_id)
    {
        try {
            DB::beginTransaction();
            $user = auth()->guard('api')->user();
            $doctor = User::whereUserType('doctor')->whereId($user->id)->first();
            if (!empty($doctor)) {
                $appointment = Appointment::whereId($appointment_id)->where('type', 'instant')->where('appointment_status', '!=',  'canceled')->first();
                if (!empty($appointment)) {
                    $appointment->update(['appointment_status' => 'canceled']);
                    $current_time = date('H:i');
                    $start = new DateTime($current_time);
                    $end_time = date('H:i', strtotime('+15 minutes', strtotime($appointment->start_time)));
                    $end = new DateTime(date('H:i', strtotime('+15 minutes', strtotime($appointment->start_time))));

                    $instant_appointment = [
                        'appointment_id' => $appointment->id,
                        'cancel_time' => $current_time,
                        'start_time' => $appointment->start_time,
                        'doctor_profile_id' => $appointment->doctor_profile_id,
                    ];
                    if($appointment->start_time >= $current_time && ($end_time < $current_time)) {
                        $interval = $start->diff($end);
                        $instant_appointment['cancel_action'] = 'after';
                    } else {
                        $end = new DateTime(date('H:i', strtotime('+15 minutes', strtotime($current_time))));
                        $interval = $start->diff($end);
                        $instant_appointment['cancel_action'] = 'before';
                    }
                    $instant_appointment['time_difference'] = $interval->format('%H:%i');
                    InstantAppointment::create($instant_appointment);
                    $appointments = Appointment::whereDoctorProfileId($appointment->doctor_profile_id)->where('appointment_status', '!=', 'canceled')->where('booking_date', '>=', date('Y-m-d'))->where('start_time', '>', date('H:i'))->get();

                    foreach ($appointments as $app) {
                        $start = new DateTime($app->start_time);
                        $added_time = $start->sub($interval);
                        $new_time = $added_time->format('H:i');
                        $app->update(['start_time' => $new_time]);
                    }
                    DB::commit();
                    return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Appointment cancelled successfully');
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment not found');
                }
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  complete Appointment.
     * @param Request $request
     * @return mixed
     */
    public function completeInstantAppointment($appointment_id)
    {
        try {
            DB::beginTransaction();
            $user = auth()->guard('api')->user();
            $doctor = User::whereUserType('doctor')->whereId($user->id)->first();
            if (!empty($doctor)) {
                $appointment = Appointment::whereId($appointment_id)->where('type', 'instant')->where('appointment_status', 'pending')->first();
                if (!empty($appointment)) {
                    $appointment->update(['appointment_status' => 'completed']);
                    $current_time = date('H:i');
                    $start = new DateTime($current_time);
                    $end_time = date('H:i', strtotime('+15 minutes', strtotime($appointment->start_time)));
                    $end = new DateTime(date('H:i', strtotime('+15 minutes', strtotime($appointment->start_time))));

                    $instant_appointment = [
                        'appointment_id' => $appointment->id,
                        'cancel_time' => $current_time,
                        'start_time' => $appointment->start_time,
                        'doctor_profile_id' => $appointment->doctor_profile_id,
                    ];
                    if($appointment->start_time >= $current_time && ($end_time < $current_time)) {
                        $interval = $start->diff($end);
                        $instant_appointment['cancel_action'] = 'after';
                    }
//                    else {
//                        $end = new DateTime(date('H:i', strtotime('+15 minutes', strtotime($current_time))));
//                        $interval = $start->diff($end);
//                        $instant_appointment['cancel_action'] = 'before';
//                    }
                    $instant_appointment['time_difference'] = $interval->format('%H:%i');
                    InstantAppointment::create($instant_appointment);
                    $appointments = Appointment::whereDoctorProfileId($appointment->doctor_profile_id)->where('appointment_status', '!=', 'canceled')->where('booking_date', '>=', date('Y-m-d'))->where('start_time', '>', date('H:i'))->get();

                    foreach ($appointments as $app) {
                        $start = new DateTime($app->start_time);
                        $added_time = $start->sub($interval);
                        $new_time = $added_time->format('H:i');
                        $app->update(['start_time' => $new_time]);
                    }
                    DB::commit();
                    return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Appointment completed successfully');
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment not found');
                }
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

}
