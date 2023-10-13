<?php

namespace App\Http\Controllers\API;

use App\Models\CategoryMaster;
use App\Models\GroupMaster;
use App\Models\OrganizationCategory;
use App\Models\TrainingLibrary;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use DB;

class CategoryMasterController extends BaseController
{

    public function getGenericCategoryList(Request $request)
    {
        $sort = $request->has('sort') ? $request->get('sort') : 'category_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if($sort == 'categoryName'){
            $sortColumn = 'category_name';
        }elseif($sort == 'categoryCode'){
            $sortColumn = 'category_code';
        }elseif($sort == 'description'){
            $sortColumn = 'description';
        }elseif($sort == 'isActive'){
            $sortColumn = 'is_active';
        }

        $categoryMaster = DB::table('lms_category_master')
        ->where('is_active','!=','0')
        ->where(function($query) use ($search){
            if($search != ''){
                $query->where('category_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('category_code', 'LIKE', '%'.$search.'%');
                if(in_array($search,['active','act','acti','activ'])){
                    $query->orWhere('is_active','1');
                }
                if(in_array($search,['inactive','inact','inacti','inactiv'])){
                    $query->orWhere('is_active','2');
                }
            }
        })
        ->orderBy($sortColumn,$order)
        ->select('category_id as categoryId', 'category_name as categoryName', 'category_code as categoryCode', 'description', 'is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$categoryMaster],200);
    }


    public function addNewCategory(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        if($request->categoryCode == ''){
            $validator = Validator::make($request->all(), [
                'categoryName' => 'required|max:150|unique:lms_category_master,category_name,null,null,is_active,!0',
            ]);
        }else{
            $validator = Validator::make($request->all(), [
                'categoryName' => 'required|max:150|unique:lms_category_master,category_name,null,null,is_active,!0',
                'categoryCode' => 'required|max:255|unique:lms_category_master,category_code,null,null,is_active,!0',
                'description' => 'max:512',
                'isActive' => 'integer'
            ]);

        }

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{

            if($request->categoryCode == ''){
                $categoryMaster = CategoryMaster::where('is_active','!=','0')->orderBy('category_code','DESC');
                if($categoryMaster->count() > 0){
                    $categoryCode = $categoryMaster->first()->category_code + 1;
                }else{
                    $categoryCode = 100000;
                }
            }else{
                $categoryCode = $request->categoryCode;
            }
            
            $categoryMaster = new CategoryMaster;
            $categoryMaster->category_name = $request->categoryName;
            $categoryMaster->category_code = $categoryCode;
            $categoryMaster->description = $request->description;
            $categoryMaster->primary_category_id = ($request->primaryCategory != '') ? $request->primaryCategory : Null;
            $categoryMaster->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $categoryMaster->created_id = $authId;
            $categoryMaster->modified_id = $authId;
            $categoryMaster->save();

            return response()->json(['status'=>true,'code'=>201,'data'=>$categoryMaster->category_id,'message'=>'Category has been created successfully.'],201);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function getCategoryById($categoryId)
    {
        try{
            $categoryMasterRedis = Redis::get('categoryMasterRedis' . $categoryId);
            if(isset($categoryMasterRedis)){
                return response()->json(['status'=>true,'code'=>200,'data'=>json_decode($categoryMasterRedis,false)],200);
            }else{
                $categoryMaster = DB::table('lms_category_master as category')
                ->leftJoin('lms_category_master as primaryCategory', 'category.primary_category_id', '=', 'primaryCategory.category_id')
                ->where('category.is_active','!=','0')->where('category.category_id',$categoryId);
                if ($categoryMaster->count() < 1) {
                    return response()->json(['status'=>false,'code'=>404,'error'=>'Category is not found.'], 404);
                }
                //$categoryMaster = $categoryMaster->select('category.category_id as categoryId', 'category.category_name as categoryName', 'category.category_code as categoryCode', 'category.description', 'category.is_active as isActive')->first();
                $categoryMaster = $categoryMaster->select('category.category_id as categoryId', 'category.category_name as categoryName', 'category.category_code as categoryCode', 'category.primary_category_id as primaryCategoryId', 'primaryCategory.category_name as primaryCategoryName', 'category.description', 'category.is_active as isActive')->first();
                Redis::set('categoryMasterRedis' . $categoryId, json_encode($categoryMaster,false));
                return response()->json(['status'=>true,'code'=>200,'data'=>$categoryMaster],200);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function updateCategoryById(Request $request)
    {
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'categoryId'=>'required|integer',
            'categoryName' => 'required|max:150|unique:lms_category_master,category_name,'.$request->categoryId.',category_id,is_active,!0',
            //'categoryCode' => 'required|max:36|unique:lms_category_master,category_code,'.$request->categoryId.',category_id',
            'description' => 'max:512',
            'isActive' => 'integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        try{
            $categoryMaster = CategoryMaster::where('is_active','!=','0')->where('category_id',$request->categoryId);
            if ($categoryMaster->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Category is not found.'], 404);
            }
            
            $categoryMaster->update([
                'category_name' => $request->categoryName,
                //'category_code' => $request->categoryCode,
                'primary_category_id' => ($request->primaryCategory != '') ? $request->primaryCategory : Null,
                'description' => $request->description,
                'is_active' => $request->isActive == '' ? $categoryMaster->first()->is_active ? $categoryMaster->first()->is_active : '1' : $request->isActive,
                'modified_id' => $authId
            ]);

            $categoryMaster = DB::table('lms_category_master as category')
            ->leftJoin('lms_category_master as primaryCategory', 'category.primary_category_id', '=', 'primaryCategory.category_id')
            ->where('category.is_active','!=','0')
            ->where('category.category_id',$request->categoryId)
            ->select('category.category_id as categoryId', 'category.category_name as categoryName', 'category.category_code as categoryCode', 'category.primary_category_id as primaryCategoryId', 'primaryCategory.category_name as primaryCategoryName', 'category.description', 'category.is_active as isActive')
            //->select('category.category_id as categoryId', 'category.category_name as categoryName', 'category.category_code as categoryCode', 'category.description', 'category.is_active as isActive')
            ->first();
            
            Redis::del('categoryMasterRedis' . $request->categoryId);
            Redis::set('categoryMasterRedis' . $request->categoryId, json_encode($categoryMaster,false));

            return response()->json(['status'=>true,'code'=>200,'message'=>'Category has been updated successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function deleteCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'categoryId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $categoryMaster = CategoryMaster::where('is_active','!=','0')->where('category_id',$request->categoryId);
            if($categoryMaster->count() > 0){

                $categoryMaster->update([
                    'is_active' => '0',
                ]);

                Redis::del('categoryMasterRedis' . $request->categoryId);

                return response()->json(['status'=>true,'code'=>200,'message'=>'Category has been deleted successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'Category is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function bulkDeleteGenericCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'categoryIds'=>'required|array'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $categoryMaster = CategoryMaster::where('is_active','!=','0')->whereIn('category_id',$request->categoryIds);
            if($categoryMaster->count() > 0){

                $categoryMaster->update([
                    'is_active' => '0',
                ]);

                if(!empty($request->categoryIds)){

                    foreach($request->categoryIds as $categoryId){
                        Redis::del('categoryMasterRedis' . $categoryId);
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


    public function getPrimaryCategoryList()
    {
        $categoryMaster = CategoryMaster::where('is_active','1')
        ->whereNull('primary_category_id')
        ->orderBy('category_name','ASC')
        ->select('category_id as categoryId', 'category_name as categoryName')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$categoryMaster],200);
    }

    public function getCategoryOptionList(){
        $categories = CategoryMaster::where('is_active','1')->orderBy('category_name','ASC')->select('category_id as categoryId', 'category_name as categoryName')->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$categories],200);
    }
    
    public function getUserCategoryList(Request $request){

        $validator = Validator::make($request->all(), [
            'userId' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $userId = $request->userId;
        $organizationId = Auth::user()->org_id;

        $categories = DB::table('lms_category_master as category')
        //->where('category.org_id',$organizationId)
        ->select('category.category_id as categoryId', 'category.category_name as categoryName')
        ->get();
        if($categories->count() > 0){
            foreach($categories as $category){
                $userCategory = DB::table('lms_user_category')->where('category_id',$category->categoryId)->where('user_id',$userId)->where('org_id',$organizationId);
                if($userCategory->count() > 0){
                    $category->isActive = $userCategory->first()->is_active;
                }else{
                    $category->isActive = '';
                }
            }
        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$categories],200);
    }

    public function addUserCategory(Request $request){

        $validator = Validator::make($request->all(), [
            'userId' => 'required|integer',
            'categories' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $authId = Auth::user()->user_id;
        $userId = $request->userId;
        $organizationId = Auth::user()->org_id;

        if(isset($request->categories)){
            
            if(is_array($request->categories)){

                if(count($request->categories) > 0){

                    foreach($request->categories as $category){
                        $categoryId = $category['categoryId'];
                        $checked = $category['checked'];
                        $userCategory = DB::table('lms_user_category')->where('category_id',$categoryId)->where('user_id',$userId)->where('org_id',$organizationId);
                        if($userCategory->count() > 0){
                            $userCategory->update(['is_active' => $checked]);
                        }else{
                            DB::table('lms_user_category')->insert([
                                'is_active' => '1',
                                'category_id' => $categoryId,
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
        return response()->json(['status'=>true,'code'=>200,'message'=>'User category has been created successfully.'],200);
    }

    public function categoryGroupAssignment(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'categoryId' => 'required',
            'groups' => 'required'
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

                        $categoryGroupAssignment = DB::table('category_group_assignment')->where('group_id',$groupId)
                        ->where('category_id',$request->categoryId);
                        if($categoryGroupAssignment->count() > 0){

                            $categoryGroupAssignment->update([
                                'is_active'=>$isChecked,
                                'date_modified' => date('Y-m-d H:i:s'),
                                'modified_id' => $authId
                            ]);

                            $parentGroupMaster = GroupMaster::where('primary_group_id',$groupId)->where('is_active','1');
                            if($parentGroupMaster->count() > 0){
                                foreach($parentGroupMaster->get() as $parentGroup){

                                    $categoryGroupAssignment = DB::table('category_group_assignment')->where('group_id',$parentGroup->group_id)
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
                            DB::table('category_group_assignment')->insert([
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

                            $parentGroupMaster = GroupMaster::where('primary_group_id',$groupId)->where('is_active','1');
                            if($parentGroupMaster->count() > 0){
                                foreach($parentGroupMaster->get() as $parentGroup){
                                    DB::table('category_group_assignment')->insert([
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


    public function bulkCourseCategoryAssign(Request $request){
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

        $reqCategoryIds = CategoryMaster::where('is_active','1')
        ->whereIn('category_id',$request->categoryIds)
        ->orWhereIn('primary_category_id',$request->categoryIds)->pluck('category_id')->toArray();

        $trainingLibrary = TrainingLibrary::whereIn('training_id',$request->courseLibraryIds); 
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

    public function courseCategoryUnassign(Request $request){
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

            $trainingLibrary = TrainingLibrary::where('training_id',$request->courseLibraryId); 
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

    public function getAssignCourseListByCategoryId(Request $request){

        $validator = Validator::make($request->all(), [
            'categoryIds' => 'required|array'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        $categoryIds = $request->categoryIds;

        $trainingLibrary = DB::table('lms_training_library as trainingLibrary')
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

    public function getUnAssignCourseListByCategoryId(Request $request){
        $validator = Validator::make($request->all(), [
            'categoryIds' => 'required|array'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        $categoryIds = $request->categoryIds;
        $trainingLibrary = DB::table('lms_training_library as trainingLibrary')
        ->join('lms_training_types as trainingType','trainingLibrary.training_type_id','=','trainingType.training_type_id')
        ->join('lms_training_status as trainingStatus','trainingLibrary.training_status_id','=','trainingStatus.training_status_id')
        ->where('trainingLibrary.is_active','!=','0')
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

    public function categoryAssignToOrgCategory(Request $request){
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'categoryIds' => 'required|array',
            'organizationIds' => 'required|array',
            'organizationIds.*.organizationId' => 'required|integer',
            'organizationIds.*.isChecked' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        if(is_array($request->categoryIds) && is_array($request->organizationIds)){
            
            if(!empty($request->categoryIds) && !empty($request->organizationIds)){
                foreach($request->organizationIds as $organization){

                    $organizationId = $organization['organizationId'];
                    $isChecked = $organization['isChecked'];

                    $organizationCategory = OrganizationCategory::whereIn('category_master_id',$request->categoryIds)->where('org_id',$organizationId);
                    if($organizationCategory->count() > 0){
                        
                    }else{

                        $categoryMasters = CategoryMaster::whereIn('category_id',$request->categoryIds);
                        if($categoryMasters->count() > 0){
                            foreach($categoryMasters->get() as $categoryMaster){

                                $organizationCategory = OrganizationCategory::where('org_id','=',$organizationId)->orderBy('category_code','DESC');
                                if($organizationCategory->count() > 0){
                                    $categoryCode = $organizationCategory->first()->category_code + 1;
                                }else{
                                    $categoryCode = 100000;
                                }

                                $category = new OrganizationCategory;
                                $category->category_master_id = $categoryMaster->category_id;
                                $category->category_code = $categoryCode;
                                $category->category_name = $categoryMaster->category_name;
                                $category->description = $categoryMaster->description;
                                $category->is_active = $categoryMaster->is_active;
                                $category->su_assigned = 1;
                                $category->org_id = $organizationId;
                                $category->created_id = $authId;
                                $category->modified_id = $authId;
                                $category->save();

                            }
                        }
                    } 
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'Category assigned to organization category successfully.'],200);
    }



}
