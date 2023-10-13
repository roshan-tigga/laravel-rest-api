<?php

namespace App\Http\Controllers\API;

use App\Models\GroupOrganization;
use App\Models\UserGroup;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;
use Carbon\Carbon;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GroupOrganizationController extends BaseController 
{
    public function getOrgGroupList(Request $request){

        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;

        $sort = $request->has('sort') ? $request->get('sort') : 'group.group_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $isAuto = $request->has('isAuto') ? $request->get('isAuto') : '';
        $sortColumn = $sort;

        $groups = DB::table('lms_group_org as group')
        ->leftjoin('lms_group_org as parentGroup','group.primary_group_id','=','parentGroup.group_id')
        ->join('lms_user_master as user','group.created_id','=','user.user_id')
        ->where('group.is_active','!=','0')
        
        ->where(function($query) use ($isAuto){
            if($isAuto != ''){
                $query->where('group.is_auto',$isAuto);
            }
        })
        ->where('group.group_name','!=','Invisible groups')
        ->where(function($query) use ($organizationId,$roleId,$authId){ 
            if($roleId != 1){

                $query->where('user.org_id',$organizationId);
                $query->where('user.role_id','>',$roleId - 1);
                $userArray = userArray($authId,$roleId,$organizationId);
                $query->whereIn('user.user_id',$userArray);  

                // $userArray = DB::table('lms_user_master as user')
                // ->where('org_id',$organizationId)
                // ->where('role_id','>',$roleId)->pluck('user_id')->toArray();
                
                // $query->where('user.org_id',$organizationId);
                // $query->where('user.role_id','>',$roleId);
                // $query->where('user.created_id','=',$authId);
                // if(!empty($userArray)){
                //     $query->orWhereIn('user.created_id',$userArray);
                // }

                // if($roleId == 2){
                //     //$query->where('user.role_id','>','1');
                //     $query->where('user.created_id','=',$authId);
                // }
                // if($roleId == 3){
                //     //$query->where('user.role_id','>','2');
                //     $query->where('user.created_id','=',$authId);
                // }
                // if($roleId == 4){
                //     //$query->where('user.role_id','>','3');
                //     $query->where('user.created_id','=',$authId);
                // }
                // if($roleId == 5){
                //     //$query->where('user.role_id','>','4');
                //     $query->where('user.created_id','=',$authId);
                // }
                // if($roleId == 6){
                //     //$query->where('user.role_id','>','5');
                //     $query->where('user.created_id','=',$authId);
                // }
                //$query->where('group.org_id',$organizationId);
                //$query->where('group.created_id',$authId);
                //$query->where('user.created_id','=',$authId);
                
                //$query->orWhere('org.parent_org_id',$organizationId);
            }else{
                $query->where('group.org_id',$organizationId);
            }
        })
        ->orderBy($sortColumn,$order)
        ->select('group.group_id as groupId', 'group.group_name as groupName', 'group.group_code as groupCode', 'parentGroup.group_name as parentGroupName', 'group.description','group.is_auto as isAuto',
        DB::raw('(CASE WHEN group.group_type = 1 THEN "Job Title"
        WHEN group.group_type = 2 THEN "Division"
        WHEN group.group_type = 3 THEN "Area"
        WHEN group.group_type = 4 THEN "Location"
        WHEN group.group_type = 5 THEN "Organization"
        ELSE "" END) AS groupType')
        ,'group.is_active as isActive')
        ->get();

        if($groups->count() > 0){
            foreach($groups as $group){
                $group->usersAssiged = DB::table('lms_user_org_group as assignGroup')
                ->join('lms_user_master as user','assignGroup.user_id','=','user.user_id')
                ->join('lms_group_org as orgGroup','assignGroup.group_id','=','orgGroup.group_id')
                ->where('orgGroup.is_active','1')
                ->where('assignGroup.is_active','1')
                ->where('user.is_active','1')
                ->where(function($query) use ($isAuto){
                    if($isAuto != ''){
                        $query->where('orgGroup.is_auto',$isAuto);
                    }
                })
                ->where('assignGroup.group_id',$group->groupId)
                ->where('assignGroup.org_id',$organizationId)
                ->groupBy('user.user_id')
                ->select(DB::raw('CONCAT(user.first_name," ",user.last_name) as fullName'))
                ->pluck('fullName');

                $group->categoryAssiged = DB::table('lms_org_category_group_assignment as group')
                ->leftjoin('lms_org_category as category','group.category_id','=','category.category_id')
                ->where('group.is_active','1')
                ->where('category.is_active','1')
                ->where('group.group_id',$group->groupId)
                ->where('group.org_id',$organizationId)
                ->select('category.category_name as categoryName')
                ->pluck('category.category_name as categoryName');

                if($isAuto == ''){
                    
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$groups],200);
    }

    public function getOrgGroupOptionList(){
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;

        $groups = GroupOrganization::join('lms_user_master as user','lms_group_org.created_id','=','user.user_id')
        ->where('lms_group_org.is_active','1')->where('lms_group_org.group_name','!=','Invisible groups')
        ->where(function($query) use ($organizationId,$roleId,$authId){ 
            if($roleId != 1){

                $query->where('user.org_id',$organizationId);
                $query->where('user.role_id','>',$roleId - 1);
                $userArray = userArray($authId,$roleId,$organizationId);
                $query->whereIn('user.user_id',$userArray);  

                // if($roleId == 2){
                //     //$query->where('user.role_id','>','1');
                //     $query->where('user.created_id','=',$authId);
                // }
                // if($roleId == 3){
                //     //$query->where('user.role_id','>','2');
                //     $query->where('user.created_id','=',$authId);
                // }
                // if($roleId == 4){
                //     //$query->where('user.role_id','>','3');
                //     $query->where('user.created_id','=',$authId);
                // }
                // if($roleId == 5){
                //     //$query->where('user.role_id','>','4');
                //     $query->where('user.created_id','=',$authId);
                // }
                // if($roleId == 6){
                //     //$query->where('user.role_id','>','5');
                //     $query->where('user.created_id','=',$authId);
                // }
                //$query->where('lms_group_org.org_id',$organizationId);
                //$query->where('lms_group_org.created_id','=',$authId);

            }else{
                $query->where('lms_group_org.org_id',$organizationId);
            }
        })
        ->orderBy('lms_group_org.group_name','ASC')->select('lms_group_org.group_id as groupId', 'lms_group_org.group_name as groupName')->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$groups],200);
    }

    public function getOrgPrimaryGroupList(){
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;
        
        $groups = GroupOrganization::join('lms_user_master as user','lms_group_org.created_id','=','user.user_id')
        ->where('lms_group_org.is_active','1')
        ->where('lms_group_org.group_name','!=','Invisible groups')
        ->where(function($query) use ($organizationId,$roleId,$authId){ 
            if($roleId != 1){

                $query->where('user.org_id',$organizationId);
                $query->where('user.role_id','>',$roleId - 1);
                $userArray = userArray($authId,$roleId,$organizationId);
                $query->whereIn('user.user_id',$userArray);  

                // $userArray = DB::table('lms_user_master as user')
                // ->where('org_id',$organizationId)
                // ->where('role_id','>',$roleId)->pluck('user_id')->toArray();
                
                // $query->where('user.org_id',$organizationId);
                // $query->where('user.role_id','>',$roleId);
                // $query->where('user.created_id','=',$authId);
                // if(!empty($userArray)){
                //     $query->orWhereIn('user.created_id',$userArray);
                // }

                // if($roleId == 2){
                //     //$query->where('user.role_id','>','1');
                //     $query->where('user.created_id','=',$authId);
                // }
                // if($roleId == 3){
                //     //$query->where('user.role_id','>','2');
                //     $query->where('user.created_id','=',$authId);
                // }
                // if($roleId == 4){
                //     //$query->where('user.role_id','>','3');
                //     $query->where('user.created_id','=',$authId);
                // }
                // if($roleId == 5){
                //     //$query->where('user.role_id','>','4');
                //     $query->where('user.created_id','=',$authId);
                // }
                // if($roleId == 6){
                //     //$query->where('user.role_id','>','5');
                //     $query->where('user.created_id','=',$authId);
                // }
                //$query->where('lms_group_org.created_id','=',$authId);
                //$query->where('lms_group_org.org_id',$organizationId);
            }else{
                $query->where('lms_group_org.org_id',$organizationId);
            }
        })
        ->whereNull('lms_group_org.primary_group_id')
        ->orderBy('lms_group_org.group_name','ASC')
        ->select('lms_group_org.group_id as groupId', 'lms_group_org.group_name as groupName')->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$groups],200);
    }
    
    public function addOrgGroup(Request $request){
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

        $group = GroupOrganization::where('org_id',$organizationId)->orderBy('group_code','DESC');
        if($group->count() > 0){
            $groupCode = $group->first()->group_code + 1;
        }else{
            $groupCode = $organizationId.'000000001';
        }

        $groupMaster = new GroupOrganization;
        $groupMaster->group_name = $request->groupName;
        $groupMaster->group_code = $groupCode;
        $groupMaster->org_id = $organizationId;
        $groupMaster->description = $request->description;
        $groupMaster->primary_group_id = ($request->parentGroup != '') ? $request->parentGroup : Null;
        $groupMaster->is_auto = 0;
        $groupMaster->is_active = $request->isActive == '' ? '1' : $request->isActive;
        $groupMaster->created_id = $authId;
        $groupMaster->modified_id = $authId;
        $groupMaster->save();

        $GroupOrganization = GroupOrganization::where('created_id',$authId)->where('group_name','Invisible groups');
        if($GroupOrganization->count() > 0){

        }else{
            $group = GroupOrganization::where('org_id',$organizationId)->orderBy('group_code','DESC');
            if($group->count() > 0){
                $groupCode = $group->first()->group_code + 1;
            }else{
                $groupCode = $organizationId.'000000001';
            }

            $groupMaster = new GroupOrganization;
            $groupMaster->group_name = 'Invisible groups';
            $groupMaster->group_code = $groupCode;
            $groupMaster->org_id = $organizationId;
            $groupMaster->description = 'Invisible groups';
            $groupMaster->is_auto = 0;
            $groupMaster->is_active = 2;
            $groupMaster->created_id = $authId;
            $groupMaster->modified_id = $authId;
            $groupMaster->save();
        }

        return response()->json(['status'=>true,'code'=>200,'message'=>'Group has been created successfully.'],200);
    }

    public function getOrgGroupById($groupId){
        $organizationId = Auth::user()->org_id;
        $groupMaster = GroupOrganization::where('is_active','!=','0')->where('group_id',$groupId);
        if ($groupMaster->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Group is not found.'], 404);
        }
        $groupMaster = DB::table('lms_group_org as group')
        ->leftjoin('lms_group_org as parentGroup','group.primary_group_id','=','parentGroup.group_id')
        ->where('group.is_active','!=','0')
        ->where('group.group_id',$groupId)
        ->where('group.org_id',$organizationId)
        ->select('group.group_id as groupId', 'group.group_name as groupName', 'group.group_code as groupCode', 'group.primary_group_id as parentGroupId', 'parentGroup.group_name as parentGroupName', 'group.description', 'group.is_active as isActive')
        ->first();
        return response()->json(['status'=>true,'code'=>200,'data'=>$groupMaster],200);
    }

    public function updateOrgGroup(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
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
        
        $groupMaster = GroupOrganization::where('is_active','!=','0')->where('group_id',$request->groupId)->where('org_id',$organizationId);
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
        return response()->json(['status'=>true,'code'=>200,'message'=>'Group has been updated successfully.'],200);
    }

    public function deleteOrgGroup(Request $request){
        $organizationId = Auth::user()->org_id;
        $validator = Validator::make($request->all(), [
            'groupId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $groupMaster = GroupOrganization::where('is_active','!=','0')->where('org_id',$organizationId)->where('group_id',$request->groupId);
        if($groupMaster->count() > 0){

            $groupMaster->update([
                'is_active' => '0',
            ]);

            DB::table('lms_user_org_group')->where('org_id',$organizationId)->where('group_id',$request->groupId)->update([
                'is_active' => '0',
            ]);
            return response()->json(['status'=>true,'code'=>200,'message'=>'Group has been deleted successfully.'],200);
        }else{
            return response()->json(['status'=>false,'code'=>404,'error'=>'Group is not found.'], 404);
        }
    }

    public function bulkDeleteOrgGroup(Request $request){
        $organizationId = Auth::user()->org_id;
        $validator = Validator::make($request->all(), [
            'groupIds'=>'required|array'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $groupMaster = GroupOrganization::where('is_active','!=','0')->where('org_id',$organizationId)->whereIn('group_id',$request->groupIds);
        if($groupMaster->count() > 0){

            $groupMaster->update([
                'is_active' => '0',
            ]);

            DB::table('lms_user_org_group')->where('org_id',$organizationId)->whereIn('group_id',$request->groupIds)->update([
                'is_active' => '0',
            ]);

            return response()->json(['status'=>true,'code'=>200,'message'=>'Group has been deleted successfully.'],200);
        }else{
            return response()->json(['status'=>false,'code'=>404,'error'=>'Group is not found.'], 404);
        }
    }

    public function groupOrgImport(Request $request){

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
            $column_range = range( 'C', $column_limit );
            $startcount = 2;
            $groupData = [];
            
            $errorMessages = []; 
            $errors = 0;
 
            if($row_limit == 1){
              return response()->json(['status'=>false,'code'=>400,'errors'=>'The file is not empty.'],400);
            }
 
            foreach ( $row_range as $row ) {
                 
                $errorMessage = [];
                $i = 0;

                $groupName = $sheet->getCell( 'A' . $row )->getValue();
                $parentGroupName = $sheet->getCell( 'B' . $row )->getValue();
                $description = $sheet->getCell( 'C' . $row )->getValue();
                $userNames = $sheet->getCell( 'D' . $row )->getValue();

                $errorMessage['rowNumber'] = $row;

                if($groupName == ''){
                    $errorMessage['groupName'] = 'The group name field is required';
                    $errors++;
                    $i++;
                }

                $parentGroupId = '';
                if($parentGroupName != ''){
                    $parentGroup = GroupOrganization::where('group_name',$parentGroupName)->where('is_active','!=','0')->where('ogr_id',$organizationId);
                    if($parentGroup->count() > 0){
                        $parentGroupId = $parentGroup->first()->group_id;
                    }else{
                        $errorMessage['parentGroupName'] = 'The parent group not found';
                        $errors++;
                        $i++;
                    }
                }

                $users = [];
                if($userNames != ''){
                    $users = DB::table('lms_user_master')
                    ->where(function($query) use ($userNames){
                        if(!empty($userNames)){
                            foreach(explode(',',$userNames) as $userName){
                                $query->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"),'LIKE','%'.$userName.'%');
                            }
                        }
                    })
                    ->where('is_active','!=',4)
                    ->where('org_id',$organizationId);
                    if($users->count() > 0){
                        $users = $users->pluck('user_id');
                    }else{
                        $errorMessage['userName'] = 'The user not found';
                        $errors++;
                        $i++;
                    }
                }
                 
                if(!empty($errorMessage)){
                    $errorMessages[] = $errorMessage;
                }

                // $groupData[] = [
                //     'groupName' =>$groupName,
                //     'parentGroupName' => $parentGroupId,
                //     'organizationName' => $organizationId,
                //     'description' => $description
                // ];
                // $startcount++;
                if($i == 0)
                {
                    $group = GroupOrganization::where('org_id',$organizationId)->orderBy('group_code','DESC');
                    if($group->count() > 0){
                        $groupCode = $group->first()->group_code + 1;
                    }else{
                        $groupCode = $organizationId.'000000001';
                    }

                    $groupMaster = new GroupOrganization;
                    $groupMaster->group_name = $groupName;
                    $groupMaster->group_code = $groupCode;
                    $groupMaster->primary_group_id = $parentGroupId;
                    $groupMaster->org_id = $organizationId;
                    $groupMaster->description = $description;
                    $groupMaster->created_id = $authId;
                    $groupMaster->modified_id = $authId;
                    $groupMaster->save();

                    if(!empty($users)){
                        foreach($users as $userId){
                            
                            $userGroupAssignment = DB::table('lms_user_org_group')
                            ->where('is_active',1)
                            ->where('user_id',$userId)
                            ->where('org_id',$organizationId)
                            ->where('group_id',$groupMaster->group_id);
                            if($userGroupAssignment->count() > 0){


                            }else{
                                DB::table('lms_user_org_group')->insert([
                                    'group_id'=>$groupMaster->group_id,
                                    'org_id'=>$organizationId,
                                    'user_id'=>$userId,
                                    'date_created' => date('Y-m-d H:i:s'),
                                    'date_modified' => date('Y-m-d H:i:s'),
                                    'created_id' => $authId,
                                    'modified_id' => $authId,
                                ]);
                            }
                        }
                    }
                }
                
            }
 
             if($errors == 0){
                //  if(!empty($groupData)){
                //      foreach($groupData as $row){
                //         $group = GroupOrganization::orderBy('group_code','DESC');
                //         if($group->count() > 0){
                //             $groupCode = $group->first()->group_code + 1;
                //         }else{
                //             $groupCode = $organizationId.'000000001';
                //         }

                //          $groupMaster = new GroupOrganization;
                //          $groupMaster->group_name = $row['groupName'];
                //          $groupMaster->group_code = $groupCode;
                //          $groupMaster->primary_group_id = $row['parentGroupName'];
                //          $groupMaster->org_id = $row['organizationName'];
                //          $groupMaster->description = $row['description'];
                //          $groupMaster->created_id = $authId;
                //          $groupMaster->modified_id = $authId;
                //          $groupMaster->save();


                //      }
                //      return response()->json(['status'=>true,'code'=>200,'message'=>'Group has been imported successfully.'],200);
                //  }
                return response()->json(['status'=>true,'code'=>200,'message'=>'Group has been imported successfully.'],200);
             }else{
                 return response()->json(['status'=>false,'code'=>400,'errors'=>$errorMessages],400);
             }
        } catch (\Throwable $e) {
          return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function groupOrgExport(){

        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $groups = DB::table('lms_group_org as group')
        ->leftjoin('lms_group_org as parentGroup','group.group_id','=','parentGroup.group_id')
        ->leftjoin('lms_org_master as org','group.org_id','=','org.org_id')
        ->where('group.is_active','!=','0')
        ->where('org.is_active','!=','0')
        ->where('group.org_id',$organizationId)
        ->where('org.org_id',$organizationId)
        ->where('group.group_name','!=','Invisible groups')
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

    public function getOrgCategoryAssignGroupList($categoryId){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        $groupArray = [];
        $groupsArray = [];
        $groups = GroupOrganization::where('is_active','1')->where('group_name','!=','Invisible groups')->where('org_id',$organizationId);
        if($groups->count() > 0){
            foreach($groups->get() as $group){
                $groupAssignment = DB::table('lms_org_category_group_assignment')
                ->where('is_active','1')
                ->where('category_id',$categoryId)
                ->where('group_id','=',$group->group_id)
                ->where('org_id',$organizationId)
                ->where('user_id',$authId);
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

    public function getOrgGroupListByCategoryId($categoryId){
        $groupData = [];
        $organizationId = Auth::user()->org_id;
        $groups = GroupOrganization::where('is_active','1')->where('is_auto','0')->where('group_name','!=','Invisible groups')->where('org_id',$organizationId);
        if($groups->count() > 0){
            foreach($groups->get() as $group){

                $isChecked = 0;
                $groupAssignment = DB::table('lms_org_category_group_assignment')->where('is_active','1')->where('group_id',$group->group_id)->where('org_id',$organizationId)->where('category_id',$categoryId);
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

    public function getUserListByGroupId($groupId){
        $userData = [];
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;
        $users = User::
        leftJoin('lms_roles as role','lms_user_master.role_id','=','role.role_id')
        ->where('lms_user_master.is_active','1')
        ->where(function($query) use ($organizationId,$roleId,$authId){
            if($roleId != 1){

                $query->where('user.org_id',$organizationId);
                $query->where('user.role_id','>',$roleId);
                $userArray = userArray($authId,$roleId,$organizationId);
                $query->whereIn('user.user_id',$userArray);  

                //$query->where('user.org_id',$organizationId);
                //$query->where('user.created_id','=',$authId);

                // $userArray = DB::table('lms_user_master as user')
                // ->where('org_id',$organizationId)
                // ->where('role_id','>',$roleId)->pluck('user_id')->toArray();
                
                // $query->where('user.org_id',$organizationId);
                // $query->where('user.role_id','>',$roleId);
                // $query->where('user.created_id','=',$authId);
                // if(!empty($userArray)){
                //     $query->orWhereIn('user.created_id',$userArray);
                // }
                
                // if($roleId == 2){
                //     $query->where('lms_user_master.role_id','>','1');
                // }
                // if($roleId == 3){
                //     $query->where('lms_user_master.role_id','>','2');
                // }
                // if($roleId == 4){
                //     $query->where('lms_user_master.role_id','>','3');
                // }
                // if($roleId == 5){
                //     $query->where('lms_user_master.role_id','>','4');
                // }
                // if($roleId == 6){
                //     $query->where('lms_user_master.role_id','>','5');
                // }
                // $query->where('lms_user_master.org_id',$organizationId);
            }
        })
        ->select('lms_user_master.user_id','lms_user_master.first_name','lms_user_master.last_name','role.role_name');
        if($users->count() > 0){
            foreach($users->get() as $user){

                $isChecked = 0;
                $userGroup = DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$user->user_id)->where('org_id',$organizationId);
                if($userGroup->count() > 0){
                    $isChecked = $userGroup->first()->is_active;
                }

                $userData[] = [
                    'userId' => $user->user_id,
                    'fullName' => $user->first_name.''.$user->last_name,
                    'roleName' => $user->role_name,
                    'isChecked' => $isChecked
                ];
            }
            $array = array_column($userData, 'isChecked');
            array_multisort($array, SORT_DESC, $userData);
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$userData],200);
    }

    public function getAssigendUserListByGroupId($groupId){
        $userData = [];
        $organizationId = Auth::user()->org_id;
        $users = User::
        leftJoin('lms_roles as role','lms_user_master.role_id','=','role.role_id')
        ->where('lms_user_master.is_active','1')
        ->where('lms_user_master.org_id',$organizationId)
        ->select('lms_user_master.user_id','lms_user_master.first_name','lms_user_master.last_name','role.role_name');
        if($users->count() > 0){
            foreach($users->get() as $user){

                $isChecked = 0;
                $userGroup = DB::table('lms_user_org_group')->where('is_active','1')->where('group_id',$groupId)->where('user_id',$user->user_id)->where('org_id',$organizationId);
                if($userGroup->count() > 0){
                    $isChecked = $userGroup->first()->is_active;
                    $userData[] = [
                        'id' => $userGroup->first()->user_group_id,
                        'userId' => $user->user_id,
                        'fullName' => $user->first_name.''.$user->last_name,
                        'roleName' => $user->role_name,
                        'isChecked' => $isChecked
                    ];
                }

                
            }
            $array = array_column($userData, 'isChecked');
            array_multisort($array, SORT_DESC, $userData);
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$userData],200);
    }

    public function unassigendGroupUserById($id){
        $organizationId = Auth::user()->org_id;
        $userGroup = DB::table('lms_user_org_group')->where('is_active','1')->where('user_group_id',$id)->where('org_id',$organizationId);
        if($userGroup->count() > 0){

            $userGroup->update([
                'is_active'=>0
            ]);

        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'Group has been removed successfully.'],200);
    }


}
