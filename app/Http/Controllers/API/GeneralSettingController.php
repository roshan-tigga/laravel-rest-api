<?php

namespace App\Http\Controllers\API;

use App\Models\GeneralSetting;
use App\Models\OrganizationGeneralSetting;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;
use Carbon\Carbon;

class GeneralSettingController extends BaseController
{
    public function getGeneralSettingList(Request $request){

        $organizationId = Auth::user()->org_id;

        $sort = $request->has('sort') ? $request->get('sort') : 'general_setting_id';
        $order = $request->has('order') ? $request->get('order') : 'ASC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;

        $generalSettings = GeneralSetting::where('is_active','1')
        ->orderBy($sortColumn,$order)
        ->select('general_setting_id as generalSettingId', 'general_setting_name as generalSettingName')
        ->get();
        if($generalSettings->count() > 0){
            foreach($generalSettings as $generalSetting){
                $organizationGeneralSetting = OrganizationGeneralSetting::where('general_setting_id',$generalSetting->generalSettingId)->where('org_id',$organizationId);
                if($organizationGeneralSetting->count() > 0){
                    $generalSetting->isChecked = $organizationGeneralSetting->first()->is_active;
                }else{
                    $generalSetting->isChecked =  0;
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$generalSettings],200);
    }


    public function addGeneralSetting(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        
        $validator = Validator::make($request->all(), [
            'generalSettings' => 'required|array',
            'generalSettings.*.generalSettingId' => 'required|integer',
            'generalSettings.*.isChecked' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        if(!empty($request->generalSettings)){
            foreach($request->generalSettings as $generalSetting){
                $generalSettingId = $generalSetting['generalSettingId'];
                $isChecked = ($generalSetting['isChecked'] != '') ? $generalSetting['isChecked'] : 0;

                $organizationGeneralSetting = OrganizationGeneralSetting::where('general_setting_id',$generalSettingId)->where('org_id',$organizationId);
                if($organizationGeneralSetting->count() > 0){
                    $organizationGeneralSetting->update([
                        'is_active' => $isChecked,
                        'modified_id' => $authId
                    ]);
                }else{
                    $organizationGeneralSetting = new OrganizationGeneralSetting;
                    $organizationGeneralSetting->general_setting_id = $generalSettingId;
                    $organizationGeneralSetting->is_active = $isChecked;
                    $organizationGeneralSetting->org_id = $organizationId;
                    $organizationGeneralSetting->created_id = $authId;
                    $organizationGeneralSetting->modified_id = $authId;
                    $organizationGeneralSetting->save();
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'General setting added successfully.'],200);
    }
}
