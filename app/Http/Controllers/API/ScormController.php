<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\ScoMenisfestReader;
use App\Models\ScoDetails;
use App\Models\OrganizationScoTrack; 
use Auth;
use App\Http\Controllers\API\BaseController as BaseController;

class ScormController extends BaseController
{
    public function getScormTrackingById($scormId){
        $authId = Auth::user()->user_id;
        $scoTrack = OrganizationScoTrack::where('scorm_id',$scormId)->where('user_id',$authId)->orderBy('id','DESC')->select('scorm_id as scormId','element','value as suspendData','completion_status as completionStatus')->first();
        return response()->json(['status'=>true,'code'=>200,'data'=>$scoTrack],200);
    }
    public function scormTracking(Request $request){
        $authId = Auth::user()->user_id;
        $scoTrack = new OrganizationScoTrack;
        $scoTrack->user_id = $authId;
        $scoTrack->scorm_id = $request->scormId;
        $scoTrack->sco_id = $request->scoId;
        $scoTrack->attempt = 1;
        $scoTrack->element = $request->name;
        $scoTrack->value = $request->suspendData;
        $scoTrack->completion_status = $request->completionStatus;
        $scoTrack->created_id = $authId;
        $scoTrack->modified_id = $authId;
        $scoTrack->save();
        // $scoTrack = OrganizationScoTrack::where('scorm_id',$request->scormId)->where('user_id',$authId);
        // if($scoTrack->count() > 0){
        //     $scoTrack->update([
        //         'completion_status' => $request->completionStatus,
        //         'element' => $request->name,
        //         'value' => $request->suspendData,
        //         'modified_id' => $authId
        //     ]);
        // }else{
        //     $scoTrack = new OrganizationScoTrack;
        //     $scoTrack->user_id = $authId;
        //     $scoTrack->scorm_id = $request->scormId;
        //     $scoTrack->sco_id = $request->scoId;
        //     $scoTrack->attempt = 1;
        //     $scoTrack->element = $request->name;
        //     $scoTrack->value = $request->suspendData;
        //     $scoTrack->completion_status = $request->completionStatus;
        //     $scoTrack->created_id = $authId;
        //     $scoTrack->modified_id = $authId;
        //     $scoTrack->save();
        // }
        return response()->json(['status'=>true,'code'=>200,'message'=>'Tracking created successfully.'],200);
    }
}
