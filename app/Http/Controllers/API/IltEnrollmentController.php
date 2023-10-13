<?php

namespace App\Http\Controllers\API;

use App\Models\IltEnrollment;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;

class IltEnrollmentController extends BaseController
{
    public function getIltEnrollmentList(){
        $iltEnrollments = IltEnrollment::select('ilt_enrollment_id as iltEnrollmentId','enrollment_type as enrollmentType')->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$iltEnrollments],200);
    }
}
