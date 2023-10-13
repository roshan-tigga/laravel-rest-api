<?php

namespace App\Http\Controllers\API;

use App\Models\Transcript;
use App\Models\Requirement;
use App\Models\UserDocument;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;


class DashboardController extends BaseController
{
    public function getStudentDashboardCount(){
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;

        $totalTranscript = Transcript::where('is_active',1)
        ->where('org_id',$organizationId)
        ->where('user_id',$authId)
        ->where('is_active',1)
        ->count();

        $totalRequirement = Requirement::where('is_active',1)
        ->where('org_id',$organizationId)
        ->where('user_id',$authId)
        ->where('is_active',1)
        ->count();

        $totalDocument = UserDocument::where('is_active',1)
        ->where('user_id',$authId)
        ->where('is_active',1)
        ->count();

        $totalCoursesCompleted = Requirement::where('is_active',1)
        ->where('org_id',$organizationId)
        ->where('user_id',$authId)
        ->where('is_active',1)
        ->where('progress',2)
        ->count();

        return response()->json(['status'=>true,'code'=>200,'data'=>[
            'totalRequirement' => $totalRequirement,
            'totalTranscript' => $totalTranscript,
            'totalDocument' => $totalDocument,
            'totalCoursesCompleted' => $totalCoursesCompleted,
        ]],200);
    }
}
