<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Models\Membership;
use App\Models\PatientProfile;
use App\Models\Symptom;
use App\Traits\DoctorTransformer;
use App\Traits\PatientTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\User;
use App\UserDevice;
use App\Models\DoctorAward;
use App\Models\DoctorEducation;
use App\Models\DoctorExperiance;
use App\Models\Appointment;
use App\Models\DoctorHospital;
use App\Models\DoctorHospitalDay;
use App\Models\DoctorHospitalService;
use App\Models\DoctorHospitalVocation;
use App\Models\DoctorProfile;
use App\Models\DoctorService;
use App\Models\DoctorSpeciality;
use App\Models\DoctorVideoConsultancy;
use App\Models\DoctorVideoConsultancyDay;
use App\Models\Language;
use App\Models\Service;
use App\Models\Speciality;
use App\Models\Faq;
use App\Traits\Transformer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Validator;

class AdminController extends Controller
{
    public function storeDoctor(Request $request)
    {
        $data = [
            'pmdc' => 'required|unique:doctor_profiles,pmdc',
            'name' => 'required',
            'email' => 'required|unique:users,email',
            'is_instant' => 'required',
            'education' => 'required',
            'phone' => 'required'
        ];
        if ($request->is_instant === false) {
            $data['speciality'] = 'required';
        } else {
            $data['speciality'] = 'nullable';
        }
        $request->validate($data, []);
        try {
            DB::beginTransaction();
            $doctor_profile = [];
            $language_data = [];
            $doc_speciality = [];
            $education = [];
            $experience = [];
            $services = [];
            $dataservice = [];
            $award = [];
            $faqs = [];
            $userData = [];
            $video_consultation_day = [];
            $video_consultation = [];
            $dob = "";
            $name = explode("@", $request->email);
            $user_name = $name[0] . '@eshaafi.com';

            //upload profile pic
            $path = "profiles/no-image.png";
            $req = json_decode(file_get_contents("php://input"));
            if ($req->profile_image) {
                $image_parts = explode(";base64,", $req->profile_image);

                $image_type_aux = explode("image/", $image_parts[0]);

                $image_type = $image_type_aux[1];

                $image_base64 = base64_decode($image_parts[1]);
                $path = 'profiles/' . uniqid() . '.' . $image_type;
                Storage::disk('s3')->put($path, $image_base64);
            }
            $is_online = (string)($request->video_consultation['is_online']);
            $is_online = (String)($request->video_consultation['is_online']);
            $is_online = $request->video_consultation['is_online'] ? 'true' : 'false';
            $userData = [
                'name' => $request->name,
                'user_name' => $user_name,
                'email' => $request->email,
                'profile_image' => !empty($path) ? $path : "",
                'password' => $request->password ? bcrypt($request->password) : bcrypt('123456'),
                'phone' => $request->phone,
                'user_type' => "doctor",
                'is_active' => "true",
            ];
            if (!empty($request->date_of_birth)) {
                $dob = $request->date_of_birth['year'] . "-" . $request->date_of_birth['month'] . "-" . $request->date_of_birth['day'];
            }
            $role = 'Doctor';
            $user = User::Create($userData);
            $user->syncRoles(['Doctor']);
            $doctor_profile['user_id'] = $user->id;
            $doctor_profile['pmdc'] = $request->pmdc;
            $doctor_profile['icon'] = 'no_image';
            $doctor_profile['address'] = $request->address;
            $doctor_profile['city'] = $request->city;
            $doctor_profile['dob'] = $dob;
            $doctor_profile['gender'] = $request->gender;
            $doctor_profile['country'] = $request->country;
            $doctor_profile['summary'] = $request->summary;
            $doctor_profile['is_video_enable'] = $is_online;
            $doctor_profile['is_instant'] = $request->is_instant === true ? 'true' : 'false';
            $doc_profile = DoctorProfile::Create($doctor_profile);
            // store record in language
            if (!empty($request->language)) {
                foreach ($request->language as $data_language) {
                    $language_data['doctor_profile_id'] = $doc_profile->id;
                    $language_data['language'] = $data_language;
                    $language = Language::Create($language_data);
                }

            }
            // store record in Doctor speciality

            if (!empty($request->speciality)) {
                $doc_speciality = [];

                foreach ($request->speciality as $speciality) {
                    $doc_speciality['doctor_profile_id'] = $doc_profile->id;
                    $doc_speciality['speciality_id'] = $speciality['id'];
                    $doctor_speciality = DoctorSpeciality::Create($doc_speciality);
                }
            }

            // store record in Doctor Education
            if (!empty($request->education)) {
                foreach ($request->education as $doc_education) {
                    $edu_start_date = "";
                    $edu_end_date = "";
                    if (!empty($doc_education['edu_start_date'])) {
                        $edu_start_date = $doc_education['edu_start_date']['year'] . "-" . $doc_education['edu_start_date']['month'] . "-" . $doc_education['edu_start_date']['day'];
                    }
                    if (!empty($doc_education['edu_end_date'])) {
                        $edu_end_date = $doc_education['edu_end_date']['year'] . "-" . $doc_education['edu_end_date']['month'] . "-" . $doc_education['edu_end_date']['day'];
                    }
                    $education['doctor_profile_id'] = $doc_profile->id;
                    $education['degree'] = $doc_education['degree'];
                    $education['institute_name'] = $doc_education['institute'];
                    $education['start_date'] = $edu_start_date;
                    $education['end_date'] = $edu_end_date;
                    $education['country'] = $doc_education['edu_country'];
                    $education_data = DoctorEducation::Create($education);

                }
            }

            // store record in Doctor experience
            if (!empty($request->experience)) {
                foreach ($request->experience as $doc_experience) {
                    $exp_start_date = "";
                    $exp_end_date = "";
                    if (!empty($doc_experience['exp_start_date'])) {
                        $exp_start_date = $doc_experience['exp_start_date']['year'] . "-" . $doc_experience['exp_start_date']['month'] . "-" . $doc_experience['exp_start_date']['day'];
                    }
                    if (!empty($doc_experience['exp_end_date'])) {
                        $exp_end_date = $doc_experience['exp_end_date']['year'] . "-" . $doc_experience['exp_end_date']['month'] . "-" . $doc_experience['exp_end_date']['day'];
                    }
                    $experience['doctor_profile_id'] = $doc_profile->id;
                    $experience['hospital_name'] = $doc_experience['exp_hosp_name'];
                    $experience['designation'] = $doc_experience['exp_desigination'];
                    $experience['start_date'] = $exp_start_date;
                    $experience['end_date'] = $exp_end_date;
                    $experience['country'] = $doc_experience['exp_country'];
                    $experience_data = DoctorExperiance::Create($experience);
                }
            }
            // store record in Doctor award
            if (!empty($request->award)) {
                foreach ($request->award as $doc_award) {

                    $award['doctor_profile_id'] = $doc_profile->id;
                    $award['achievement'] = $doc_award['award_achivements'];
                    $award['event_name'] = $doc_award['award_event_name'];
                    $award['designation'] = $doc_award['award_desigination'];
                    $award['award'] = $doc_award['award_recive_award'];
                    $award['received_from'] = $doc_award['award_recive_from'];
                    $award['dated'] = $doc_award['award_recived_dated'];
                    $award['country'] = $doc_award['award_country'];
                    DoctorAward::Create($award);
                }

            }

            // store record in Doctor services
            if (!empty($request->services)) {
                foreach ($request->services as $doc_services) {
                    $data_service['name'] = $doc_services['name'];
                    $data_services = Service::Create($data_service);
                    $services['doctor_profile_id'] = $doc_profile->id;
                    $services['service_id'] = $data_services->id;
                    $services_data = DoctorService::Create($services);
                }

            }

            // store record in Doctor Faqs
            if (!empty($request->faqs)) {
                foreach ($request->faqs as $doc_faqs) {
                    $faqs['added_by'] = $user->id;
                    $faqs['question'] = $doc_faqs['faq_question'];
                    $faqs['answer'] = $doc_faqs['faq_answer'];
                    $faqs_data = Faq::Create($faqs);
                }

            }
            // store record in Doctor Video Consultation
            if (!empty($request->video_consultation)) {
                $is_online = $request->video_consultation['is_online'] ? 'true' : 'false';
                $emailNotification = $request->video_consultation['emailNotification'] ? 'true' : 'false';
                $video_consultation['doctor_profile_id'] = $doc_profile->id;
                $video_consultation['fee'] = $request->video_consultation['video_consultation_fee'];
                $video_consultation['waiting_time'] = $request->video_consultation['v_c_waiting_time'];
                $video_consultation['is_online'] = $is_online;
                $video_consultation['is_email_notification_enabled'] = $emailNotification;
                $video_consultation_data = DoctorVideoConsultancy::Create($video_consultation);
                if (!empty($request->video_consultation)) {
                    foreach ($request->video_consultation['video_consultation_day'] as $consultancy) {
                        $video_start_time = "";
                        $video_end_time = "";
                        if (!empty($consultancy['video_start_time'])) {
                            $video_start_time = $consultancy['video_start_time']['hour'] . ":" . $consultancy['video_start_time']['minute'];
                        }
                        if (!empty($consultancy['video_end_time'])) {
                            $video_end_time = $consultancy['video_end_time']['hour'] . ":" . $consultancy['video_end_time']['minute'];
                        }
                        $video_consultation_day['doctor_profile_id'] = $doc_profile->id;
                        $video_consultation_day['doctor_video_consultancy_id'] = $video_consultation_data->id;
                        $video_consultation_day['day'] = $consultancy['video_day'];
                        $video_consultation_day['start_time'] = $video_start_time;
                        $video_consultation_day['end_time'] = $video_end_time;
                        $video_consultation_day['duration'] = $consultancy['video_duration'];
                        DoctorVideoConsultancyDay::Create($video_consultation_day);
                    }
                }
            }
            DB::commit();
            $data = ['message' => "Successfully Added"];
            return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $data);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    public function storeSpeciality(Request $request)
    {

        $data = [
            'name' => 'required',
        ];
        $request->validate($data, []);

        try {
            $data_speciality = [];
            //upload profile pic

            $path = "icon/no-image.png";
            $req = json_decode(file_get_contents("php://input"));
            if ($req->icon) {
                $image_parts = explode(";base64,", $req->icon);

                $image_type_aux = explode("image/", $image_parts[0]);

                $image_type = $image_type_aux[1];

                $image_base64 = base64_decode($image_parts[1]);
                $path = 'icon/' . uniqid() . '.' . $image_type;
                Storage::disk('s3')->put($path, $image_base64);
            }

            $data_speciality['name'] = $request->name;
            $data_speciality['icon'] = $path;
            $user = Speciality::Create($data_speciality);
            return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Data added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }

    }

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

    public function storeServices(Request $request)
    {
        if ($request->user_type == 'admin') {
            $data = [
                'name' => 'required',
            ];
        }
        $validator = Validator::make($request->all(), $data);

        if ($validator->fails()) {
            return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', $validator->errors());
        }
        try {
            $data['name'] = $request->name;
            $user = Service::Create($data);
            return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Data added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }

    }

    public function getServices()
    {
        $service = Service::all();
        return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $service);

    }

    // edit Doctor profile.
    public function editDoctor($id)
    {
        try {
            DB::beginTransaction();
            $doctor = User::whereId($id)->whereUserType('doctor')->whereHas('profile')->with(['profile' => function ($sub_query) {
                $sub_query->with(['education', 'awards', 'languag', 'experiences', 'specialities' => function ($sub_query) {
                    $sub_query->with(['speciality']);
                }, 'services' => function ($sub_query) {
                    $sub_query->with(['service']);
                }, 'videoConsultancy', 'videoConsultancyDays', 'awards', 'memberships', 'ratings', 'languag', 'appointments']);
            }])->first();
            if ($doctor) {
                $transformedDoctors = DoctorTransformer::transformDoctorProfile($doctor);
                return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedDoctors);
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    //get all doctors
    public function doctors()
    {
        try {
            DB::beginTransaction();
            $limit = request('limit') ? request('limit') : 5;
            $doctor = User::whereUserType('doctor')->whereHas('profile')->with(['profile' => function ($sub_query) {
                $sub_query->with(['education', 'awards', 'languag', 'experiences', 'specialities' => function ($sub_query) {
                    $sub_query->with(['speciality']);
                }, 'services' => function ($sub_query) {
                    $sub_query->with(['service']);
                }, 'videoConsultancy', 'videoConsultancyDays', 'awards', 'memberships', 'ratings', 'languag', 'appointments']);
            }])->paginate($limit);
            if ($doctor) {
                $transformedAdminDoctors = Transformer::transformAdminDoctors($doctor);
                $meta = Transformer::transformCollection($doctor);
                return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedAdminDoctors, $meta);
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    //update doctor profiles
    public function updateDoctor(Request $request, $id)
    {
        $rules = [
            'name' => 'required',
            'email' => 'nullable',
            'phone' => 'required',
            'pmdc' => 'required',
            'is_instant' => 'required',
            'education' => 'required',

        ];
        if ($request->is_instant === false) {
            $data['speciality'] = 'required';
        } else {
            $data['speciality'] = 'nullable';
        }
        $request->validate($rules, []);

        try {
            DB::beginTransaction();
            $user = User::whereId($id)->whereUserType('doctor')->first();
            if ($user) {
                $doctor = User::whereId($id)->whereUserType('doctor')->whereHas('profile')->with(['profile'])->first();
                if ($doctor) {
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
                    $data_languages = [];
                    $data_language = [];
                    $data_users = [];

                    //update in users table
                    $data_users = [
                        'name' => $request->name,
                        'email' => $request->email,
                        'phone' => $request->phone,
                        'profile_image' => $path,
                    ];
                    if ($request->password) {
                        if (!empty($request->password)) {
                            $data_users['password'] = bcrypt($request->password);
                        }
                    }

                    User::whereId($user->id)->update($data_users);

                    //update in doctor_profiles
                    $dob = $doctor->profile->dob;
                    if (!empty($request->date_of_birth)) {
                        $dob = $request->date_of_birth['year'] . "-" . $request->date_of_birth['month'] . "-" . $request->date_of_birth['day'];
                    }
                    $is_online = (string)($request->video_consultation['is_online']);
                    $is_online = (String)($request->video_consultation['is_online']);
                    $is_online = $request->video_consultation['is_online'] ? 'true' : 'false';
                    //dd($converted_is_video_enable);
                    $data_profiles = [
                        'pmdc' => $request->pmdc,
                        'dob' => $dob,
                        'gender' => $request->gender,
                        'summary' => $request->summary,
                        'is_video_enable' => $is_online,
                        'address' => $request->address,
                        'city' => $request->city,
                        'country' => $request->country,
                        'is_instant' => $request->is_instant === true ? 'true' : 'false',
                    ];
                    DoctorProfile::whereUserId($doctor->id)->update($data_profiles);

                    // update speciality against a given doctor
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

                    // update education against a given doctor
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
                    // update education against a given doctor
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
                    // update awards against a given doctor
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
                    // update membership against a given doctor
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
                    // update experience_detail against a given doctor
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
                    // update video_consultation against a given doctor
                    if (!empty($request->video_consultation)) {
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
                    // update Faqs against a given doctor
                    if (!empty($request->faqs)) {

                        $data_faqs = [];
                        $faq_data = "";
                        $check_faq = Faq::whereAddedBy($doctor->id)->first();

                        if ($check_faq) {
                            Faq::whereAddedBy($doctor->id)->delete();
                        }
                        foreach ($request->faqs as $faqs) {

                            $data_faqs['question'] = $faqs['faq_question'];
                            $data_faqs['answer'] = $faqs['faq_answer'];
                            $data_faqs['added_by'] = $doctor->id;
                            $faq_data = Faq::Create($data_faqs);
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

    // get dashboard counter record
    public function dashboardCounts()
    {
        $doctor = User::whereUserType('doctor')->count();
        $patient = User::whereUserType('patient')->count();
        $consultation = Appointment::whereType('online_consultation')->count();
        $today_registered = User::whereUserType('patient')->whereCreatedAt(date('Y-m-d'))->count();
        $data = [
            'doctor_count' => $doctor,
            'patient_count' => $patient,
            'total_consultation' => $consultation,
            'today_registered_patient' => $today_registered,
        ];
        if ($data) {
            return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $data);
        } else {
            return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Record not found');
        }
    }

    // delete Doctor profile.
    public function deleteDoctor($id)
    {
        try {
            $user = User::whereUserType('doctor')->findOrFail($id);
            if ($user) {
                DB::beginTransaction();
                $user->delete();
                DoctorProfile::whereUserId($id)->delete();
                DB::commit();

                return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Doctor deleted successfully');

            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Doctor not found');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    // delete Doctor profile.
    public function deleteSpeciality($id)
    {
        try {
            $speciality = Speciality::findOrFail($id);
            if ($speciality) {
                $doctor_speciality = DoctorSpeciality::whereSpecialityId($id)->first();
                if ($doctor_speciality) {
                    return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', 'Speciality exist agianst doctor');
                } else {

                    DB::beginTransaction();
                    $speciality->delete();
                    DB::commit();

                    return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Speciality deleted successfully');
                }

            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Speciality not found');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    // delete Doctor profile.
    public function updateSpeciality(Request $request, $id)
    {
        try {
            $speciality = Speciality::findOrFail($id);
            if ($speciality) {
                DB::beginTransaction();
                //upload profile pic
                $path = $speciality->icon;
                $req = json_decode(file_get_contents("php://input"));
                if ($req->icon) {
                    if (Storage::disk('s3')->exists($speciality->icon)) {
                        Storage::disk('s3')->delete($speciality->icon);
                    }
                    $image_parts = explode(";base64,", $req->icon);

                    $image_type_aux = explode("image/", $image_parts[0]);

                    $image_type = $image_type_aux[1];

                    $image_base64 = base64_decode($image_parts[1]);
                    $path = 'icon/' . uniqid() . '.' . $image_type;
                    Storage::disk('s3')->put($path, $image_base64);
                }
                $speciality->update(['name' => $request->name, 'icon' => $path]);
                DB::commit();

                return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Speciality updated successfully');

            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Speciality not found');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  Registered patients.
     * @param
     * @return mixed
     */
    public function getPatients()
    {
        try {
            $limit = !empty(request('limit')) ? request('limit') : 10;
            DB::beginTransaction();
            $patients = User::whereUserType('patient')->whereIsActive('true')->whereHas('patientProfile', function ($query) {
            })->with(['patientProfile'])->paginate($limit);
            if ($patients) {
                $transformedPatient = Transformer::transformPatients($patients);
                $meta = Transformer::transformCollection($patients);
                return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedPatient, $meta);
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Record found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  Get appointments
     * @param
     * @return mixed
     */
    public function getAppointments()
    {
        try {
            DB::beginTransaction();
            $limit = !empty(request('limit')) ? request('limit') : 10;
            $appointments = Appointment::with(['patient' => function ($query) {
                $query->with(['user']);
            }, 'doctor' => function ($query) {
                $query->with(['user']);
                $query->whereHas('videoConsultancy')->with(['videoConsultancy']);
            }])->orderBy('booking_date', 'asc')->orderby('id', 'desc');
            if (request('filter')) {
                $filter = request('filter') ? request('filter') : '';
                if (!empty($filter)) {
                    $appointments->whereAppointmentStatus($filter);
                }
            }
            $appointments = $appointments->paginate($limit);
            $transformedPatient = Transformer::transformAppointments($appointments);
            $meta = Transformer::transformCollection($appointments);
            return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedPatient, $meta);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  Store patient
     * @param
     * @return mixed
     */
    public function addPatient(Request $request)
    {
        $data = [
            'name' => 'required',
            'phone' => 'required|unique:users,phone',
            'email' => 'required|unique:users,email',
            'dob' => 'required',
            'blood_group' => 'required',
            'weight' => 'required',
            'height' => 'required',
            'gender' => 'required',
            'marital_status' => 'required',
        ];

        $validator = Validator::make($request->all(), $data);

        if ($validator->fails()) {
            return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', $validator->errors());
        }
        try {
            $path = "";
            $req = json_decode(file_get_contents("php://input"));
            if ($req->profile_image) {
                $image_parts = explode(";base64,", $req->profile_image);

                $image_type_aux = explode("image/", $image_parts[0]);

                $image_type = $image_type_aux[1];

                $image_base64 = base64_decode($image_parts[1]);
                $path = 'profiles/' . uniqid() . '.' . $image_type;
                Storage::disk('s3')->put($path, $image_base64);
            }
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'user_name' => $request->phone,
                'profile_image' => !empty($path) ? $path : "",
                'user_type' => 'patient',
                'is_active' => 'true',
                'phone' => $request->phone,
                'password' => $request->password ? bcrypt($request->password) : bcrypt('123456'),
            ]);
            $user->assignRole('Patient');
            $token = $user->createToken('token')->accessToken;
            $device_data = [
                'device_key' => 'web',
                'device_type' => 'web',
            ];
            $user->devices()->firstOrCreate($device_data, [
                'access_token' => $token,
            ]);
            PatientProfile::create([
                'user_id' => $user->id,
                'dob' => $request->dob,
                'height' => $request->height,
                'blood_group' => $request->blood_group,
                'weight' => $request->weight,
                'gender' => $request->gender,
                'marital_status' => $request->marital_status,
            ]);
            return $this->apiResponse(JsonResponse::HTTP_OK, 'data', ['id' => $user->id]);
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
    public function updatePatient(Request $request, $id)
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
                    'name' => $request->name ? $request->name : $user->name,
                    'email' => $request->email ? $request->email : $user->email,
                ];
                User::whereId($id)->update($userdata);
                $data = [
                    'dob' => !empty($request->dob) ? $request->dob : $PatientProfile->dob,
                    'blood_group' => !empty($request->blood_group) ? $request->blood_group : $PatientProfile->blood_group,
                    'weight' => !empty($request->weight) ? $request->weight : $PatientProfile->weight,
                    'height' => !empty($request->height) ? $request->height : $PatientProfile->height,
                    'gender' => !empty($request->gender) ? $request->gender : $PatientProfile->gender,
                    'age' => !empty($request->age) ? $request->age : $PatientProfile->age,
                    'address' => !empty($request->address) ? $request->address : $PatientProfile->address,
                    'marital_status' => !empty($request->marital_status) ? $request->marital_status : $PatientProfile->marital_status,
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
     *  delete patient
     * @param Request $request
     * @return mixed
     */
    public function deletePatient($id)
    {
        try {
            $user = User::findOrFail($id);
            if (!empty($user)) {
                $user->update(['is_active' => false]);
                return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'User deleted successfully');
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'User not found');
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
     *  delete symptom
     * @param id
     * @return mixed
     */
    public function deleteSymptom($id)
    {
        try {
            $symptom = Symptom::findOrFail($id);
            if (!empty($symptom)) {
                DB::beginTransaction();
                $symptom->delete();
                DB::commit();
                return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Symptom deleted successfully');
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Symptom not found');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  update Symptom
     * @param Request $request , id
     * @return mixed
     */
    public function updateSymptom(Request $request, $id)
    {
        try {
            $symptom = Symptom::findOrFail($id);
            if (!empty($symptom)) {
                DB::beginTransaction();
                //upload profile pic
                $path = $symptom->icon;
                $req = json_decode(file_get_contents("php://input"));
                if ($req->icon) {
                    if (Storage::disk('s3')->exists($symptom->icon)) {
                        Storage::disk('s3')->delete($symptom->icon);
                    }
                    $image_parts = explode(";base64,", $req->icon);

                    $image_type_aux = explode("image/", $image_parts[0]);

                    $image_type = $image_type_aux[1];

                    $image_base64 = base64_decode($image_parts[1]);
                    $path = 'icon/' . uniqid() . '.' . $image_type;
                    Storage::disk('s3')->put($path, $image_base64);
                }
                $symptom->update(['name_ur' => $request->name_ur, 'name_en' => $request->name_en, 'icon' => $path]);
                DB::commit();

                return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Symptom updated successfully');

            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Symptom not found');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  update Symptom
     * @param Request $request
     * @return mixed
     */
    public function storeSymptom(Request $request)
    {
        $data = [
            'name_en' => 'required',
            'name_ur' => 'required',
        ];
        $request->validate($data, []);
        try {
            $data_symptom = [];
            //upload profile pic

            $path = "icon/no-image.png";
            $req = json_decode(file_get_contents("php://input"));
            if ($req->icon) {
                $image_parts = explode(";base64,", $req->icon);

                $image_type_aux = explode("image/", $image_parts[0]);

                $image_type = $image_type_aux[1];

                $image_base64 = base64_decode($image_parts[1]);
                $path = 'icon/' . uniqid() . '.' . $image_type;
                Storage::disk('s3')->put($path, $image_base64);
            }

            $data_symptom['name_ur'] = $request->name_ur;
            $data_symptom['name_en'] = $request->name_en;
            $data_symptom['icon'] = $path;
            Symptom::create($data_symptom);
            return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Symptom added successfully.');
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
    public function InstantAppointments()
    {
        try {
            DB::beginTransaction();
            $appointment_data = Appointment::where('type', 'instant')->whereHas('doctor')->with(['doctor' => function ($query) {
                $query->select('id', 'user_id');
                $query->with(['user' => function ($sub_query) {
                    $sub_query->select('id', 'profile_image', 'name');
                }, 'videoConsultancy' => function ($sub_query) {
                    $sub_query->select('id', 'fee', 'doctor_profile_id');
                }]);
            }, 'rating'])->orderBy('booking_date', 'asc')->orderby(DB::raw('case when appointment_status= "pending" then 1 when appointment_status= "completed" then 2 when appointment_status= "canceled" then 3 end'))->get();
            if ($appointment_data) {
                $transformedPatient = PatientTransformer::transformPatientAppointments($appointment_data, true);
                return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformedPatient);
            } else {
                return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'Patient profile not found');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }
}
