<?php
use App\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\DoctorProfile;
use App\Models\PatientProfile;
use App\Models\Appointment;
use App\Models\DoctorAward;
use App\Models\DoctorEducation;
use App\Models\DoctorExperiance;
use App\Models\Faq;
use App\Models\DoctorHospitalService;
use App\Models\DoctorHospital;
use App\Models\DoctorHospitalDay;
use App\Models\DoctorHospitalVocation;
use App\Models\DoctorService;
use App\Models\DoctorSpeciality;
use App\Models\Service;
use App\Models\Hospital;
use App\Models\DoctorVideoConsultancyDay;
use App\Models\DoctorVideoConsultancy;
use App\Models\Language;
use App\Models\Membership;
use App\Models\Speciality;
use App\Models\Prescription;
use App\Models\MedicalRecord;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->truncateTables();
        $this->call(RolesPermissionsTablesSeeder::class);
        $this->call(UsersTableSeeder::class);
//        $this->call(ServiceSeeder::class);
//        $this->call(SpecialitySeeder::class);
//        $this->call(DoctorSeeder::class);
//        $this->call(PatientProfileSeeder::class);
//        $this->call(AppointmentSeeder::class);
//        $this->call(DoctorFaqsSeeder::class);
//        $this->call(DoctorAwardSeeder::class);
//        $this->call(DoctorEducationSeeder::class);
//        $this->call(DoctorExperienceSeeder::class);
//        $this->call(HospitalSeeder::class);
//        $this->call(LanguageSeeder::class);
//        $this->call(MemberShipSeeder::class);
//        $this->call(DoctorSpecialitySeeder::class);
//        $this->call(DoctorHospitalSeeder::class);
//        $this->call(DoctorHospitalDaySeeder::class);
//        $this->call(DoctorServiceSeeder::class);
//        $this->call(DoctorHospitalServiceSeeder::class);
//        $this->call(DoctorHospitalVocationSeeder::class);
//        $this->call(DoctorVideoConsultancySeeder::class);
//        $this->call(DoctorVideoConsultancyDaySeeder::class);
//        $this->call(PriscriptionSeeder::class);
//        $this->call(MedicalRecordSeeder::class);

    }

    public function truncateTables()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        User::truncate();
        Role::truncate();
        Service::truncate();
        Speciality::truncate();
        DoctorProfile::truncate();
        PatientProfile::truncate();
        Appointment::truncate();
        Faq::truncate();
        DoctorAward::truncate();
        DoctorEducation::truncate();
        DoctorExperiance::truncate();
        Hospital::truncate();
        Language::truncate();
        Membership::truncate();
        DoctorSpeciality::truncate();
        DoctorHospital::truncate();
        DoctorService::truncate();
        DoctorHospitalService::truncate();
        DoctorHospitalDay::truncate();
        DoctorHospitalVocation::truncate();
        DoctorVideoConsultancy::truncate();
        Prescription::truncate();
        MedicalRecord::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
