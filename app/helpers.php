<?php

use App\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


function getAvatar($user)
{
    if (!empty($user->is_social_login)) {
        $avatar = $user->avatar;
    } else {
        $avatar = empty($user->avatar) ? '' : Storage::url($user->avatar);
    }
    return empty($avatar) ? url('img/no_avatar.png') : $avatar;
}

/**
 * @param $file
 * @param $directory
 * @param $width
 * @return string
 * save resize image in storage
 */
function s($file, $directory, $width)
{
    if (!Storage::exists($directory)) {
        Storage::makeDirectory("$directory");
    }
    $filename = Str::random() . time() . '.' . $file->getClientOriginalExtension();
    $path = "$directory/$filename";
    \Image::make($file)->resize($width, null, function ($constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    })->save("storage/$path");
    return $path;
}

/**
 * @param $file
 * delete a file
 */
function deleteFile($file)
{
    if (!empty($file)) {
        $host = str_replace('www.', '', request()->getHttpHost());
        $scheme = request()->getScheme();
        $needles = [
            "{$scheme}://www.{$host}",

            "{$scheme}://{$host}"
        ];
        $file = str_replace($needles, '', $file);
        if ((file_exists(public_path($file)) || Storage::exists($file))) {
            file_exists(public_path($file)) ? unlink(public_path($file)) : Storage::delete($file);
        }
    }
}
