<?php

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::post('login', 'Api\AuthController@login');
Route::post('forgot/password', 'Api\AuthController@forgotPassword');
Route::post('signup', 'Api\AuthController@register');
Route::post('checkPhone', 'Api\AuthController@checkPhone');
Route::group(['middleware' => ['auth:api']], function () {
    Route::delete('logout', 'Api\AuthController@logout');
});

// patient routes
Route::group(['prefix' => 'patient','middleware' => ['cors']], function () {
    Route::get('doctors', 'Api\HomeController@doctors');
    Route::get('doctor/{id}', 'Api\HomeController@getDoctor');
    Route::get('doctor/{id}/slots/video', 'Api\HomeController@doctorVideoSlots');
    Route::get('doctor/{id}/slots/video/detail', 'Api\HomeController@doctorSlotsDetails');
    Route::get('call/{id}', 'Api\HomeController@SendCallLink')->name('patcall');
    Route::post('call/sync/{channel_name}', 'Api\HomeController@callSync');
});

// Authenticated Patient routes
Route::group(['middleware' => ['auth:api', 'cors'], 'prefix' => 'patient'], function () {
    Route::post('doctor/{id}/slots/video/detail/book', 'Api\Patient\PatientController@doctorSlotBooking');
    Route::get('{id}/profile', 'Api\Patient\PatientController@profile');
    Route::post('{id}/profile/update', 'Api\Patient\PatientController@updateProfile');
    Route::get('{id}/appointments/video', 'Api\Patient\PatientController@patientAppointments');
    Route::get('{id}/appointments/pending', 'Api\Patient\PatientController@pendingAppointments');
    Route::get('{id}/instant/appointments/video', 'Api\Patient\PatientController@patientInstantAppointments');
    Route::get('{id}/instant/appointments/pending', 'Api\Patient\PatientController@pendingInstantAppointments');
    Route::post('{id}/appointments/{appointment_id}/records/add', 'Api\Patient\PatientController@addRecordToAppointment');
    Route::get('{id}/appointments/{appointment_id}/records', 'Api\Patient\PatientController@getAppointmentRecords');
    Route::post('{id}/appointments/{appointment_id}/cancel', 'Api\Patient\PatientController@cancelAppointment');
    Route::post('{id}/appointments/{appointment_id}/call', 'Api\Patient\PatientController@call');
    Route::post('{id}/appointments/{appointment_id}/rate', 'Api\Patient\PatientController@callRating');
    Route::get('{id}/relatives', 'Api\Patient\PatientController@getRelatives');

    //instant doctor appointment
    Route::post('instant', 'Api\Patient\PatientController@instantAppointments');
    Route::get('{patient_id}/doctors', 'Api\Patient\PatientController@getDoctors');
    Route::get('medicalRecords', 'Api\Patient\PatientController@getMedicalRecords');
    Route::post('instant/cancel/{appointment_id}', 'Api\Patient\PatientController@cancelInstantAppointment');
//    Route::post('instant/complete/{appointment_id}', 'Api\Patient\PatientController@completeInstantAppointment');
    Route::get('symptom/get', 'Api\Patient\PatientController@getSymptoms');
    Route::post('add/record', 'Api\Patient\PatientController@addRecord');
    Route::delete('record/delete/{id}', 'Api\Patient\PatientController@deleteRecord');
    Route::delete('prescription/delete/{id}', 'Api\Patient\PatientController@deletePrescription');

});

Route::group(['middleware' => ['auth:api', 'cors'], 'prefix' => 'doctor'], function () {
    Route::get('dashboard', 'Api\Doctor\DoctorController@dashboardCounts');
    Route::post('{id}/appointments', 'Api\Doctor\DoctorController@doctorAppointments');
    Route::post('{id}/instant/appointments', 'Api\Doctor\DoctorController@doctorInstantAppointments');
    Route::get('patient/{id}', 'Api\Doctor\DoctorController@patientProfile');
    Route::get('profile', 'Api\Doctor\DoctorController@profile');
    Route::post('profile/update', 'Api\Doctor\DoctorController@updateProfile');
    Route::post('{id}/appointments/patients', 'Api\Doctor\DoctorController@patientsAppointments');
    Route::post('{id}/appointment/{appointment_id}/status', 'Api\Doctor\DoctorController@updateAppointmentStatus');
    Route::post('{id}/appointment/{appointment_id}/call', 'Api\Doctor\DoctorController@doctorCall');
    Route::post('{id}/appointment/{relativesappointment_id}/upload/prescription', 'Api\Doctor\DoctorController@uploadPrescription');
    Route::delete('prescription/delete', 'Api\Doctor\DoctorController@deletePrescription');
    Route::get('specialities', 'Api\Doctor\DoctorController@getSpecialities');
    Route::post('instant/cancel/{appointment_id}', 'Api\Doctor\DoctorController@cancelInstantAppointment');
    Route::post('instant/complete/{appointment_id}', 'Api\Doctor\DoctorController@completeInstantAppointment');
});


Route::group(['middleware' => ['auth:api', 'cors'],], function () {
//    Route::post('sendNotification', 'Api\Doctor\DoctorController@sendCloudMessage');
    Route::post('add/token', 'Api\NotificationController@userToken');
    Route::get('send/notification/{user_id}', 'Api\NotificationController@sendCloudMessage');

});

// admin  routes
Route::group(['middleware' => ['auth:api', 'cors'], 'prefix' => 'admin'], function () {
    Route::group(['prefix' => 'doctors'], function () {
        Route::post('store', 'Api\Admin\AdminController@storeDoctor');
        Route::get('/', 'Api\Admin\AdminController@doctors');
        Route::get('edit/{id}', 'Api\Admin\AdminController@editDoctor');
        Route::post('update/{id}', 'Api\Admin\AdminController@updateDoctor');
        Route::delete('delete/{id}', 'Api\Admin\AdminController@deleteDoctor');
    });

    Route::group(['prefix' => 'symptom'], function () {
        Route::post('store', 'Api\Admin\AdminController@storeSymptom');
        Route::get('get', 'Api\Admin\AdminController@getSymptoms');
        Route::post('update/{id}', 'Api\Admin\AdminController@updateSymptom');
        Route::delete('delete/{id}', 'Api\Admin\AdminController@deleteSymptom');
    });

    Route::post('speciality/store', 'Api\Admin\AdminController@storeSpeciality');
    Route::get('speciality', 'Api\Admin\AdminController@getSpecialities');
    Route::delete('speciality/delete/{id}', 'Api\Admin\AdminController@deleteSpeciality');
    Route::post('services/store', 'Api\Admin\AdminController@storeServices');
    Route::post('speciality/update/{id}', 'Api\Admin\AdminController@updateSpeciality');
    Route::get('services', 'Api\Admin\AdminController@getServices');
    Route::get('dashboard', 'Api\Admin\AdminController@dashboardCounts');
    // get all registered patients
    Route::get('patients', 'Api\Admin\AdminController@getPatients');
    Route::get('appointments', 'Api\Admin\AdminController@getAppointments');
    Route::get('instant/appointments', 'Api\Admin\AdminController@InstantAppointments');
    Route::post('add/patient', 'Api\Admin\AdminController@addPatient');
    Route::post('update/patient/{id}', 'Api\Admin\AdminController@updatePatient');
    Route::delete('delete/patient/{id}', 'Api\Admin\AdminController@deletePatient');
});

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
