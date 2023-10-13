<?php

namespace App\Http\Controllers\API;

use App\Models\SkillMaster;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController; 
use Auth;
use Illuminate\Support\Facades\Redis;

class SkillMasterController extends BaseController
{

    public function getSkillList(Request $request)
    {
        $sort = $request->has('sort') ? $request->get('sort') : 'skill_id';
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

        $skillMaster = SkillMaster::where('is_active','!=','0')
        ->where(function($query) use ($search){
            if($search != ''){
                $query->orWhere('skill_name', 'LIKE', '%'.$search.'%');
                //$query->orWhere('description', 'LIKE', '%'.$search.'%');
                if(in_array($search,['active','act','acti','activ'])){
                    $query->orWhere('is_active','1');
                }
                if(in_array($search,['inactive','inact','inacti','inactiv'])){
                    $query->orWhere('is_active','2');
                }
            }
        })
        ->orderBy($sortColumn,$order)
        ->select('skill_id as skillId', 'skill_name as skillName', 'description', 'is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$skillMaster],200);
    }

    public function addNewSkill(Request $request)
    {
        $authId = Auth::user()->user_id;
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

            $skillMaster = new SkillMaster;
            $skillMaster->skill_name = $request->skillName;
            $skillMaster->description = $request->description;
            $skillMaster->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $skillMaster->created_id = $authId;
            $skillMaster->modified_id = $authId;
            $skillMaster->save();

            return response()->json(['status'=>true,'code'=>200,'message'=>'Skill has been created successfully.'],200);

        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function getSkillById($skillId)
    {
        try {
            $skillMasterRedis = Redis::get('skillMasterRedis' . $skillId);
            if(isset($skillMasterRedis)){
                return response()->json(['status'=>true,'code'=>200,'data'=>json_decode($skillMasterRedis,false)],200);
            }else{
                $skillMaster = SkillMaster::where('is_active','!=','0')->where('skill_id',$skillId);
                if ($skillMaster->count() < 1) {
                    return response()->json(['status'=>false,'code'=>404,'error'=>'Skill is not found.'], 404);
                }
                $skillMaster =  $skillMaster->select('skill_id as skillId', 'skill_name as skillName', 'description', 'is_active as isActive')->first();
                Redis::set('skillMasterRedis' . $skillId, $skillMaster);
                return response()->json(['status'=>true,'code'=>200,'data'=>$skillMaster],200);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function updateSkillById(Request $request)
    {
        $authId = Auth::user()->user_id;
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
            $skillMaster = SkillMaster::where('is_active','!=','0')->where('skill_id',$request->skillId);
            if ($skillMaster->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Skill is not found.'], 404);
            }
            
            $skillMaster->update([
                'skill_name' => $request->skillName,
                'description' => $request->description,
                'is_active' => $request->isActive == '' ? $skillMaster->first()->is_active ? $skillMaster->first()->is_active : '1' : $request->isActive,
                'modified_id' => $authId
            ]);

            $skillMaster = $skillMaster->select('skill_id as skillId', 'skill_name as skillName', 'description', 'is_active as isActive')->first();
            
            Redis::del('skillMasterRedis' . $request->skillId);
            Redis::set('skillMasterRedis' . $request->skillId, json_encode($skillMaster,false));

            return response()->json(['status'=>true,'code'=>200,'message'=>'Skill has been updated successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function deleteSkill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'skillId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $skillMaster = SkillMaster::where('is_active','!=','0')->where('skill_id',$request->skillId);
            if ($skillMaster->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Skill is not found.'], 404);
            }
            
            $skillMaster->update([
                'is_active' => '0'
            ]);

            Redis::del('skillMasterRedis' . $request->skillId);
            return response()->json(['status'=>true,'code'=>200,'message'=>'Skill has been deleted successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }
}
