<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    public function apiResponse($status_code, $key, $value, $meta = '')
    {
        $response = [];
        $response['status_code'] = $status_code;
        if ($status_code == 422 && gettype($value) == 'object' && get_class($value) == 'Illuminate\Support\MessageBag') {
            $errors = [];
            foreach ($value->toArray() as $attr => $value_errors) {
                $errors[$attr] = $value_errors[0];
            }
            $response['errors'] = $errors;
        } else {
            $response[$key] = $value;
        }
        if (!empty($meta)) {
            $response = array_merge($response, $meta);
        }
        return response()->json($response)->setStatusCode($status_code);
    }
}
