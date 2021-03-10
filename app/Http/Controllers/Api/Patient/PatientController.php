<?php

namespace App\Http\Controllers\Api\Patient;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentSymptom;
use App\Models\Channel;
use App\Models\InstantAppointment;
use App\Models\MedicalRecord;
use App\Models\PatientProfile;
use App\Models\DoctorProfile;
use App\Models\Prescription;
use App\Models\Rating;
use App\Models\Symptom;
use App\User;
use App\Models\Relation;
use App\Models\PatientRecord;
use DateTime;
use App\Traits\Transformer;
use App\Traits\PatientTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Agora\RtcTokenBuilder;
use phpDocumentor\Reflection\Types\Nullable;

class PatientController extends Controller
{
    /**
     *  doctor Slot Booking OR Appointment.
     * @param Request $request
     * @return mixed
     */
    public function doctorSlotBooking(Request $request, $id)
    {
        $rules = [
//            'booking_date ' => 'required',
            'time_slot' => 'nullable',
            'patient_type' => 'required',
        ];
        if ($request->patient_type == 'other') {
            $rules['relation_id'] = 'nullable';
            if ($request->relation_id == 0) {
                $rules['patient_name'] = 'required';
                $rules['patient_relation'] = 'required';
                $rules['patient_phone_number'] = 'nullable';
                $rules['patient_gender'] = 'required';
            }
        }
        $request->validate($rules, []);
        try {
            DB::beginTransaction();
            $patient_user = auth()->guard('api')->user();
            if (!empty($patient_user)) {
                $patient = PatientProfile::whereUserId($patient_user->id)->first();
                if (!empty($request->patient_type)) {
                    $user = User::whereUserType('doctor')->findOrFail($id);
                    if ($user) {
                        $doctor = $user->whereHas('profile', function ($query) use ($id) {
                            $query->whereUserId($id);
                        })->with(['profile'])->first();
                        if ($doctor) {

                            $appointment_of_person = Appointment::whereDoctorProfileId($doctor->profile->id)->whereStartTime($request->time_slot)->whereBookingDate($request->booking_date)->whereNotIn('appointment_status', ['canceled'])->first();
                            $messge = 'Appointment already exist on given slot';
                            if ($appointment_of_person) {
                                return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', $messge);
                            }
                            if ($request->patient_type == 'self') {
                                $appointment = Appointment::whereDoctorProfileId($doctor->profile->id)->whereBookingDate($request->booking_date)->whereStartTime($request->time_slot)->whereNotIn('appointment_status', ['canceled'])->first();
                                $messge = 'Appointment already exist';
                            } else {
                                $relation = Relation::whereId($request->relation_id)->first();
                                if (!empty($relation)) {
                                    $check = Appointment::whereDoctorProfileId($doctor->profile->id)->whereBookingDate($request->booking_date)->whereRelationId($request->relation_id)->whereNotIn('appointment_status', ['canceled'])->first();
                                    if (!empty($check)) {
                                        return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', 'Appointment already exist against that person');
                                    }
                                    $appointment_of_person = Appointment::whereDoctorProfileId($doctor->profile->id)->whereRelationId($request->relation_id)->whereStartTime($request->time_slot)->whereBookingDate($request->booking_date)->whereNotIn('appointment_status', ['canceled'])->first();
                                    $messge = 'Appointment already exist on given slot';
                                    if ($appointment_of_person) {
                                        return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', 'Appointment already exist against that person');
                                    }
                                } else {

                                    $check = Appointment::whereDoctorProfileId($doctor->profile->id)->whereBookingDate($request->booking_date)->whereStartTime($request->time_slot)->whereNotIn('appointment_status', ['canceled'])->first();
                                    if (!empty($check)) {
                                        return $this->apiResponse(JsonResponse::HTTP_BAD_REQUEST, 'message', 'Appointment already exist');
                                    }
                                }

                            }
                            if (!empty($appointment)) {
                                return $this->apiResponse(JsonResponse::HTTP_BAD_REQUEST, 'message', $messge);
                            } else {
                                $data = [
                                    'doctor_profile_id' => $doctor->profile->id,
                                    'patient_profile_id' => $patient->id,
                                    'type' => 'online_consultation',
                                    'fee_status' => 'unpaid',
                                    'booking_date' => $request->booking_date,
                                    'start_time' => $request->time_slot,
                                ];
                                if ($request->patient_type == 'self') {
                                    //dd($request->booking_date);
                                    $appointment = Appointment::create($data);
                                }
                                if ($request->patient_type == 'other') {
                                    if (empty($request->relation_id) || ($request->relation_id == 0)) {
                                        $relation = Relation::create([
                                            'patient_profile_id' => $patient->id,
                                            'name' => $request->patient_name,
                                            'gender' => $request->patient_gender,
                                            'relation' => $request->patient_relation,
                                            'phone' => $request->patient_phone_number ? $request->patient_phone_number : $user->phone,
                                        ]);
                                    }
                                    if (!empty($request->relation_id) && ($request->relation_id != 0)) {
                                        $relation_id = Relation::whereid($request->relation_id)->first();
                                        if (empty($relation_id)) {
                                            return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', "Relation doesn't exist");
                                        }
                                        $data['relation_id'] = $request->relation_id;
                                    } else {
                                        $data['relation_id'] = $relation->id;
                                    }
                                    $data['patient_type'] = 'other';
                                    $appointment = Appointment::create($data);
                                }
                                DB::commit();
                                if ($appointment) {
                                    $transformed_booking_data = PatientTransformer::transformOnlineSlotBooking($appointment, $id, $user);
                                    return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformed_booking_data);
                                }
                            }
                        } else {
                            return $this->apiResponse(JsonResponse::HTTP_BAD_REQUEST, 'message', 'Doctor profile not found');
                        }

                    } else {
                        return $this->apiResponse(JsonResponse::HTTP_BAD_REQUEST, 'message', 'Doctor not found');
                    }

                }
            } else {
                return $this->apiResponse(JsonResponse::HTTP_UNAUTHORIZED, 'message', 'Unauthenticated');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  patient profile.
     * @param Request $request
     * @return mixed
     */
    public function profile($id)
    {
        try {
            DB::beginTransaction();
            $patient = User::whereUserType('patient')->whereId($id)->first();
            if ($patient) {
                $patient_data = $patient->whereHas('patientProfile', function ($query) use ($id) {
                    $query->whereUserId($id);
                })->with(['patientProfile'])->first();
                if ($patient_data) {
                    $transformedPatient = PatientTransformer::transformProfile($patient_data);
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
     *  Update profile
     * @param Request $request
     * @return mixed
     */
    public function updateProfile(Request $request, $id)
    {

        $rules = [
            'dob' => 'nullable',
            'blood_group' => 'nullable',
            'weight' => 'nullable',
            'height' => 'nullable',
            'gender' => 'nullable',
            'name' => 'required',
            'email' => 'nullable',
            'address' => 'nullable',
            'age' => 'nullable',
            //'marital_status' => 'required',
        ];
        $request->validate($rules, []);
        try {
            DB::beginTransaction();
            $user = Auth::user();
            $patient = User::whereUserType('patient')->whereId($id)->first();
            $PatientProfile = PatientProfile::whereUserId($id)->first();
            if (!empty($patient)) {
                $userdata = [
                    'name' => $request->name,
                    'email' => $request->email,
                ];
                User::whereId($id)->update($userdata);
                $data = [
                    'dob' => $request->dob,
                    'blood_group' => $request->blood_group,
                    'weight' => $request->weight,
                    'height' => $request->height,
                    'gender' => $request->gender,
                    'age' => $request->age,
                    'address' => $request->address,
                    'marital_status' => $request->marital_status,
                ];
                // PatientProfile::whereUserId($id)->update($data);
                $PatientProfile->update($data);
                DB::commit();
                return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Profile updated successfully');
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Patient not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  Add record to appointment.
     * @param Request $request
     * @return mixed
     */
    public function addRecordToAppointment(Request $request, $id, $appointment_id)
    {
        $rules = [
//            'appointment_id' => 'required',
            'created_at' => 'required',
            'upload_by' => 'required',
            'reports' => 'nullable',
            'prescriptions' => 'nullable',
            'invoices' => 'nullable',
        ];
        $request->validate($rules, []);
        try {
            $user = Auth::user();
            $patient = User::whereUserType('patient')->whereId($user->id)->first();
            if (!empty($patient)) {
                $appointment = Appointment::whereId($appointment_id)->first();
                //dd($appointment);
                if (!empty($appointment)) {
                    $precription = Prescription::create([
                        'doctor_profile_id' => $appointment->doctor_profile_id,
                        'patient_profile_id' => $appointment->patient_profile_id,
                        'appointments_id' => $appointment->id,
                        'uploaded_by' => $request->upload_by,
                    ]);

                    if ($request->hasFile('reports')) {
                        $files = $request->file('reports');
                        foreach ($files as $file) {
                            //$path = Storage::disk('public')->put('prescription/', $file);
                            $path = Storage::disk('s3')->put('prescription', $file);
                            MedicalRecord::create([
                                'image_url' => $path,
                                'prescriptions_id' => $precription->id,
                                'Type' => 'report'
                            ]);
                        }
                    }
                    if ($request->hasFile('prescriptions')) {

                        $files = $request->file('prescriptions');
                        foreach ($files as $file) {
                            //$path = Storage::disk('public')->put('prescription/', $file);
                            $path = Storage::disk('s3')->put('prescription', $file);
                            MedicalRecord::create([
                                'image_url' => $path,
                                'prescriptions_id' => $precription->id,
                                'Type' => 'prescription'
                            ]);
                        }
                    }
                    if ($request->hasFile('invoices')) {
                        $files = $request->file('invoices');
                        foreach ($files as $file) {
                            //$path = $file->store('prescription');

                            $path = Storage::disk('s3')->put('prescription', $file);
                            MedicalRecord::create([
                                'image_url' => $path,
                                'prescriptions_id' => $precription->id,
                                'Type' => 'invoice'
                            ]);
                        }

                    }
                    //DB::commit();
                    return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Record Added successfully');
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment not found');
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
     *  patient appointmetnts.
     * @param Request $request
     * @return mixed
     */
    public function patientAppointments($id)
    {
        try {
            DB::beginTransaction();
            $patient = User::whereUserType('patient')->whereId($id)->first();
            if ($patient) {
                $patient_data = PatientProfile::whereUserId($id)->first();
                if ($patient_data) {
                    $appointment_data = Appointment::wherePatientProfileId($patient_data->id)->whereHas('doctor')->whereNotIn('appointment_status', ['pending'])->whereNotIn('type', ['instant'])->with(['doctor' => function ($query) {
                        $query->select('id', 'user_id');
                        $query->with(['user' => function ($sub_query) {
                            $sub_query->select('id', 'profile_image', 'name');
                        }, 'videoConsultancy' => function ($sub_query) {
                            $sub_query->select('id', 'fee', 'doctor_profile_id');
                        }, 'specialities' => function ($sub_query) {
                            $sub_query->with(['speciality' => function ($sub_query) {
                                $sub_query->select('id', 'name');
                            }]);
                        }]);
                    }, 'rating', 'prescription' => function ($query) {
                        $query->with(['medicalRecords']);
                    }])->orderBy('booking_date', 'desc')->orderby(DB::raw("FIELD(appointment_status, 'pending', 'completed', 'canceled', 'expired')"))->orderby('start_time', 'desc')->get();
                    if ($appointment_data) {
                        $transformedPatient = PatientTransformer::transformPatientAppointments($appointment_data);
                        return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedPatient);
                    } else {
                        return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Patient Appointments not found');
                    }

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
     *  patient instant appointments.
     * @param Request $request
     * @return mixed
     */
    public function pendingAppointments($id)
    {
        try {
            DB::beginTransaction();
            $limit = !empty(request('limit')) ? request('limit') : 10;
            $patient = User::whereUserType('patient')->whereId($id)->first();
            if ($patient) {
                $patient_data = PatientProfile::whereUserId($id)->first();
                if ($patient_data) {
                    $appointment_data = Appointment::wherePatientProfileId($patient_data->id)->whereNotIn('type', ['instant'])
                        ->where('appointment_status', 'pending')->where('booking_date', date('Y-m-d'))->whereHas('doctor')->with(['doctor' => function ($query) {
                            $query->select('id', 'user_id');
                            $query->with(['user' => function ($sub_query) {
                                $sub_query->select('id', 'profile_image', 'name');
                            }, 'videoConsultancy' => function ($sub_query) {
                                $sub_query->select('id', 'fee', 'doctor_profile_id');
                            }]);
                        }, 'rating', 'symptoms'])->orderBy('booking_date', 'desc')->orderby(DB::raw("FIELD(appointment_status, 'pending', 'completed', 'canceled', 'expired')"))->paginate($limit);
                    if ($appointment_data) {
                        $meta = Transformer::transformCollection($appointment_data);
                        $transformedPatient = PatientTransformer::transformPatientAppointments($appointment_data, true);
                        return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedPatient, $meta);
                    } else {
                        return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Patient Appointments not found');
                    }
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
     *  get medical records.
     * @param $id
     * @return mixed
     */
    public function getMedicalRecords()
    {
        try {
            DB::beginTransaction();
            $patient = auth()->guard('api')->user();
            $limit = !empty(request('limit')) ? request('limit') : 10;
            $device_type = 'web';

            if ($patient) {
                $patient_profile = $patient->patientProfile()->first();
                $records = Prescription::wherePatientProfileId($patient_profile->id)->with(['patientProfile', 'doctorProfile' => function ($query) {
                    $query->select('id', 'user_id');
                    $query->with(['user' => function ($sub_query) {
                        $sub_query->select('id', 'name');
                    }, 'specialities' => function ($query) {
                        $query->with(['speciality']);
                    }]);
                }, 'medicalRecords'])->orderBy('created_at', 'desc')->paginate($limit);
                $meta = Transformer::transformCollection($records);
                if (request('device_type')) {
                    $device_type = 'android';
                }
                $transformedRecord = PatientTransformer::transformPatientRecords($records, $device_type);
                return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedRecord, $meta);
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Patient not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  Get Appointment records.
     * @param Request $request
     * @return mixed
     */
    public function getAppointmentRecords($id, $appointment_id)
    {
        try {
            DB::beginTransaction();
            $patient = User::whereUserType('patient')->whereId($id)->first();
            if ($patient) {
                $appointment = Appointment::whereId($appointment_id)->with(['patientPrescription' => function ($query) {
                    $query->whereHas('medicalRecords')->with(['medicalRecords']);
                }])->first();
                if (!empty($appointment)) {
                    $transformedAppointment = PatientTransformer::transformAppointmentRecords($appointment);
                    return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedAppointment);
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment not found');
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
     *  cancel Appointment.
     * @param Request $request
     * @return mixed
     */
    public function cancelAppointment($id, $appointment_id)
    {
        try {
            DB::beginTransaction();
            $user = auth()->guard('api')->user();
            $patient = User::whereUserType('patient')->whereId($user->id)->first();
            if (!empty($patient)) {
                $appointment = Appointment::whereId($appointment_id)->first();
                if (!empty($appointment)) {
                    $appointment->update(['appointment_status' => 'canceled', 'start_time' => '',]);
                    DB::commit();
                    return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Appointment cancelled successfully');
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment not found');
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
     *  call.
     * @param Request $request
     * @return mixed
     */
    public function call($id, $appointment_id)
    {
        try {
            DB::beginTransaction();
            $user = Auth::user();
            $patient = User::whereUserType('patient')->whereId($id)->first();
            if (!empty($patient)) {
                $appointment = Appointment::whereId($appointment_id)->first();
                if (!empty($appointment)) {

                    /*******************************calling time logic here start*******************************************/
                    $currenttime = date("H:i");
                    $today = date("Y-m-d");
                    $time = strtotime($appointment->start_time);
                    $endTime = date("H:i", strtotime('+300 minutes', $time));
                    $minusTime = date("H:i", strtotime('+31 minutes', $time));
                    if (($appointment->booking_date < $today) || (($appointment->booking_date == $today) && ($currenttime > $endTime))) {
                        $can_call = false;
                        $is_expired = true;
                    } elseif (($appointment->booking_date == $today) && (($currenttime >= $appointment->start_time) && ($currenttime <= $minusTime))) {
                        $can_call = true;
                        $is_expired = false;
                    } elseif (($appointment->booking_date > $today) || (($appointment->booking_date == $today) && ($currenttime < $appointment->start_time))) {
                        $can_call = false;
                        $is_expired = false;
                    } else {
                        $can_call = false;
                        $is_expired = true;
                    }
                    /*******************************calling time logic here end*******************************************/

                    $random = Str::random(30);
                    $uid = $id;
                    $appID = config('app.agora_app_id');
                    $app_secret = config('app.agora_secret_key');
                    $channelName = $random;
                    $role = RtcTokenBuilder::RolePublisher;
                    $expireTimeInSeconds = 86400;
                    $currentTimestamp = (new  \DateTime("now", new  \DateTimeZone('UTC')))->getTimestamp();
                    $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;
                    $token = RtcTokenBuilder::buildTokenWithUid($appID, $app_secret, $channelName, $uid, $role, $privilegeExpiredTs);
                    /*Save Token for Every user for video calling*/
                    $doctor_data = Appointment::whereId($appointment_id)->with(['doctor' => function ($query) {
                        $query->with(['user']);
                    }])->first();
                    $channel_data = Channel::whereAppointmentId($appointment->id)->first();

                    if ($channel_data) {
                        $channel_data->update([
                            'patient_token' => $token,
                            'is_patient_called' => true,
                            'appointment_id' => $appointment->id,
                        ]);
                        $channel = Channel::whereAppointmentId($appointment->id)->first();
                    } else {
                        $channel = Channel::Create([
                            'patient_token' => $token,
                            'name' => $channelName,
                            'is_patient_called' => true,
                            'appointment_id' => $appointment->id,
                        ]);
                    }

                    $time = $appointment->start_time;
                    $date = $appointment->booking_date;

                    $booking_date = date('d F, Y ', strtotime($date));
                    $booking_time = date('h:i A', strtotime($time));
                    $data = [
                        'appointment_id' => $appointment->id,
                        'channel_name' => $channel->name,
                        'agora_token' => $channel->patient_token,
                        'status' => $channel->status,
                        'booking_date' => $booking_date,
                        'booking_time' => $booking_time,
                        'can_call' => $can_call,
                        'is_expired' => $is_expired,
                        'booking_date' => $booking_date,
                        'booking_time' => $booking_time,
                        'is_doctor_called' => $channel->is_doctor_called,
                        'is_patient_called' => $channel->is_patient_called,
                        'doctor_user_id' => $doctor_data->doctor->user->id,
                    ];

                    DB::commit();
                    return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $data);
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment not found');
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
     *  call Rating.
     * @param Request $request
     * @return mixed
     */
    public function callRating($id, $appointment_id, Request $request)
    {
        try {
            DB::beginTransaction();
            $user = Auth::user();
            $patient = User::whereUserType('patient')->whereId($id)->first();
            if (!empty($patient)) {
                $appointment = Appointment::whereId($appointment_id)->first();
                if (!empty($appointment)) {
                    $userdata = Appointment::whereId($appointment_id)->with(['doctor', 'patient'])->first();
                    Rating::Create([
                        'doctor_profile_id' => $userdata->doctor->id,
                        'patient_profile_id' => $userdata->patient->id,
                        'appointment_id' => $appointment_id,
                        'star_value' => $request->rating ? $request->rating : 0.0,
                        'is_like' => $request->is_satisfied ? $request->is_satisfied : false,
                        'comment' => $request->comment,
                    ]);
                    DB::commit();
                    return $this->apiResponse(JsonResponse::HTTP_OK, 'data', 'Rating added successfully');
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment not found');
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
     *  patient relatives.
     * @param Request $request
     * @return mixed
     */
    public function getRelatives($id)
    {
        try {
            DB::beginTransaction();
            $patient = User::whereUserType('patient')->whereId($id)->first();
            if ($patient) {
                $patient_data = $patient->whereHas('patientProfile')->with(['patientProfile' => function ($query) {
                    $query->with(['relation']);
                }])->first();
                if ($patient_data) {
                    $transformedPatient = PatientTransformer::transformRelation($patient_data->patientProfile->relation);
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
     *  Book instant appointments
     * @param Request $request
     * @return mixed
     */
    public function instantAppointments(Request $request)
    {
        $rules = [
            'symptoms' => 'required'
        ];
        $request->validate($rules, []);
        try {
            $auth_user = auth()->guard('api')->user();
            $patient_profile = PatientProfile::whereUserId($auth_user->id)->first();
            $patient = $patient_profile->whereUserId($auth_user->id)->whereHas('appointments', function ($query) {
                $query->whereIn('appointment_status', ['pending']);
                $query->where('booking_date', date('Y-m-d'));
                $query->whereType('instant');
            })->with(['appointments' => function ($query) {
                $query->whereType('instant');
                $query->where('appointment_status', 'pending');
            }])->first();
//            if (!empty($patient)) {
////            $doctor_profile_id = $patient->appointments[0]->doctor_profile_id;
////            Appointment::where('doctor_profile_id', $doctor_profile_id)->
////            dd($patient->appointments[0]->doctor_profile_id);
//                return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', 'Your Appointment is already in pending status!');
//            }
            $doctors = DoctorProfile::withCount(['appointments' => function ($query) {
                $query->where('type', 'instant');
                $query->where('appointment_status', 'pending');
                $query->where('booking_date', date('Y-m-d'));
//                $query->where('start_time', '>=', date('H:i'));
            }])->where('is_instant', true)->whereHas('videoConsultancyDays', function ($query) {
                $query->where('end_time', '>', date('H:i'));
            })->with(['videoConsultancyDays', 'user']);
            $doctors = $doctors->orderBy('appointments_count', 'asc')->get();
            $appointment_data = [];
            if ($doctors->count() > 0) {
                foreach ($doctors as $doctor) {
                    $new_date = date('y-m-d');
                    if ($doctor->videoConsultancyDays->count() > 0) {
                        foreach ($doctor->videoConsultancyDays as $key => $consultancyDay) {
                            $start_time = $consultancyDay->start_time;
                            $end_time = $consultancyDay->end_time;
                            $date = $consultancyDay->day;
                            $duration = $consultancyDay->duration ? ($consultancyDay->duration) : 15;
//                        $duration = 15;
                            if ($date == date('D')) {
                                if (!empty($start_time) && !empty($end_time)) {
                                    $appointment = Self::getOnlineBookedSloteByDate($doctor->id, $new_date);
                                    if (empty($appointment)) {
                                        $start_time = date('H:i');
                                    } else {
                                        $apt = $appointment[0];
                                        $start_time = date('H:i', strtotime('+15 minutes', strtotime($apt['start_time'])));
                                    }
                                    $timeslots = Self::getSlotsByDay($start_time, $end_time, $new_date, $duration);
                                    if (!empty($appointment)) {
                                        $apps = array();
                                        foreach ($appointment as $app) {
                                            $apps[] = $app['start_time'];
                                        }
                                        $cancel_appointments = InstantAppointment::where('doctor_profile_id', $doctor->id)->whereDate('created_at', date('Y-m-d'))->where('cancel_action', 'after')->get();
                                        if ($cancel_appointments->count() > 0) {
                                            $start_time = end($apps);
                                            $start_time = date('H:i', strtotime('+' . $duration . ' minutes', strtotime($start_time)));
                                            $timeslots = Self::getSlotsByDay($start_time, $end_time, $new_date, $duration);
                                        }
                                        $intersection = array_intersect($apps, $timeslots);
                                        $slots = array_diff($timeslots, $intersection);
                                        $timeslots = array_values($slots);
                                    }
                                    if (count($timeslots) > 0) {
                                        $data = [
                                            'doctor_profile_id' => $doctor->id,
                                            'patient_profile_id' => $patient_profile->id,
                                            'type' => 'instant',
                                            'fee_status' => 'unpaid',
                                            'booking_date' => $new_date,
                                            'start_time' => $timeslots[0],
                                        ];
                                        $appointment = Appointment::create($data);
                                        $symptoms = $request->symptoms;
                                        for ($i = 0; $i < count($symptoms); $i++) {
                                            AppointmentSymptom::create([
                                                'appointment_id' => $appointment->id,
                                                'symptom_id' => $symptoms[$i],
                                            ]);
                                        }
                                        $appointment_data = Appointment::where('id', $appointment->id)->where('type', 'instant')->whereHas('doctor')->with(['doctor' => function ($query) {
                                            $query->select('id', 'user_id');
                                            $query->with(['user' => function ($sub_query) {
                                                $sub_query->select('id', 'profile_image', 'name');
                                            }, 'videoConsultancy' => function ($sub_query) {
                                                $sub_query->select('id', 'fee', 'doctor_profile_id');
                                            }]);
                                        }, 'rating', 'symptoms'])->first();
                                        $transformedPatient = PatientTransformer::transformAppointment($appointment_data, $doctor->user->id, $doctor->user, true);
                                        return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedPatient);
                                    }
                                }
                            }
                            /* date wise slotting*/

                            /* date wise slotting*/
                        }
                    }
                }
                if (empty($appointment_data)) {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'No doctor available at that time please try later.');
                }
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'No doctor available');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }

    }

    /**
     *  patient instant appointments.
     * @param Request $request
     * @return mixed
     */
    public function patientInstantAppointments($id)
    {
        try {
            DB::beginTransaction();
            $limit = !empty(request('limit')) ? request('limit') : 10;
            $patient = User::whereUserType('patient')->whereId($id)->first();
            if ($patient) {
                $patient_data = PatientProfile::whereUserId($id)->first();
                if ($patient_data) {
                    $appointment_data = Appointment::wherePatientProfileId($patient_data->id)->where('type', 'instant')->where('appointment_status', '!=', 'pending')->whereHas('doctor')->with(['doctor' => function ($query) {
                        $query->select('id', 'user_id');
                        $query->with(['user' => function ($sub_query) {
                            $sub_query->select('id', 'profile_image', 'name');
                        }, 'videoConsultancy' => function ($sub_query) {
                            $sub_query->select('id', 'fee', 'doctor_profile_id');
                        }]);
                    }, 'rating', 'symptoms'])->orderBy('booking_date', 'desc')->orderby('start_time', 'desc')->paginate($limit);
                    if ($appointment_data) {
                        $meta = Transformer::transformCollection($appointment_data);
                        $transformedPatient = PatientTransformer::transformPatientAppointments($appointment_data, true);
                        return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedPatient, $meta);
                    } else {
                        return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Patient Appointments not found');
                    }
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
     *  patient instant appointments.
     * @param Request $request
     * @return mixed
     */
    public function pendingInstantAppointments($id)
    {
        try {
            DB::beginTransaction();
            $limit = !empty(request('limit')) ? request('limit') : 10;
            $patient = User::whereUserType('patient')->whereId($id)->first();
            if ($patient) {
                $patient_data = PatientProfile::whereUserId($id)->first();
                if ($patient_data) {
                    $appointment_data = Appointment::wherePatientProfileId($patient_data->id)->where('type', 'instant')->where('appointment_status', 'pending')->where('booking_date', date('Y-m-d'))->whereHas('doctor')->with(['doctor' => function ($query) {
                        $query->select('id', 'user_id');
                        $query->with(['user' => function ($sub_query) {
                            $sub_query->select('id', 'profile_image', 'name');
                        }, 'videoConsultancy' => function ($sub_query) {
                            $sub_query->select('id', 'fee', 'doctor_profile_id');
                        }]);
                    }, 'rating', 'symptoms'])->orderBy('booking_date', 'desc')->orderby(DB::raw("FIELD(appointment_status, 'pending', 'completed', 'canceled', 'expired')"))->paginate($limit);
                    if ($appointment_data) {
                        $meta = Transformer::transformCollection($appointment_data);
                        $transformedPatient = PatientTransformer::transformPatientAppointments($appointment_data, true);
                        return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedPatient, $meta);
                    } else {
                        return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Patient Appointments not found');
                    }
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
     *  cancel Appointment.
     * @param Request $request
     * @return mixed
     */
    public function cancelInstantAppointment($appointment_id)
    {
        try {
            DB::beginTransaction();
            $user = auth()->guard('api')->user();
            $patient = User::whereUserType('patient')->whereId($user->id)->first();
            if (!empty($patient)) {
                $appointment = Appointment::whereId($appointment_id)->where('type', 'instant')->where('appointment_status', '!=', 'canceled')->first();
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
                    if ($appointment->start_time < $current_time && ($end_time > $current_time)) {
                        $interval = $start->diff($end);
                        $instant_appointment['cancel_action'] = 'after';
                        $appointments = Appointment::whereDoctorProfileId($appointment->doctor_profile_id)->where('appointment_status', 'pending')->where('booking_date', '>=', date('Y-m-d'))->where('start_time', '>', date('H:i'))->get();
                    } else {
                        $end = new DateTime(date('H:i', strtotime('+15 minutes', strtotime($current_time))));
                        $interval = $start->diff($end);
                        $instant_appointment['cancel_action'] = 'before';
                        $appointments = Appointment::whereDoctorProfileId($appointment->doctor_profile_id)->where('appointment_status', 'pending')->where('booking_date', '>=', date('Y-m-d'))->where('start_time', '>', $appointment->start_time)->get();
                    }
                    $instant_appointment['time_difference'] = $interval->format('%H:%i');
                    InstantAppointment::create($instant_appointment);


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
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Patient not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  get symptoms
     * @param no
     * @return mixed
     */
    public function getSymptoms()
    {
        $symptoms = Symptom::all();
        if (!empty($symptoms)) {
            $transformed_symptoms = Transformer::transformSymptoms($symptoms);
            return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformed_symptoms);
        } else {
            return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Symptoms not found');
        }

    }

    /**
     *  get doctors against patient appointments.
     * @param no
     * @return mixed
     */
    public function getDoctors($id)
    {
        $rules = [
            'device_type' => 'required',
        ];
        request()->validate($rules, []);
        try {
            DB::beginTransaction();
            $limit = !empty(request('limit')) ? request('limit') : 10;
            $patient_data = User::whereUserType('patient')->whereId($id)->whereHas('patientProfile')->with(['patientProfile'])->first();
            if (!empty($patient_data)) {
                $appointments = Appointment::wherePatientProfileId($patient_data->patientProfile->id)->groupBy('doctor_profile_id');
                if (!empty($appointments)) {
                    if (request('filter')) {
                        $filter = request('filter') ? request('filter') : '';
                        if (!empty($filter)) {
                            $appointments->whereAppointmentStatus($filter);
                        }
                    };
                    $appointments = $appointments->whereHas('doctor')->with(['doctor' => function ($sub_query) {
                        $sub_query->select('id', 'user_id', 'gender');
                        $sub_query->with(['specialities' => function ($query) {
                            $query->select('id', 'doctor_profile_id', 'speciality_id');
                            $query->whereHas('speciality')->with(['speciality' => function ($sub_query2) {
                                $sub_query2->select('id', 'name');
                            }]);
                        }, 'videoConsultancy' => function ($sub_query_3) {
                            $sub_query_3->select('id', 'doctor_profile_id', 'fee');
                        }, 'videoConsultancyDays' => function ($sub_query_3) {
                            $sub_query_3->select('id', 'doctor_profile_id', 'doctor_video_consultancy_id', 'day');
                        }]);
                        $sub_query->with('user');
                    }])->orderBy('booking_date', 'asc')
                        ->orderby(DB::raw('case when appointment_status= "pending" then 1 when appointment_status= "completed" then 2 when appointment_status= "canceled" then 3 end'))
                        ->paginate($limit);
                    $transformedDoctors = PatientTransformer::transformDoctors($patient_data, $appointments);
                    $meta = Transformer::transformCollection($appointments);
                    return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedDoctors, $meta);
                } else {
                    return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Appointment record not found');
                }
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Patient not found');
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  Add record against patient.
     * @param Request $request
     * @return mixed
     */
    public function addRecord(Request $request)
    {
        $rules = [
            'id' => 'nullable',
            'device_type' => 'required',
            'reports' => 'nullable',
            'prescriptions' => 'nullable',
            'invoices' => 'nullable',
            'name' => 'nullable'
        ];
        $request->validate($rules, []);
        try {
            $user = auth()->guard('api')->user();
            $patient = User::whereUserType('patient')->whereId($user->id)->whereHas('patientProfile')->with(['patientProfile'])->first();
            if (!empty($patient)) {
                if ($request->id) {
                    if (!empty($request->id)) {
                        $prescription = Prescription::whereId($request->id)->first();
                    }
                } else {
                    $prescription = Prescription::create([
                        'patient_profile_id' => $patient->patientProfile->id,
                        'name' => $request->name,
                        'uploaded_by' => 'self',
                    ]);
                }

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
                            'prescriptions_id' => $prescription->id,
                            'Type' => 'prescription'
                        ]);
                        Storage::disk('s3')->put($path, $image_base64);
                    }

                    foreach ($req->invoices as $key => $value) {

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
                        $path = 'invoice/' . uniqid() . '.' . $file_type;
                        MedicalRecord::create([
                            'image_url' => $path,
                            'prescriptions_id' => $prescription->id,
                            'Type' => 'invoice'
                        ]);
                        Storage::disk('s3')->put($path, $image_base64);
                    }

                    foreach ($req->reports as $key => $value) {

                        $image_parts = explode(";base64,", $value);
                        $file_parts = explode("/", $image_parts[0]);

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
                        $path = 'report/' . uniqid() . '.' . $file_type;
                        MedicalRecord::create([
                            'image_url' => $path,
                            'prescriptions_id' => $prescription->id,
                            'Type' => 'report'
                        ]);
                        Storage::disk('s3')->put($path, $image_base64);
                    }
                } else {
                    if ($request->hasFile('reports')) {
                        $files = $request->file('reports');
                        foreach ($files as $file) {
                            $path = Storage::disk('s3')->put('report', $file);
                            MedicalRecord::create([
                                'image_url' => $path,
                                'prescriptions_id' => $prescription->id,
                                'Type' => 'report'
                            ]);
                        }
                    }
                    if ($request->hasFile('prescriptions')) {

                        $files = $request->file('prescriptions');
                        foreach ($files as $file) {
                            $path = Storage::disk('s3')->put('prescription', $file);
                            MedicalRecord::create([
                                'image_url' => $path,
                                'prescriptions_id' => $prescription->id,
                                'Type' => 'prescription'
                            ]);
                        }
                    }
                    if ($request->hasFile('invoices')) {
                        $files = $request->file('invoices');
                        foreach ($files as $file) {
                            $path = Storage::disk('s3')->put('invoice', $file);
                            MedicalRecord::create([
                                'image_url' => $path,
                                'prescriptions_id' => $prescription->id,
                                'Type' => 'invoice',
                            ]);
                        }
                    }
                }
                $prescription = Prescription::whereId($prescription->id)->with(['medicalRecords'])->first();
                $transformedRecord = PatientTransformer::transformPrescriptionRecord($prescription);
                return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedRecord);

            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Patient not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  delete record.
     * @param no
     * @return mixed
     */
    public function deleteRecord($id)
    {
        try {
            $medical_record = MedicalRecord::whereId($id)->first();
            if (!empty($medical_record)) {
                $prescription_id = $medical_record->prescriptions_id;
                $records = MedicalRecord::where('prescriptions_id', $prescription_id);
                if ($records->count() === 1) {
                    Prescription::where('id', $prescription_id)->delete();
                }
                $path = $medical_record->image_url;
                Storage::disk('s3')->delete($path);
                MedicalRecord::whereId($id)->delete();
                return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Record deleted successfully');
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Record not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  delete prescription and their records
     * @param no
     * @return mixed
     */
    public function deletePrescription($id)
    {
        try {
            $prescription = Prescription::whereId($id)->with(['medicalRecords'])->first();
            if (!empty($prescription)) {
                foreach ($prescription->medicalRecords as $record) {
                    $path = $record->image_url;
                    Storage::disk('s3')->delete($path);
                    MedicalRecord::whereId($record->id)->delete();
                }
                $prescription->delete();
                return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Prescription deleted successfully');
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Prescription not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
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

    // get Online Booked Slots By Date
    static function getOnlineBookedSloteByDate($doc_id, $date = null)
    {
        $appointment = Appointment::where([['type', 'instant'], ['doctor_profile_id', $doc_id]])->whereDate('booking_date', $date)
            ->whereNotIn('appointment_status', ['canceled', 'expired', 'completed', 'not_appeared'])
            ->orderBy('id', 'desc')->get()->toArray();
        return $appointment;
    }
}
