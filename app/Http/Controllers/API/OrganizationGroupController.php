<?php

namespace App\Http\Controllers\API;

use App\Models\GroupOrganizationSetting;
use App\Models\User;
use App\Models\Location;
use App\Models\JobTitle;
use App\Models\Division;
use App\Models\Area;
use App\Models\Organization;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;
use Carbon\Carbon;

class OrganizationGroupController extends BaseController
{
    public function getOrganizationGroupList(Request $request){ 

        $organizationId = Auth::user()->org_id;
        $groups = DB::table('lms_group_settings as groupSettings')
        ->where('groupSettings.is_active','1')
        ->orderBy('groupSettings.order','ASC')
        ->select('groupSettings.group_setting_id as groupSettingId','groupSettings.group_setting_name as groupSettingName')
        ->get();
        if($groups->count() > 0){
            foreach($groups as $group){
                $orgSettings = DB::table('lms_group_org_settings')->where('is_active','=','1')->where('org_id',$organizationId)->where('group_setting_id',$group->groupSettingId);
                if($orgSettings->count() > 0){
                    $group->isActive = $orgSettings->first()->is_active;
                }else{
                    $group->isActive = 0;
                }
            }
        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$groups],200);
    }


    public function addOrganizationGroup(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'groups' => 'required|array',
            'groups.*.groupId' => 'required|integer',
            'groups.*.isChecked' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $modifyUsers = $request->modifyUsers;

        if(!empty($request->groups)){
            foreach($request->groups as $group){
                $groupId = $group['groupId'];
                $isChecked =($group['isChecked'] == 1) ? 1 : 0;

                $groupOrganizationSetting = GroupOrganizationSetting::where('group_setting_id',$groupId)->where('org_id',$organizationId);
                if($groupOrganizationSetting->count() > 0){

                    $groupArray = [
                        'is_active' => $isChecked,
                        'date_modified' => Carbon::now(),
                        'modified_id' => $authId
                    ];
                    $groupOrganizationSetting->update($groupArray);

                    if($isChecked == 1){
                        $users = User::where('org_id',$organizationId)->where('is_active','=','1');
                        if($users->count() > 0){
                            foreach($users->get() as $user){
                                SELF::organizationGroup($user->user_id,$groupId,$organizationId);
                            }
                        }
                    }

                }else{

                    $group = GroupOrganizationSetting::where('org_id',$organizationId)->orderBy('org_code','DESC');
                    if($group->count() > 0){
                        $orgCode = $group->first()->org_code + 1;
                    }else{
                        $orgCode = $organizationId.'000000001';
                    }

                    $groupOrganizationSetting = new GroupOrganizationSetting;
                    $groupOrganizationSetting->group_setting_id = $groupId;
                    $groupOrganizationSetting->org_code = $orgCode;
                    $groupOrganizationSetting->org_id = $organizationId;
                    $groupOrganizationSetting->is_active = $isChecked;
                    $groupOrganizationSetting->created_id = $authId;
                    $groupOrganizationSetting->modified_id = $authId;
                    $groupOrganizationSetting->save();

                    //if($modifyUsers == 1){
                        $users = User::where('org_id',$organizationId)->where('is_active','=','1');
                        if($users->count() > 0){
                            foreach($users->get() as $user){
                                SELF::organizationGroup($user->user_id,$groupId,$organizationId);
                            }
                        }
                    //}
                }
            }
        }

        return response()->json(['status'=>true,'code'=>200,'message'=>'Organization Group has been submitted successfully.'],200);
    }

    public function organizationGroup($userId,$groupSettingId,$organizationId){
        $authId = Auth::user()->user_id;
        if($groupSettingId == 1 || $groupSettingId == 2 || $groupSettingId == 3){
            $jobTitles = JobTitle::where('org_id',$organizationId)->where('is_active','=','1');
            if($jobTitles->count() > 0){
                foreach($jobTitles->get() as $jobTitle){
                    jobTitleGroup($jobTitle->job_title_id,$userId,$organizationId,1,'add','');
                }
            }
        }
        if($groupSettingId == 4){
            $organizations = Organization::where('org_id',$organizationId)->where('is_active','=','1');
            if($organizations->count() > 0){
                foreach($organizations->get() as $organization){
                    companyGroup($userId,$organizationId,1,'add','');
                }
            }
        }
        if($groupSettingId == 5){
            $divisions = Division::where('org_id',$organizationId)->where('is_active','=','1');
            if($divisions->count() > 0){
                foreach($divisions->get() as $division){
                    divisionGroup($division->division_id,$userId,$organizationId,1,'add','');
                }
            }
        }
        if($groupSettingId == 6){
            $areas = Area::where('org_id',$organizationId)->where('is_active','=','1');
            if($areas->count() > 0){
                foreach($areas->get() as $area){
                    areaGroup($area->area_id,$userId,$organizationId,1,'add','');
                }
            }
        }
        if($groupSettingId == 7){
            $locations = Location::where('org_id',$organizationId)->where('is_active','=','1');
            if($locations->count() > 0){
                foreach($locations->get() as $location){
                    locationGroup($location->location_id,$userId,$organizationId,1,'add','');
                }
            }
        }
    }

}
