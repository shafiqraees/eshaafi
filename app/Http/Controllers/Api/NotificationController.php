<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserToken;
use App\Traits\Transformer;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;

class NotificationController extends Controller
{

    public function sendCloudMessage($id)
    {
        try {
            DB::beginTransaction();
            $messaging = app('firebase.messaging');
            $user = User::whereId($id)->first();
            $devices = $user->userToken()->pluck('token')
                ->reject(function ($it) {
                    return is_numeric($it);
                })
                ->toArray();
            $message = CloudMessage::withTarget('token', $devices[0])
                ->withData(['test' => 1, 'priority' => 'high', 'is_rejected' => true, 'current_time' => date('Y/m/d H:i:s')]) // optional
            ;
            $messaging->send($message);
            DB::commit();
            return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Notification sent');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }

    public function userToken(Request $request) {
        $data = [
            'token' => 'required',
        ];
        $validator = Validator::make($request->all(), $data);

        if ($validator->fails()) {
            return $this->apiResponse(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'message', $validator->errors());
        }
        try {
            DB::beginTransaction();
            $user = Auth::user();
            $user_token = UserToken::whereUserId($user->id)->first();
            if($user_token) {
                $user_token->update(['token' => $request->token]);
            } else {
                UserToken::create(['user_id' => $user->id, 'token' => $request->token]);
            }
            DB::commit();
            return $this->apiResponse(JsonResponse::HTTP_OK, 'message', 'Record added');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->apiResponse(JsonResponse::HTTP_INTERNAL_SERVER_ERROR, 'message', $e->getMessage());
        }
    }



}
