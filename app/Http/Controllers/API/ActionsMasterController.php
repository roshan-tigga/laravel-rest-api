<?php

namespace App\Http\Controllers\API;

use App\Models\ActionMaster;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use DB;

class ActionsMasterController extends BaseController
{
    public function getActionList(){
        $actionMaster = DB::table('lms_actions_master as action')
        ->leftjoin('lms_module_master as module','module.module_id','=','action.module_id')
        ->where('action.is_active','!=','0')
        ->orderBy('action.actions_id','Desc')
        ->select('action.actions_id as actionsId','action.action_name as actionName', 'action.controller_name as controllerName', 'action.method_name as methodName', 'action.module_id as moduleId', 'module.module_name as moduleName', 'action.is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$actionMaster],200);
    }

    public function addNewAction(Request $request){
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'actionName' => 'max:250',
            'moduleId' => 'nullable|integer',
            'controllerName' => 'required|max:250',
            'methodName' => 'required|max:250',
            'isActive' => 'integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        try {
            $actionMaster = new ActionMaster;
            $actionMaster->action_name = $request->actionName;
            $actionMaster->module_id = $request->moduleId;
            $actionMaster->controller_name = $request->controllerName;
            $actionMaster->method_name = $request->methodName;
            $actionMaster->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $actionMaster->created_id = $authId;
            $actionMaster->modified_id = $authId;
            $actionMaster->save();

            return response()->json(['status'=>true,'code'=>200,'message'=>'Action has been created successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function getActionById($actionsId){
        try{
            $actionMasterRedis = Redis::get('actionMasterRedis' . $actionsId);
            if(isset($actionMasterRedis)){
                return response()->json(['status'=>true,'code'=>200,'data'=>json_decode($actionMasterRedis,false)],200);
            }else{
                $actionMaster = DB::table('lms_actions_master as action')
                ->leftjoin('lms_module_master as module','module.module_id','=','action.module_id')
                ->where('action.is_active','!=','0')->where('action.actions_id',$actionsId);
                
                if ($actionMaster->count() < 1) {
                    return response()->json(['status'=>false,'code'=>404,'error'=>'Action is not found.'], 404);
                }
                $actionMaster = $actionMaster->select('action.actions_id as actionsId','action.action_name as actionName', 'action.controller_name as controllerName', 'action.method_name as methodName', 'action.module_id as moduleId', 'module.module_name as moduleName', 'action.is_active as isActive')->first();
                Redis::set('actionMasterRedis' . $actionsId, json_encode($actionMaster,false));
                return response()->json(['status'=>true,'code'=>200,'data'=>$actionMaster],200);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function updateAction(Request $request){
        $authId = Auth::user()->user_id;

        $validator = Validator::make($request->all(), [
            'actionsId' => 'required|integer',
            'actionName' => 'max:250',
            'moduleId' => 'nullable|integer',
            'controllerName' => 'required|max:250',
            'methodName' => 'required|max:250',
            'isActive' => 'integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{

            $actionMaster = ActionMaster::where('is_active','!=','0')->where('actions_id',$request->actionsId);
            if ($actionMaster->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Action is not found.'], 404);
            }

            $actionMaster->update([
                'action_name' => $request->actionName,
                'module_id' => $request->moduleId,
                'controller_name' => $request->controllerName,
                'method_name' => $request->methodName,
                'is_active' => $request->isActive == '' ? $actionMaster->first()->is_active ? $actionMaster->first()->is_active : '1' : $request->isActive,
                'modified_id' => $authId
            ]);


            $actionMaster = DB::table('lms_actions_master as action')
            ->leftjoin('lms_module_master as module','module.module_id','=','action.module_id')
            ->where('action.is_active','!=','0')->where('action.actions_id',$request->actionsId)
            ->select('action.actions_id as actionsId','action.action_name as actionName', 'action.controller_name as controllerName', 'action.method_name as methodName', 'action.module_id as moduleId', 'module.module_name as moduleName', 'action.is_active as isActive')->first();

            Redis::del('actionMasterRedis' . $request->actionsId);
            Redis::set('actionMasterRedis' . $request->actionsId, json_encode($actionMaster,false));

            return response()->json(['status'=>true,'code'=>200,'message'=>'Action has been updated successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function deleteAction(Request $request){
        $validator = Validator::make($request->all(), [
            'actionsId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $actionMaster = ActionMaster::where('is_active','!=','0')->where('actions_id',$request->actionsId);
            if($actionMaster->count() > 0){

                $actionMaster->update([ 
                    'is_active' => '0',
                ]);

                Redis::del('actionMasterRedis' . $request->actionsId);

                return response()->json(['status'=>true,'code'=>200,'message'=>'Action has been deleted successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'Action is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function getActionOptionList(){ 
        $actionMaster = ActionMaster::where('is_active','1')
        ->orderBy('action_name','ASC')
        ->select('actions_id as actionsId','action_name as actionName')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$actionMaster],200);
    }

    public function getActionsByModuleId($moduleId){
        $actionMaster = ActionMaster::where('is_active','!=','0')
        ->orderBy('action_name','ASC')
        ->select('actions_id as actionsId','action_name as actionName','controller_name as controllerName', 'method_name as methodName', 'is_active as isActive')
        ->where('module_id',$moduleId)
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$actionMaster],200);
    }

    public function getModuleActionsList(Request $request){
        $sort = $request->has('sort') ? $request->get('sort') : 'actions_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if($sort == 'actionName'){
            $sortColumn = 'action.action_name';
        }elseif($sort == 'moduleName'){
            $sortColumn = 'module.module_name';
        }

        $data = $allData = [];
        $modules = DB::table('lms_actions_master as action')
        ->join('lms_module_master as module','module.module_id','=','action.module_id')
        ->orderBy($sortColumn,$order)
        ->where(function($query) use ($search){
            if($search != ''){
                $query->where('action.action_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('module.module_name', 'LIKE', '%'.$search.'%');
            }
        })
        ->where('action.is_active','1')->groupBy('action.module_id')
        ->select('action.module_id','action.action_name','module.module_id','module.module_name')->get();
        if($modules->count() > 0){
            foreach($modules as $module){

                $actionData = [];

                $actions = ActionMaster::where('is_active','1')->where('module_id',$module->module_id)->get();
                if($actions->count() > 0){
                    foreach($actions as $action){
                        $actionData[] = [
                            'actionsId' => $action->actions_id,
                            'actionsName' => $action->action_name
                        ];
                    }
                }
                $data['moduleId'] = $module->module_id;
                $data['moduleName'] = $module->module_name;
                $data['actions'] = $actionData;
                $allData[] = $data;
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$allData],200);
    }

}
