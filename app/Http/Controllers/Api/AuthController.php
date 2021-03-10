<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PatientProfile;
use App\User;
use App\Rules\CheckOldPassword;
use App\Traits\Transformer;
use App\UserDevice;
use Carbon\Carbon;
use Auth;
use Crypt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     *  Login user and add device to current user devices
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        if ($request->user_type == 'patient') {
            $data = [
                'phone' => 'required',
                'device_key' => 'required',
                'device_type' => 'required',
            ];

        }
        if ($request->user_type == 'doctor' || $request->user_type == 'admin' || $request->user_type == 'user') {
            $data = [
                'user_name' => 'required',
                'password' => 'required',
                'device_key' => 'required'
            ];
            if ($request->device_type == 'web') {
                $data['device_key'] = 'nullable';
            }
        }
        $validator = Validator::make($request->all(), $data);

        if ($validator->fails()) {
            return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', $validator->errors());
        }

        try {
            DB::beginTransaction();
            if ($request->user_type == 'admin' || $request->user_type == 'doctor' || $request->user_type == 'user') {
                $credentials = [
                    'user_name' => $request->user_name, 'password' => $request->password,
                    'is_active' => 'true'
                ];
                if (auth()->attempt($credentials)) {
                    $user = User::where('user_name', $request->user_name)->whereUserType($request->user_type)->first();
                    if (empty($user)) {
                        return $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'User name or password is incorrect For the doctor');
                    }
                    $devices = $user->devices()->where('device_type', $request->device_type)->get();
                    $token = $user->createToken('token')->accessToken;
                    if ($devices->count() > 0) {
                        foreach ($devices as $device) {
                            if ($device->device_key === $request->device_key && $device->device_type === $request->device_type) {
                                $device->update(['access_token' => $token]);
                            } else {
//                                if($user->is_logged_in === 'true') {
//                                    return $this->apiResponse(JsonResponse::HTTP_UNAUTHORIZED, 'message', 'User already logged in');
//                                } else {
                                if ($device->device_type === 'web') {
                                    $device->update([
                                        'access_token' => $token,
                                        'device_key' => trim(preg_replace('/\s\s+/', ' ', $request->device_key)),
                                    ]);
                                } else {
                                    $device->update([
                                        'device_type' => $request->device_type,
                                        'access_token' => $token,
                                        'device_key' => trim(preg_replace('/\s\s+/', ' ', $request->device_key)),
                                    ]);
                                }
//                                }
                            }
                        }

                    } else {
                        UserDevice::create([
                            'user_id' => $user->id,
                            'access_token' => $token,
                            'device_key' => trim(preg_replace('/\s\s+/', ' ', $request->device_key)),
                            'device_type' => $request->device_type,
                        ]);
                    }
                    $user->update(['is_logged_in' => 'true']);
                    $user_data = clone $user;
                    if ($user->user_type === 'doctor') {
                        $user_data = $user_data->whereId($user->id)->with(['profile'])->first();
                    }
                    $transformed_user = Transformer::transformUser($user_data, $user->createToken('token')->accessToken, $request->device_key, true);

                    $response = $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformed_user);
                    DB::commit();
                } else {
                    $response = $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'User name or password is incorrect');
                }
                return $response;
            }
            if ($request->user_type == 'patient') {
                $user = User::where('phone', $request->phone)->whereUserType($request->user_type)->first();
                if (!empty($user)) {
//                    $token = $user->createToken('token')->accessToken;
//                    if ($request->device_type == 'web') {
//                        $device = $user->devices()->firstOrCreate([
//                            'device_key' => 'web',
//                            'device_type' => $request->device_type
//                        ], [
//                            'access_token' => $token,
//                        ]);
//                    } else {
//                        $device = $user->devices()->firstOrCreate([
//                            'device_key' => $request->device_key,
//                            'device_type' => $request->device_type,
//                        ], [
//                            'access_token' => $token,
//                        ]);
//                    }

                    $devices = $user->devices()->where('device_type', $request->device_type)->get();
                    $token = $user->createToken('token')->accessToken;
                    if ($devices->count() > 0) {
                        foreach ($devices as $device) {
                            if ($device->device_key === $request->device_key && $device->device_type === $request->device_type) {
                                $device->update(['access_token' => $token]);
                            } else {
//                                if($user->is_logged_in === 'true') {
//                                    return $this->apiResponse(JsonResponse::HTTP_UNAUTHORIZED, 'message', 'User already logged in');
//                                } else {
                                if ($device->device_type === 'web') {
                                    $device->update([
                                        'access_token' => $token,
                                        'device_key' => trim(preg_replace('/\s\s+/', ' ', $request->device_key)),
                                    ]);
                                } else {
                                    $device->update([
                                        'device_type' => $request->device_type,
                                        'access_token' => $token,
                                        'device_key' => trim(preg_replace('/\s\s+/', ' ', $request->device_key)),
                                    ]);
                                }
                            }
//                            }
                        }

                    } else {
                        UserDevice::create([
                            'user_id' => $user->id,
                            'access_token' => $token,
                            'device_key' => trim(preg_replace('/\s\s+/', ' ', $request->device_key)),
                            'device_type' => $request->device_type,
                        ]);
                    }
                    $user->update(['is_logged_in' => 'true']);
                    $user_data = clone $user;
                    $transformed_user = Transformer::transformUser($user_data, $token, $request->device_key, true);
                    $response = $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformed_user);
                    DB::commit();
                } else {
                    $response = $this->apiResponse(JsonResponse::HTTP_NOT_FOUND, 'message', 'User not found!');
                }
                return $response;
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  Logout current user and delete device from user devices
     * @return JsonResponse
     */
    public function logout()
    {
        if (Auth::check()) {
            Auth::user()->update(['is_logged_in' => 'false']);
            Auth::user()->AauthAcessToken()->delete();
            $response = $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'User logout successfully');
        } else {
            $response = $this->apiResponse(JsonResponse::HTTP_UNAUTHORIZED, 'message', 'Unauthorizeccd access.');
        }
        return $response;
    }

    /**
     *  Register new user
     * @param Request $request
     * @return mixed
     */
    public function register(Request $request)
    {
        $rules = [
            'name' => 'required',
            'profile_image' => 'nullable',
            'user_type' => 'required',
            'device_key' => 'required',
            'device_type' => 'required',
        ];
        if ($request->user_type == 'doctor') {
            $rules['phone'] = 'nullable';
            $rules['password'] = ['required', 'min:6'];
            $rules['user_name'] = 'nullable'; // auto generate
            $rules['email'] = 'required|unique:users,email';
        }

        if ($request->user_type == 'admin') {
            $rules['phone'] = 'required|unique:users,phone';
            $rules['password'] = ['required', 'min:6'];
            $rules['user_name'] = 'required|unique:users,user_name';
            $rules['email'] = 'required|unique:users,email';
        }
        if ($request->user_type == 'patient') {
            $rules['phone'] = 'required|unique:users,phone';
            $rules['password'] = 'nullable';
            $rules['user_name'] = 'nullable'; // Auto generate
            $rules['email'] = 'nullable'; // Auto generate
        }
        if ($request->device_type == 'web') {
            $data['device_key'] = 'nullable';
        }

        $validator = Validator::make(request()->all(), $rules);
        if ($validator->fails()) {
            return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', $validator->errors());
        }

        $data = [
            'name' => $request->name,
            'user_type' => $request->user_type,
        ];
        if ($request->user_type == 'doctor') {
            $data['password'] = empty($request->password) ? '' : bcrypt($request->password);
            // explode name from email
            $name = explode("@", $request->email);
            // generate username
            $user_name = $name[0] . '@eshaafi.com';
            $data['user_name'] = $user_name; // auto generated
            $data['email'] = $request->email;
            $role = 'Doctor';
        }

        if ($request->user_type == 'admin') {
            $data['phone'] = $request->phone;
            $data['password'] = empty($request->password) ? '' : bcrypt($request->password);
            $data['user_name'] = $request->user_name;
            $data['email'] = $request->email;
            $role = 'Admin';

        }
        if ($request->user_type == 'patient') {
            $phone = $request->phone;
            $substr = substr($request->phone, 0, 4);
            if ($substr == "+920") {
                $phone = str_replace('+920', '+92', $request->phone);
            }
            $data['phone'] = $phone;
            $data['password'] = $request->password ? bcrypt($request->password) : bcrypt('123456');
            $data['user_name'] = $request->phone; // Auto generate
            $data['email'] = $request->email ? $request->email : '';
            $role = 'Patient';
        }
        $user = User::Create($data);
        $user->syncRoles([$role]);
        $token = $user->createToken('token')->accessToken;
        if ($request->device_type == 'web') {
            $device_data = [
                'device_key' => 'web',
                'device_type' => $request->device_type,
            ];
        } else {
            $device_data = [
                'device_key' => trim(preg_replace('/\s\s+/', ' ', $request->device_key)),
                'device_type' => $request->device_type,
            ];
        }
        $user->devices()->firstOrCreate($device_data, [
            'access_token' => $token,
        ]);
        if ($request->user_type == 'patient') {
            PatientProfile::create([
                'user_id' => $user->id,
            ]);
        }
        $transformed_user = Transformer::transformUser($user, $token, true);
        return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformed_user);
    }

    /**
     * Update password of current user
     * @return JsonResponse
     */
    public function updatePassword()
    {
        $validator = Validator::make(request()->all(), [
            'current_password' => ['required', new CheckOldPassword()],
            'password' => 'required|confirmed',
            'password_confirmation' => 'required'
        ]);
        if ($validator->fails()) {
            return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', $validator->errors());
        }
        try {
            DB::beginTransaction();
            $token = Hash::make(request('password'));
            $device = auth()->guard('token')->getTokenWithUser();
            $user = $device->user;
            $device->update(['access_token' => $token]);

            $user->update(['password' => bcrypt(request('password'))]);
            $user->devices()->where('id', '!=', $device->id)->delete();
            DB::commit();

            $transformed_user = Transformer::transformUser($user, $token, true);
            return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $transformed_user);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    /**
     *  Send forgot password email to user
     * @return JsonResponse
     */
    public function forgotPassword()
    {
        $validator = Validator::make(request()->all(), [
            'email' => 'required|email|exists:users,email',
        ]);
        if ($validator->fails()) {
            return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', $validator->errors());
        }

        return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'A recovery email has been sent on your email.');
    }

    /**
     *  Delete current user account
     * @return JsonResponse
     */
    public function deleteAccount()
    {
        $user = auth()->guard('token')->user();
        $avatar = $user->avatar;
        $user->delete();
        if (Storage::disk('public')->exists($avatar)) {
            Storage::disk('public')->delete($avatar);
        }
        return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Account deleted successfully.');
    }

    /**
     *  Delete current user account
     * @return JsonResponse
     */
    public function checkPhone(Request $request)
    {
        if ($request->user_type == 'patient') {
            $data = [
                'phone' => 'required',
                'device_key' => 'required',
                'user_type' => 'required',
                'device_type' => 'required',
            ];
            if ($request->device_type == 'web') {
                $data['device_key'] = 'nullable';
            }
        }
        $validator = Validator::make($request->all(), $data);

        if ($validator->fails()) {
            return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', $validator->errors());
        }

        try {
            DB::beginTransaction();

            if ($request->user_type == 'patient') {
                $user = User::where('phone', $request->phone)->whereUserType($request->user_type)->first();
                if (!empty($user)) {
                    if ($request->device_type == 'web') {
                        $userData = [
                            'is_exists' => true,
                        ];
                        $response = $this->apiResponse(JsonResponse::HTTP_OK, 'data', $userData);
                    } else {
                        $device = $user->devices()->whereDeviceKey($request->device_key)->first();
                        if (empty($device)) {
                            $userData = [
                                'is_number_exists' => true,
                                'is_device_exists' => false,
                            ];
                            $response = $this->apiResponse(JsonResponse::HTTP_OK, 'data', $userData);
                        } else {
                            $userData = [
                                'is_number_exists' => true,
                                'is_device_exists' => true,
                            ];
                            $response = $this->apiResponse(JsonResponse::HTTP_OK, 'data', $userData);
                        }
                    }


                } else {
                    if ($request->device_type == 'web') {
                        $userData = [
                            'is_exists' => false,
                        ];
                        return $this->apiResponse(JsonResponse::HTTP_OK, 'data', $userData);
                    }
                    $response = $this->apiResponse(JsonResponse::HTTP_OK, 'data', ['is_number_exists' => false, 'is_device_exists' => false]);
                }
                return $response;
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }
}
