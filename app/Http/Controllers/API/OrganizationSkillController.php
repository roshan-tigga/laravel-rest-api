<?php

namespace App\Http\Controllers\API;

use App\Models\OrganizationSkill;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController; 
use Auth;
use Illuminate\Support\Facades\Redis;

class OrganizationSkillController extends BaseController
{
    public function getOrganizationSkillList(Request $request)
    {
        $organizationId = Auth::user()->org_id;

        $sort = $request->has('sort') ? $request->get('sort') : 'org_skill_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if($sort == 'skillName'){
            $sortColumn = 'skill_name';
        }elseif($sort == 'description'){
            $sortColumn = 'description';
        }elseif($sort == 'isActive'){
            $sortColumn = 'is_active';
        }

        $organizationSkill = OrganizationSkill::where('is_active','!=','0')
        ->where(function($query) use ($search){
            if($search != ''){
                $query->orWhere('org_skill_name', 'LIKE', '%'.$search.'%');
                if(in_array($search,['active','act','acti','activ'])){
                    $query->orWhere('is_active','1');
                }
                if(in_array($search,['inactive','inact','inacti','inactiv'])){
                    $query->orWhere('is_active','2');
                }
            }
        })
        ->where('org_id',$organizationId)
        ->orderBy($sortColumn,$order)
        ->select('org_skill_id as skillId', 'org_skill_name as skillName', 'description', 'is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$organizationSkill],200);
    }

    public function addOrganizationSkill(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'skillName' => 'max:45',
            'description' => 'max:512',
            'isActive' => 'integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try {

            $organizationSkill = new OrganizationSkill;
            $organizationSkill->org_skill_name = $request->skillName;
            $organizationSkill->description = $request->description;
            $organizationSkill->org_id = $organizationId;
            $organizationSkill->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $organizationSkill->created_id = $authId;
            $organizationSkill->modified_id = $authId;
            $organizationSkill->save();

            return response()->json(['status'=>true,'code'=>200,'message'=>'Skill has been created successfully.'],200);

        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function getOrganizationSkillById($skillId)
    {
        $organizationId = Auth::user()->org_id;

        try {
            $organizationSkillRedis = Redis::get('organizationSkillRedis' . $skillId);
            if(isset($organizationSkillRedis)){
                return response()->json(['status'=>true,'code'=>200,'data'=>json_decode($organizationSkillRedis,false)],200);
            }else{
                $organizationSkill = OrganizationSkill::where('is_active','!=','0')->where('org_id',$organizationId)->where('org_skill_id',$skillId);
                if ($organizationSkill->count() < 1) {
                    return response()->json(['status'=>false,'code'=>404,'error'=>'Skill is not found.'], 404);
                }
                $organizationSkill =  $organizationSkill->select('org_skill_id as skillId', 'org_skill_name as skillName', 'description', 'is_active as isActive')->first();
                Redis::set('skillMasterRedis' . $skillId, $organizationSkill);
                return response()->json(['status'=>true,'code'=>200,'data'=>$organizationSkill],200);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function updateOrganizationSkill(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'skillId'=>'required|integer',
            'skillName' => 'max:45',
            'description' => 'max:512',
            'isActive' => 'integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $organizationSkill = OrganizationSkill::where('is_active','!=','0')->where('org_id',$organizationId)->where('org_skill_id',$request->skillId);
            if ($organizationSkill->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Skill is not found.'], 404);
            }
            
            $organizationSkill->update([
                'org_skill_name' => $request->skillName,
                'description' => $request->description,
                'is_active' => $request->isActive == '' ? $organizationSkill->first()->is_active ? $organizationSkill->first()->is_active : '1' : $request->isActive,
                'modified_id' => $authId
            ]);

            $organizationSkill = $organizationSkill->select('org_skill_id as skillId', 'org_skill_name as skillName', 'description', 'is_active as isActive')->first();
            
            Redis::del('organizationSkillRedis' . $request->skillId);
            Redis::set('organizationSkillRedis' . $request->skillId, json_encode($organizationSkill,false));

            return response()->json(['status'=>true,'code'=>200,'message'=>'Skill has been updated successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function deleteOrganizationSkill(Request $request)
    {
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'skillId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{ 
            $organizationSkill = OrganizationSkill::where('is_active','!=','0')->where('org_id',$organizationId)->where('org_skill_id',$request->skillId);
            if ($organizationSkill->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Skill is not found.'], 404);
            }
            
            $organizationSkill->update([
                'is_active' => '0'
            ]);

            Redis::del('organizationSkillRedis' . $request->skillId);
            return response()->json(['status'=>true,'code'=>200,'message'=>'Skill has been deleted successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }
}
