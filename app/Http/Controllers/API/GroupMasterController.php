<?php

namespace App\Http\Controllers\API;

use App\Models\GroupMaster;
use App\Models\CategoryMaster;
use App\Models\UserGroup;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GroupMasterController extends BaseController
{
    public function getGroupList(Request $request){

        $organizationId = Auth::user()->org_id;

        $sort = $request->has('sort') ? $request->get('sort') : 'group.group_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if($sort == 'groupName'){
            $sortColumn = 'group.group_name';
        }elseif($sort == 'groupCode'){
            $sortColumn = 'group.group_code';
        }elseif($sort == 'parentGroupName'){
            $sortColumn = 'parentGroup.group_name';
        }elseif($sort == 'description'){
            $sortColumn = 'group.description';
        }elseif($sort == 'organizationName'){
            $sortColumn = 'org.organization_name';
        }elseif($sort == 'isActive'){
            $sortColumn = 'group.is_active';
        }

        $superadminGroups = DB::table('lms_group_master as group')
        ->leftjoin('lms_group_master as parentGroup','group.primary_group_id','=','parentGroup.group_id')
        ->leftjoin('lms_org_master as org','group.org_id','=','org.org_id')
        ->where('group.is_active','!=','0')
        ->where('org.is_active','!=','0')
        ->where(function($query) use ($search){
            if($search != ''){
                $query->where('group.group_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('group.group_code', 'LIKE', '%'.$search.'%');
                $query->orWhere('parentGroup.group_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('group.description', 'LIKE', '%'.$search.'%');
                $query->orWhere('org.organization_name', 'LIKE', '%'.$search.'%');
                if(in_array($search,['active','act','acti','activ'])){
                    $query->orWhere('group.is_active','1');
                }
                if(in_array($search,['inactive','inact','inacti','inactiv'])){
                    $query->orWhere('group.is_active','2');
                }
            }
        })
        ->orderBy($sortColumn,$order)
        ->select('group.group_id as groupId', 'group.group_name as groupName', 'group.group_code as groupCode', 'parentGroup.group_name as parentGroupName', 'group.description','group.is_auto as isAuto', 'org.organization_name as organizationName', 'group.is_active as isActive')
        ->get();

        if($superadminGroups->count() > 0){
            foreach($superadminGroups as $group){
                $group->usersAssiged = DB::table('lms_group_master as group')->leftjoin('lms_user_group as userGroup','group.group_id','=','userGroup.group_id')
                ->where('group.is_active','!=','0')
                ->where('userGroup.is_active','1')
                ->where('userGroup.group_id',$group->groupId)
                ->where('userGroup.org_id',$organizationId)
                ->groupBy('userGroup.user_id')
                ->pluck('userGroup.user_id');
            }
        }

        // $organizationGroups = DB::table('lms_group_org as group')
        // ->leftjoin('lms_group_org as parentGroup','group.group_id','=','parentGroup.group_id')
        // ->where('group.is_active','!=','0')
        // ->where(function($query) use ($search){
        //     if($search != ''){
        //         $query->where('group.group_name', 'LIKE', '%'.$search.'%');
        //         $query->orWhere('group.group_code', 'LIKE', '%'.$search.'%');
        //         $query->orWhere('parentGroup.group_name', 'LIKE', '%'.$search.'%');
        //         $query->orWhere('group.description', 'LIKE', '%'.$search.'%');
        //         if(in_array($search,['active','act','acti','activ'])){
        //             $query->orWhere('group.is_active','1');
        //         }
        //         if(in_array($search,['inactive','inact','inacti','inactiv'])){
        //             $query->orWhere('group.is_active','2');
        //         }
        //     }
        // })
        // ->orderBy($sortColumn,$order)
        // ->select('group.group_id as groupId', 'group.group_name as groupName', 'group.group_code as groupCode', 'parentGroup.group_name as parentGroupName', 'group.description', 'group.is_active as isActive')
        // ->get();

        // $data = [];
        // $data['superadminGroups'] = $superadminGroups;
        // $data['organizationGroups'] = $organizationGroups; 
        return response()->json(['status'=>true,'code'=>200,'data'=>$superadminGroups],200);
    }


    public function getGroupOptionList(){
        $groups = GroupMaster::where('is_active','1')->orderBy('group_name','ASC')->select('group_id as groupId', 'group_name as groupName', 'is_active as isActive')->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$groups],200);
    }

    public function getPrimaryGroupList(){
        $groups = GroupMaster::where('is_active','1')->whereNull('primary_group_id')->orderBy('group_name','ASC')->select('group_id as groupId', 'group_name as groupName')->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$groups],200);
    }

    public function addGroup(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        
        $validator = Validator::make($request->all(), [
            'groupName' => 'required|max:150',
            'parentGroup' => 'nullable|integer',
            'description' => 'nullable|max:512',
            'isActive' => 'integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $group = GroupMaster::orderBy('group_code','DESC'); 
            if($group->count() > 0){
                $groupCode = $group->first()->group_code + 1;
            }else{
                $groupCode = $organizationId.'000000001';
            }

            $groupMaster = new GroupMaster;
            $groupMaster->group_name = $request->groupName;
            $groupMaster->group_code = $groupCode;
            $groupMaster->org_id = $organizationId;
            $groupMaster->description = $request->description;
            $groupMaster->primary_group_id = ($request->parentGroup != '') ? $request->parentGroup : Null;
            //$groupMaster->is_auto = $request->isAuto;
            $groupMaster->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $groupMaster->created_id = $authId;
            $groupMaster->modified_id = $authId;
            $groupMaster->save();

            return response()->json(['status'=>true,'code'=>200,'message'=>'Group has been created successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        } 
    }

    public function getGroupById($groupId){
        try{
            $groupMasterRedis = Redis::get('groupMasterRedis' . $groupId);
            if(isset($groupMasterRedis)){
                $groupMasterRedis = json_decode($groupMasterRedis,false);
                return response()->json(['status'=>true,'code'=>200,'data'=>$groupMasterRedis],200);
            }else{
                $groupMaster = GroupMaster::where(['is_active'=>'1','group_id'=>$groupId]);
                if ($groupMaster->count() < 1) {
                    return response()->json(['status'=>false,'code'=>404,'error'=>'Group is not found.'], 404);
                }
                
                $groupMaster = DB::table('lms_group_master as group')
                ->leftjoin('lms_group_master as parentGroup','group.primary_group_id','=','parentGroup.group_id')
                ->leftjoin('lms_org_master as org','group.org_id','=','org.org_id')
                ->where('group.is_active','!=','0')
                ->where('org.is_active','!=','0')
                ->where('group.group_id',$groupId)
                ->select('group.group_id as groupId', 'group.group_name as groupName', 'group.group_code as groupCode', 'group.primary_group_id as parentGroupId', 'parentGroup.group_name as parentGroupName', 'group.description', 'group.org_id as organizationId', 'org.organization_name as organizationName', 'group.is_active as isActive')
                ->first();
                Redis::set('groupMasterRedis' . $groupId, json_encode($groupMaster,false));
                return response()->json(['status'=>true,'code'=>200,'data'=>$groupMaster],200);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function updateGroup(Request $request){
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'groupId'=>'required|integer', 
            'groupName' => 'required|max:150',
            'parentGroup' => 'nullable|integer',
            'description' => 'nullable|max:512',
            'isActive' => 'integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        
        try{
            $groupMaster = GroupMaster::where(['is_active'=>'1','group_id'=>$request->groupId]);
            if ($groupMaster->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Group is not found.'], 404);
            }

            $groupMaster->update([
                'group_name' => $request->groupName,
                'primary_group_id' => ($request->parentGroup != '') ? $request->parentGroup : Null,
                'description' => $request->description,
                'is_active' => $request->isActive == '' ? $groupMaster->first()->is_active ? $groupMaster->first()->is_active : '1' : $request->isActive,
                'modified_id' => $authId
            ]);


            $groupMaster = DB::table('lms_group_master as group')
            ->leftjoin('lms_group_master as parentGroup','group.primary_group_id','=','parentGroup.group_id')
            ->leftjoin('lms_org_master as org','group.org_id','=','org.org_id')
            ->where('group.is_active','!=','0')
            ->where('org.is_active','!=','0')
            ->where('group.group_id',$request->groupId)
            ->select('group.group_id as groupId', 'group.group_name as groupName', 'group.group_code as groupCode', 'group.primary_group_id as parentGroupId', 'parentGroup.group_name as parentGroupName', 'group.description','group.org_id as organizationId', 'org.organization_name as organizationName', 'group.is_active as isActive')->first();
            
            Redis::del('groupMasterRedis' . $request->groupId);
            Redis::set('groupMasterRedis' . $request->groupId, json_encode($groupMaster,false));

            return response()->json(['status'=>true,'code'=>200,'message'=>'Group has been updated successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function deleteGroup(Request $request){
        $validator = Validator::make($request->all(), [
            'groupId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $groupMaster = GroupMaster::where('is_active','!=','0')->where('group_id',$request->groupId);
            if($groupMaster->count() > 0){

                $groupMaster->update([
                    'is_active' => '0',
                ]);

                UserGroup::where('group_id',$request->groupId)->update([
                    'is_active' => '0',
                ]);

                Redis::del('groupMasterRedis' . $request->groupId);

                return response()->json(['status'=>true,'code'=>200,'message'=>'Group has been deleted successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'Group is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function bulkDeleteGroup(Request $request){
        $validator = Validator::make($request->all(), [
            'groupIds'=>'required|array'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $groupMaster = GroupMaster::where('is_active','!=','0')->whereIn('group_id',$request->groupIds);
            if($groupMaster->count() > 0){

                $groupMaster->update([
                    'is_active' => '0',
                ]);

                UserGroup::whereIn('group_id',$request->groupIds)->update([
                    'is_active' => '0',
                ]);

                if(!empty($request->groupIds)){
                    foreach($request->groupIds as $groupId){
                        Redis::del('groupMasterRedis' . $groupId);
                    }
                }

                return response()->json(['status'=>true,'code'=>200,'message'=>'Group has been deleted successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'Group is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function getUserGroupList(Request $request){
        $validator = Validator::make($request->all(), [
            'userId' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $userId = $request->userId;
        $organizationId = Auth::user()->org_id;

        $groups = DB::table('lms_group_master as group')
        ->where('group.org_id',$organizationId)
        ->select('group.group_id as groupId', 'group.group_name as groupName')
        ->get();
        if($groups->count() > 0){
            foreach($groups as $group){
                $userGroup = DB::table('lms_user_group')->where('group_id',$group->groupId)->where('user_id',$userId)->where('org_id',$organizationId);
                if($userGroup->count() > 0){
                    $group->isActive = $userGroup->first()->is_active;
                }else{
                    $group->isActive = '';
                }
            }
        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$groups],200);
    }

    public function addUserGroup(Request $request){

        $validator = Validator::make($request->all(), [
            'userId' => 'required|integer',
            'groups' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $authId = Auth::user()->user_id;
        $userId = $request->userId;
        $organizationId = Auth::user()->org_id;

        if(isset($request->groups)){

            if(is_array($request->groups)){

                if(count($request->groups) > 0){

                    foreach($request->groups as $group){

                        $groupId = $group['groupId'];
                        $checked = $group['checked'];

                        $userGroup = DB::table('lms_user_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId);
                        if($userGroup->count() > 0){
                            $userGroup->update(['is_active' => $checked]);
                        }else{
                            DB::table('lms_user_group')->insert([
                                'group_id' => $groupId,
                                'user_id' => $userId,
                                'org_id' => $organizationId,
                                'is_active' => $checked,
                                'created_id' => $authId,
                                'modified_id' => $authId,
                                'date_created'=> Carbon::now(),
                                'date_modified'=> Carbon::now()
                            ]);
                        }
                    }

                }

            }
        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'User group has been created successfully.'],200);
    }

    public function groupImport(Request $request){

        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        $parentGroupId = '';

        $validator = Validator::make($request->all(), [
            'groupImportFile' => 'required|file|mimes:xls,xlsx,csv,txt'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $groupImportFile = $request->file('groupImportFile');

        try{
            $spreadsheet = IOFactory::load($groupImportFile->getRealPath());
            $sheet        = $spreadsheet->getActiveSheet();
            $row_limit    = $sheet->getHighestDataRow();
            $column_limit = $sheet->getHighestDataColumn();
            $row_range    = range( 2, $row_limit );
            $column_range = range( 'D', $column_limit );
            $startcount = 2;
            $groupData = [];
            
            $errorMessages = [];
            $errors = 0;
 
            if($row_limit == 1){
              return response()->json(['status'=>false,'code'=>400,'errors'=>'The file is not empty.'],400);
            }
 
            foreach ( $row_range as $row ) {
                 
                $errorMessage = [];

                $groupName = $sheet->getCell( 'A' . $row )->getValue();
                $parentGroupName = $sheet->getCell( 'B' . $row )->getValue();
                //$organizationName = $sheet->getCell( 'C' . $row )->getValue();
                $description = $sheet->getCell( 'C' . $row )->getValue();

                $errorMessage['rowNumber'] = $row;

                if($groupName == ''){
                    $errorMessage['groupName'] = 'The group name field is required';
                    $errors++;
                }

                if($parentGroupName != ''){
                    $parentGroup = GroupMaster::where('group_name',$parentGroupName)->where('is_active','!=','0');
                    if($parentGroup->count() > 0){
                        $parentGroupId = $parentGroup->first()->group_id;
                    }else{
                        $errorMessage['parentGroupName'] = 'The parent group not found';
                        $errors++;
                    }
                }

                // if($organizationName == ''){
                //     $errorMessage['organizationName'] = 'The organization name field is required';
                //     $errors++;
                // }else{
                //     $organization = Organization::where('organization_name',$organizationName)->where('is_active','!=','0');
                //     if($organization->count() > 0){
                //         $organizationId = $organization->first()->org_id;
                //     }else{
                //         $errorMessage['organizationName'] = 'The organization not found';
                //         $errors++;
                //     }
                // }

                 
                if(!empty($errorMessage)){
                    $errorMessages[] = $errorMessage;
                }

                $groupData[] = [
                    'groupName' =>$groupName,
                    'parentGroupName' => $parentGroupId,
                    'organizationName' => $organizationId,
                    'description' => $description
                ];
                $startcount++;
            }
 
             if($errors == 0){
                 if(!empty($groupData)){
                     foreach($groupData as $row){

                        $group = GroupMaster::orderBy('group_code','DESC'); 
                        if($group->count() > 0){
                            $groupCode = $group->first()->group_code + 1;
                        }else{
                            $groupCode = $organizationId.'000000001';
                        }

                         $groupMaster = new GroupMaster;
                         $groupMaster->group_name = $row['groupName'];
                         $groupMaster->group_code = $groupCode;
                         $groupMaster->primary_group_id = $row['parentGroupName'];
                         $groupMaster->org_id = $row['organizationName'];
                         $groupMaster->description = $row['description'];
                         //$groupMaster->is_active = $row['isActive'] == '' ? '1' : $row['isActive'];
                         $groupMaster->created_id = $authId;
                         $groupMaster->modified_id = $authId;
                         $groupMaster->save();
                     }
                     return response()->json(['status'=>true,'code'=>200,'message'=>'Group has been imported successfully.'],200);
                 }
             }else{
                 return response()->json(['status'=>false,'code'=>400,'errors'=>$errorMessages],400);
             }
        } catch (\Throwable $e) {
          return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function groupExport(){

        $groups = DB::table('lms_group_master as group')
        ->leftjoin('lms_group_master as parentGroup','group.group_id','=','parentGroup.group_id')
        ->leftjoin('lms_org_master as org','group.org_id','=','org.org_id')
        ->where('group.is_active','!=','0')
        ->where('org.is_active','!=','0')
        ->select('group.group_id as groupId', 'group.group_name as groupName', 'group.group_code as groupCode', 'parentGroup.group_name as parentGroupName', 'group.description', 'org.organization_name as organizationName', 'group.is_active as isActive')
        ->get();

        $data_array [] = array("Group Name","Group Code","Parent Group Name","Organization Name","Description");

        if($groups->count() > 0){
            foreach($groups as $group)
            {
                $data_array[] = array(
                    'groupName' =>$group->groupName,
                    'groupCode' => $group->groupCode,
                    'parentGroupName' => $group->parentGroupName,
                    'organizationName' => $group->organizationName,
                    'description' => $group->description
                );
            }
        }


        $spreadSheet = new Spreadsheet();
        $spreadSheet->getActiveSheet()->getDefaultColumnDimension()->setWidth(20);
        $spreadSheet->getActiveSheet()->fromArray($data_array);
        $Excel_writer = new Xls($spreadSheet);

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="group_export.csv"');
        header('Cache-Control: max-age=0');
        ob_end_clean();
        $Excel_writer->save('php://output');
        exit();

    }

    public function getCategoryAssignGroupList($categoryId){
        $groupArray = [];
        $groupsArray = [];
        $groups = GroupMaster::where('is_active','1');
        if($groups->count() > 0){
            foreach($groups->get() as $group){
                $groupAssignment = DB::table('category_group_assignment')
                ->where('is_active','1')
                ->where('category_id',$categoryId)
                ->where('group_id','=',$group->group_id);
                if($groupAssignment->count() > 0){
                    
                }else{
                    $groupArray['groupId'] = $group->group_id;
                    $groupArray['groupName'] = $group->group_name;
                    $groupsArray[] = $groupArray;
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$groupsArray],200);
    }

    public function getGroupListByCategoryId($categoryId){ 
        $groupData = [];
        $groups = GroupMaster::where('is_active','1');
        if($groups->count() > 0){
            foreach($groups->get() as $group){

                $isChecked = 0;
                $groupAssignment = DB::table('category_group_assignment')->where('is_active','1')->where('group_id',$group->group_id)->where('category_id',$categoryId);
                if($groupAssignment->count()){
                    $isChecked = $groupAssignment->first()->is_active;
                }
                $groupData[] = [
                    'groupId' => $group->group_id,
                    'groupName' => $group->group_name,
                    'groupCode' => $group->group_code,
                    'isChecked' => $isChecked,
                ];
            }
            $array = array_column($groupData, 'isChecked');
            array_multisort($array, SORT_DESC, $groupData);
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$groupData],200);
    }
}
