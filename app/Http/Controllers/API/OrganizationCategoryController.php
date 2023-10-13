<?php

namespace App\Http\Controllers\API;

use App\Models\OrganizationCategory;
use App\Models\Organization;
use App\Models\GroupOrganization;
use App\Models\OrganizationTrainingLibrary;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use DB;

class OrganizationCategoryController extends BaseController
{

    public function getOrganizationCategoryList(Request $request)
    {
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;

        $sort = $request->has('sort') ? $request->get('sort') : 'lms_org_category.category_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';
        $suAssigned = $request->has('suAssigned') ? $request->get('suAssigned') : 0;

        $sortColumn = $sort;
        if($sort == 'categoryName'){
            $sortColumn = 'lms_org_category.category_name';
        }elseif($sort == 'categoryCode'){
            $sortColumn = 'lms_org_category.category_code';
        }elseif($sort == 'description'){
            $sortColumn = 'lms_org_category.description';
        }elseif($sort == 'isActive'){
            $sortColumn = 'lms_org_category.is_active';
        }

        // $organizationIds = Organization::where('is_active','!=','0')
        // ->where(function($query) use ($organizationId){
        //     $query->where('org_id',$organizationId);
        //     $query->orWhere('parent_org_id',$organizationId);
        // })
        // ->pluck('org_id');

        $organizationCategories = OrganizationCategory::join('lms_user_master as user','lms_org_category.created_id','=','user.user_id')
        ->where('lms_org_category.is_active','!=','0')
        ->where(function($query) use ($search){
            if($search != ''){
                $query->where('lms_org_category.category_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('lms_org_category.category_code', 'LIKE', '%'.$search.'%');
                if(in_array($search,['lms_org_category.active','act','acti','activ'])){
                    $query->orWhere('lms_org_category.is_active','1');
                }
                if(in_array($search,['lms_org_category.inactive','inact','inacti','inactiv'])){
                    $query->orWhere('is_active','2');
                }
            }
        })
        ->where(function($query) use ($suAssigned){
            if($suAssigned != ''){
                $query->where('lms_org_category.su_assigned',$suAssigned);
            }
        })
        ->where(function($query) use ($organizationId,$roleId,$authId){ 
            if($roleId != 1){

                $query->where('user.org_id',$organizationId);
                $query->where('user.role_id','>',$roleId - 1);
                $userArray = userArray($authId,$roleId,$organizationId);
                $query->whereIn('user.user_id',$userArray);  

                //$query->where('lms_org_category.created_id','=',$authId);
                //$query->where('lms_org_category.org_id',$organizationId);

                // $userArray = DB::table('lms_user_master as user')
                // ->where('org_id',$organizationId)
                // ->where('role_id','>',$roleId)->pluck('user_id')->toArray();
                
                // $query->where('user.org_id',$organizationId);
                // $query->where('user.role_id','>',$roleId);
                // $query->where('user.created_id','=',$authId);
                // if(!empty($userArray)){
                //     $query->orWhereIn('user.created_id',$userArray);
                // }
            }else{
                $query->where('lms_org_category.org_id',$organizationId);
            }
        })
        // ->where(function($query) use ($organizationIds,$roleId){
        //     if(!empty($organizationIds) && $roleId != 1){
        //         $query->whereIn('org_id',$organizationIds);
        //     }
        // })
        ->orderBy($sortColumn,$order)
        ->select('lms_org_category.category_id as categoryId', 'lms_org_category.category_name as categoryName', 'lms_org_category.category_code as categoryCode', 'lms_org_category.description', 'lms_org_category.org_id as organizationId','lms_org_category.su_assigned as suAssigned', 'lms_org_category.is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$organizationCategories],200);
    }


    public function addOrganizationCategory(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        if($request->categoryCode == ''){
            $validator = Validator::make($request->all(), [
                'categoryName' => 'max:150'
            ]);
        }else{
            $validator = Validator::make($request->all(), [
                'categoryName' => 'max:150',
                'categoryCode' => 'required|max:255',
                'description' => 'max:512',
                'isActive' => 'integer'
            ]);
        }

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{

            $organizationCategory = OrganizationCategory::where('is_active','!=','0')->where('org_id','=',$organizationId)
            ->where('category_name','LIKE','%'.$request->categoryName.'%');
            if($organizationCategory->count() > 0){
                return response()->json(['status'=>false,'code'=>404,'error'=>'Category name is already exist.'], 404);
            }

            $organizationCategory = OrganizationCategory::where('is_active','!=','0')->where('org_id','=',$organizationId)->where('su_assigned','0')
            ->where('category_code',$request->categoryCode);
            if($organizationCategory->count() > 0){
                return response()->json(['status'=>false,'code'=>404,'error'=>'Category code is already exist.'], 404);
            }

            if($request->categoryCode == ''){
                $organizationCategory = OrganizationCategory::where('is_active','!=','0')->where('org_id','=',$organizationId)->orderBy('category_code','DESC');
                if($organizationCategory->count() > 0){
                    $categoryCode = $organizationCategory->first()->category_code + 1;
                }else{
                    $categoryCode = 100000;
                }
            }else{
                $categoryCode = $request->categoryCode;
            }

            $organizationCategory = new OrganizationCategory;
            $organizationCategory->category_name = $request->categoryName;
            $organizationCategory->category_code = $categoryCode;
            $organizationCategory->description = $request->description;
            $organizationCategory->primary_category_id = ($request->primaryCategory != '') ? $request->primaryCategory : Null;
            $organizationCategory->org_id = $organizationId;
            $organizationCategory->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $organizationCategory->created_id = $authId;
            $organizationCategory->modified_id = $authId;
            $organizationCategory->save();

            return response()->json(['status'=>true,'code'=>201,'data'=>$organizationCategory->category_id,'message'=>'Category has been created successfully.'],201);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function getOrganizationCategoryById($categoryId)
    {
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        // $organizationIds = Organization::where('is_active','!=','0')
        // ->where(function($query) use ($organizationId){
        //     $query->where('org_id',$organizationId);
        //     $query->orWhere('parent_org_id',$organizationId);
        // })
        // ->pluck('org_id');

        try{
            $organizationCategoryRedis = Redis::get('organizationCategoryRedis' . $categoryId);
            if(isset($organizationCategoryRedis)){
                return response()->json(['status'=>true,'code'=>200,'data'=>json_decode($organizationCategoryRedis,false)],200);
            }else{
                $organizationCategory = OrganizationCategory::
                leftJoin('lms_org_category as primaryCategory', 'lms_org_category.primary_category_id', '=', 'primaryCategory.category_id')
                ->where('lms_org_category.is_active','!=','0')
                ->where('lms_org_category.org_id',$organizationId)
                // ->where(function($query) use ($organizationIds,$roleId){
                //     if(!empty($organizationIds) && $roleId != 1){
                //         $query->whereIn('lms_org_category.org_id',$organizationIds);
                //     }
                // })
                ->where('lms_org_category.category_id',$categoryId);
                if ($organizationCategory->count() < 1) {
                    return response()->json(['status'=>false,'code'=>404,'error'=>'Category is not found.'], 404);
                }
                $organizationCategory = $organizationCategory->select('lms_org_category.category_id as categoryId', 'lms_org_category.category_name as categoryName', 'lms_org_category.category_code as categoryCode', 'lms_org_category.primary_category_id as primaryCategoryId', 'primaryCategory.category_name as primaryCategoryName', 'lms_org_category.description', 'lms_org_category.is_active as isActive')->first();
                Redis::set('organizationCategoryRedis' . $categoryId, json_encode($organizationCategory,false));
                return response()->json(['status'=>true,'code'=>200,'data'=>$organizationCategory],200);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function updateOrganizationCategory(Request $request)
    {
        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        $organizationIds = Organization::where('is_active','!=','0')
        ->where(function($query) use ($organizationId){
            $query->where('org_id',$organizationId);
            $query->orWhere('parent_org_id',$organizationId);
        })
        ->pluck('org_id');

        $validator = Validator::make($request->all(), [
            'categoryId'=>'required|integer',
            'categoryName' => 'max:150',
            //'categoryCode' => 'required|max:36|unique:lms_org_category,category_code,'.$request->categoryId.',category_id',
            'description' => 'max:512',
            'isActive' => 'integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        try{

            $organizationCategory = OrganizationCategory::where('is_active','!=','0')
            ->where(function($query) use ($organizationIds,$roleId){
                if(!empty($organizationIds) && $roleId != 1){
                    $query->whereIn('org_id',$organizationIds);
                }
            })
            ->where('category_id',$request->categoryId);
            if ($organizationCategory->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Category is not found.'], 404);
            }
            
            $organizationCategory->update([
                'category_name' => $request->categoryName,
                //'category_code' => $request->categoryCode,
                'description' => $request->description,
                'primary_category_id' => ($request->primaryCategory != '') ? $request->primaryCategory : Null,
                'is_active' => $request->isActive == '' ? $organizationCategory->first()->is_active ? $organizationCategory->first()->is_active : '1' : $request->isActive,
                'modified_id' => $authId
            ]);

            $organizationCategory = OrganizationCategory::
                leftJoin('lms_org_category as primaryCategory', 'lms_org_category.primary_category_id', '=', 'primaryCategory.category_id')
                ->where('lms_org_category.is_active','!=','0')
                ->where(function($query) use ($organizationIds,$roleId){
                    if(!empty($organizationIds) && $roleId != 1){
                        $query->whereIn('lms_org_category.org_id',$organizationIds);
                    }
                })
                ->where('lms_org_category.category_id',$request->categoryId)
                ->select('lms_org_category.category_id as categoryId', 'lms_org_category.category_name as categoryName', 'lms_org_category.category_code as categoryCode', 'lms_org_category.primary_category_id as primaryCategoryId', 'primaryCategory.category_name as primaryCategoryName', 'lms_org_category.description', 'lms_org_category.is_active as isActive')->first();
            
            Redis::del('organizationCategoryRedis' . $request->categoryId);
            Redis::set('organizationCategoryRedis' . $request->categoryId, $organizationCategory);

            return response()->json(['status'=>true,'code'=>200,'message'=>'Category has been updated successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function deleteOrganizationCategory(Request $request)
    {
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        $organizationIds = Organization::where('is_active','!=','0')
        ->where(function($query) use ($organizationId){
            $query->where('org_id',$organizationId);
            $query->orWhere('parent_org_id',$organizationId);
        })
        ->pluck('org_id');

        $validator = Validator::make($request->all(), [
            'categoryId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $organizationCategory = OrganizationCategory::where('is_active','!=','0')
            ->where(function($query) use ($organizationIds,$roleId){
                if(!empty($organizationIds) && $roleId != 1){
                    $query->whereIn('org_id',$organizationIds);
                }
            })
            ->where('category_id',$request->categoryId);
            if($organizationCategory->count() > 0){

                $organizationCategory->update([
                    'is_active' => '0',
                ]);

                Redis::del('organizationCategoryRedis' . $request->categoryId);

                return response()->json(['status'=>true,'code'=>200,'message'=>'Category has been deleted successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'Category is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function bulkDeleteOrganizationCategory(Request $request)
    {
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        $organizationIds = Organization::where('is_active','!=','0')
        ->where(function($query) use ($organizationId){
            $query->where('org_id',$organizationId);
            $query->orWhere('parent_org_id',$organizationId);
        })
        ->pluck('org_id');

        $validator = Validator::make($request->all(), [
            'categoryIds'=>'required|array'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $organizationCategory = OrganizationCategory::where('is_active','!=','0')
            ->where(function($query) use ($organizationIds,$roleId){
                if(!empty($organizationIds) && $roleId != 1){
                    $query->whereIn('org_id',$organizationIds);
                }
            })
            ->whereIn('category_id',$request->categoryIds);
            if($organizationCategory->count() > 0){

                $organizationCategory->update([
                    'is_active' => '0',
                ]);

                if(!empty($request->categoryIds)){
                    foreach($request->categoryIds as $categoryId){
                        Redis::del('organizationCategoryRedis' . $categoryId);
                    }
                }
                return response()->json(['status'=>true,'code'=>200,'message'=>'Category has been deleted successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'Category is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function getOrganizationPrimaryCategoryList()
    {
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;

        // $organizationIds = Organization::where('is_active','!=','0')
        // ->where(function($query) use ($organizationId){
        //     $query->where('org_id',$organizationId);
        //     $query->orWhere('parent_org_id',$organizationId);
        // })
        // ->pluck('org_id');

        $categoryMaster = OrganizationCategory::join('lms_user_master as user','lms_org_category.created_id','=','user.user_id')
        ->where('lms_org_category.is_active','1')
        ->whereNull('lms_org_category.primary_category_id')
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

                //$query->where('lms_org_category.created_id','=',$authId);
                //$query->where('lms_org_category.org_id',$organizationId);
            }else{
                $query->where('lms_org_category.org_id',$organizationId);
            }
        })
        // ->where(function($query) use ($organizationIds,$roleId){
        //     if(!empty($organizationIds) && $roleId != 1){
        //         $query->whereIn('org_id',$organizationIds);
        //     }
        // })
        ->orderBy('lms_org_category.category_name','ASC')
        ->select('lms_org_category.category_id as categoryId', 'lms_org_category.category_name as categoryName')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$categoryMaster],200);
    }

    public function getOrganizationCategoryOptionList(){

        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;
        // $organizationIds = Organization::where('is_active','!=','0')
        // ->where(function($query) use ($organizationId){
        //     $query->where('org_id',$organizationId);
        //     $query->orWhere('parent_org_id',$organizationId);
        // })
        // ->pluck('org_id');

        $categories = OrganizationCategory::join('lms_user_master as user','lms_org_category.created_id','=','user.user_id')
        ->where('lms_org_category.is_active','1')
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

                //$query->where('lms_org_category.created_id','=',$authId);
                //$query->where('lms_org_category.org_id',$organizationId);
            }else{
                $query->where('lms_org_category.org_id',$organizationId);
            }
        })
        // ->where(function($query) use ($organizationIds,$roleId){
        //     if(!empty($organizationIds) && $roleId != 1){
        //         $query->whereIn('org_id',$organizationIds);
        //     }
        // })
        ->orderBy('lms_org_category.category_name','ASC')->select('lms_org_category.category_id as categoryId', 'lms_org_category.category_name as categoryName')->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$categories],200);
    }

    public function getCategoryGroupList(Request $request){

        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;

        // $organizationIds = Organization::where('is_active','!=','0')
        // ->where(function($query) use ($organizationId){
        //     $query->where('org_id',$organizationId);
        //     $query->orWhere('parent_org_id',$organizationId); 
        // })
        // ->pluck('org_id');

        if($roleId == 1){
            $table = 'category_group_assignment';
        }else{
            $table = 'lms_org_category_group_assignment';
        }

        $groups = DB::table('lms_group_org')
        ->leftJoin($table,'lms_group_org.group_id','=',$table.'.group_id')
        ->where('lms_group_org.is_active','!=','0')
        ->where(function($query) use ($organizationId,$roleId,$authId,$table){
            if(!empty($organizationId) && $roleId != 1){
                $query->where($table.'.org_id',$organizationId);
                $query->where($table.'.user_id',$authId);
            }
        })
        ->select('lms_group_org.group_id as groupId','lms_group_org.group_name as groupName','lms_group_org.group_code as groupCode',
        DB::raw('(CASE WHEN '.$table.'.is_active = 1 THEN 1 ELSE 0 END) AS isChecked'))
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$groups],200);
    }

    public function orgCategoryGroupAssignment(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'categoryId' => 'required',
            'groups' => 'required|array'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        if(isset($request->categoryId)){

            if(is_array($request->groups)){

                if(count($request->groups) > 0){

                    foreach($request->groups as $group){

                        $groupId = $group['groupId'];
                        $isChecked = $group['isChecked'];

                        $categoryGroupAssignment = DB::table('lms_org_category_group_assignment')->where('group_id',$groupId)->where('user_id',$authId)
                        ->where('category_id',$request->categoryId);
                        if($categoryGroupAssignment->count() > 0){

                            $categoryGroupAssignment->update([
                                'is_active'=>$isChecked,
                                'date_modified' => date('Y-m-d H:i:s'),
                                'modified_id' => $authId
                            ]);

                            $parentGroupMaster = GroupOrganization::where('primary_group_id',$groupId)->where('org_id',$organizationId)->where('is_active','1');
                            if($parentGroupMaster->count() > 0){
                                foreach($parentGroupMaster->get() as $parentGroup){

                                    $categoryGroupAssignment = DB::table('lms_org_category_group_assignment')->where('group_id',$parentGroup->group_id)->where('org_id',$organizationId)->where('user_id',$authId)
                                    ->where('category_id',$request->categoryId);
                                    if($categoryGroupAssignment->count() > 0){
        
                                        $categoryGroupAssignment->update([
                                            'is_active'=>$isChecked,
                                            'date_modified' => date('Y-m-d H:i:s'),
                                            'modified_id' => $authId
                                        ]);
                                    }

                                }
                            }
    
                        }else{
                            DB::table('lms_org_category_group_assignment')->insert([
                                'category_id'=>$request->categoryId,
                                'group_id'=>$groupId,
                                'is_active'=>$isChecked,
                                'org_id'=>$organizationId,
                                'user_id'=>$authId,
                                'date_created' => date('Y-m-d H:i:s'),
                                'date_modified' => date('Y-m-d H:i:s'),
                                'created_id' => $authId,
                                'modified_id' => $authId
                            ]);

                            $parentGroupMaster = GroupOrganization::where('primary_group_id',$groupId)->where('org_id',$organizationId)->where('is_active','1');
                            if($parentGroupMaster->count() > 0){
                                foreach($parentGroupMaster->get() as $parentGroup){
                                    DB::table('lms_org_category_group_assignment')->insert([
                                        'category_id'=>$request->categoryId,
                                        'group_id'=>$parentGroup->group_id,
                                        'is_active'=>$isChecked,
                                        'org_id'=>$organizationId,
                                        'user_id'=>$authId,
                                        'date_created' => date('Y-m-d H:i:s'),
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'created_id' => $authId,
                                        'modified_id' => $authId
                                    ]);
                                }
                            }
                        }
                    }
                    return response()->json(['status'=>true,'code'=>200,'message'=>'Category assigned to group successfully.'],200);
                }
            }
        }
    }

    public function orgBulkCourseCategoryAssign(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'categoryIds' => 'required|array',
            'courseLibraryIds' => 'required|array'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $reqCategoryIds = OrganizationCategory::where('is_active','1')
        ->where('org_id',$organizationId)
        ->whereIn('category_id',$request->categoryIds)
        ->orWhereIn('primary_category_id',$request->categoryIds)->pluck('category_id')->toArray();

        $trainingLibrary = OrganizationTrainingLibrary::whereIn('training_id',$request->courseLibraryIds); 
        if($trainingLibrary->count() > 0){
            // $trainingLibrary->update([
            //     'category_id' => implode(',',$request->categoryIds)
            // ]); 
            foreach($trainingLibrary->get() as $row){
                $categoryIds = $row->category_id;
                if(!empty($categoryIds)){
                    $newCategoryIds = array_unique(array_merge(explode(',',$categoryIds),$reqCategoryIds));
                    $trainingLibrary->update([
                        'category_id' => implode(',',$newCategoryIds)
                    ]);
                }else{
                    $trainingLibrary->update([
                        'category_id' => implode(',',$reqCategoryIds)
                    ]); 
                }
            }
                
        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'Category assigned successfully.'],200);
    }

    public function orgCourseCategoryUnassign(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'categoryId' => 'required',
            'courseLibraryId' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try {

            $trainingLibrary = OrganizationTrainingLibrary::where('training_id',$request->courseLibraryId); 
            if($trainingLibrary->count() > 0){
                $categoryIds = $trainingLibrary->first()->category_id;
                if(!empty($categoryIds)){
                    $newCategoryIds = array_diff(explode(',',$categoryIds),[$request->categoryId]);
                    $trainingLibrary->update([
                        'category_id' => implode(',',$newCategoryIds)
                    ]);
                }  
            }
            return response()->json(['status'=>true,'code'=>200,'message'=>'Category unassigned successfully.'],200);

        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function getOrgAssignCourseListByCategoryId(Request $request){

        $validator = Validator::make($request->all(), [
            'categoryIds' => 'required|array'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        $categoryIds = $request->categoryIds;

        $trainingLibrary = DB::table('lms_org_training_library as trainingLibrary')
        ->join('lms_training_types as trainingType','trainingLibrary.training_type_id','=','trainingType.training_type_id')
        ->join('lms_training_status as trainingStatus','trainingLibrary.training_status_id','=','trainingStatus.training_status_id')
        ->where('trainingLibrary.is_active','!=','0')
        ->where(function($query) use ($categoryIds){
            if(!empty($categoryIds)){
                foreach($categoryIds as $categoryId){
                    $query->whereRaw('Find_IN_SET(?, trainingLibrary.category_id)', $categoryId);
                }
            }
        })
        ->select('trainingLibrary.training_id as courseLibraryId','trainingLibrary..training_name as courseTitle','trainingType.training_type as trainingType','trainingStatus.training_status as status')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$trainingLibrary],200);
    }

    public function getOrgUnAssignCourseListByCategoryId(Request $request){
        $organizationId = Auth::user()->org_id;
        $validator = Validator::make($request->all(), [
            'categoryIds' => 'required|array'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        $categoryIds = $request->categoryIds;
        $trainingLibrary = DB::table('lms_org_training_library as trainingLibrary')
        ->join('lms_training_types as trainingType','trainingLibrary.training_type_id','=','trainingType.training_type_id')
        ->join('lms_training_status as trainingStatus','trainingLibrary.training_status_id','=','trainingStatus.training_status_id')
        ->where('trainingLibrary.is_active','!=','0')
        ->where('org_id',$organizationId)
        ->where(function($query) use ($categoryIds){
            if(!empty($categoryIds)){
                foreach($categoryIds as $categoryId){
                    $query->orWhereRaw('NOT Find_IN_SET(?, trainingLibrary.category_id)', $categoryId);
                }
            }
        })
        
        ->select('trainingLibrary.training_id as courseLibraryId','trainingLibrary..training_name as courseTitle','trainingType.training_type as trainingType','trainingStatus.training_status as status')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$trainingLibrary],200);
    }

}
