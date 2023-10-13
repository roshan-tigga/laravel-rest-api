<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Models\Login;
use App\Models\Organization;
use App\Models\NotificationMaster;
use App\Models\OrganizationNotification;

use App\Models\MenuMaster;
use App\Models\ModuleMaster;
use App\Models\ActionMaster;
use App\Models\MenuPermission;
use App\Models\Permission;
use App\Models\RoleMaster;

use App\Models\Location;
use App\Models\JobTitle;
use App\Models\Division;
use App\Models\Area;

use App\Models\OrganizationUserCustomField;
use App\Models\OrganizationCustomField;
use App\Models\OrganizationCustomNumberOfField;

use App\Models\GroupOrganization;
use App\Models\GroupOrganizationSetting;
use Illuminate\Validation\Rule;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use Carbon\Carbon;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Hash;
use DB;
use Illuminate\Support\Facades\Storage;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;


use App\Mail\WelcomeMail;
use Mail;

use Illuminate\Auth\Events\Registered;

class UserController extends Controller
{

    public function getUserList(Request $request)
    {
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;

        $sort = $request->has('sort') ? $request->get('sort') : 'user.user_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';

        $sortColumn = $sort;


        $users = DB::table('lms_user_master as user')
        ->leftJoin('lms_user_login as login','user.user_id','=','login.user_id')
        ->leftJoin('lms_roles as role','user.role_id','=','role.role_id')
        ->leftJoin('lms_org_master as org','user.org_id','=','org.org_id')
        ->where(function($query) use ($request){
            if($request->query('userType') != ''){
                if($request->query('userType') == 'activeUser'){
                    $query->where('user.is_active','1');
                }
                if($request->query('userType') == 'deletedUser'){
                    $query->where('user.is_active','0');
                }
                if($request->query('userType') == 'inactivatedUser'){
                    $query->where('user.is_active','2');
                }
                if($request->query('userType') == 'archivedUser'){
                    $query->orWhere('user.is_active','3');
                }
            }
        })
        ->where('user.is_active','!=','4')
        ->where(function($query) use ($organizationId,$roleId,$authId){
            if($roleId != 1){
                $query->where('user.org_id',$organizationId);
                $query->where('user.role_id','>',$roleId);
                $userArray = userArray($authId,$roleId,$organizationId);
                $query->whereIn('user.user_id',$userArray);                
            }
        })
        ->orderBy($sortColumn,$order)
        ->select('user.user_id as userId', 'user.first_name as firstName', 'user.last_name as lastName', 
        'login.user_name as username', 'user.email_id as email', 'user.phone_number as phone', 'role.role_name as roleName', 'org.organization_name as organizationName',
        DB::raw('(CASE WHEN user.is_active = 1 THEN "Active"
        WHEN user.is_active = 2 THEN "Inactive"
        WHEN user.is_active = 3 THEN "Archived"
        ELSE "Deleted" END) AS isActive')
        )
        ->get();

        if($users->count() > 0){
            foreach($users as $user){
                $user->groupsAssiged = DB::table('lms_user_org_group as userGroup')
                ->leftjoin('lms_group_org as group','userGroup.group_id','=','group.group_id')
                ->where('userGroup.is_active','1')
                ->where('group.is_active','1')
                ->where('userGroup.user_id',$user->userId)
                ->where('userGroup.org_id',$organizationId)
                ->pluck('group.group_name');

                $user->categoryAssiged = DB::table('lms_org_user_category_assignment as categoryAssignment')
                    ->leftjoin('lms_org_category as category','categoryAssignment.category_id','=','category.category_id')
                    ->where('categoryAssignment.is_active','1')
                    ->where('category.is_active','1')
                    ->where('categoryAssignment.user_id',$user->userId)
                    ->where('categoryAssignment.org_id',$organizationId)
                    ->pluck('category.category_name as categoryName');
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$users],200);
    }

    public function addNewUser(Request $request)
    {
        $authId = Auth::user()->user_id;
        $domainId = Auth::user()->domain_id;

        $roleId = Auth::user()->user->role_id;
        $organizationId = $request->organizationId; //Auth::user()->org_id;
        
        
        $validator = Validator::make($request->all(), [
            'organizationId' => 'required|integer',
            'firstName' => 'required|max:32',
            'lastName' => 'required|max:32',
            'jobTitle' => 'nullable|max:64',
            'emailAddress' => 'nullable|max:256|regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix|unique:lms_user_master,email_id',
            'barcode' => 'nullable|max:24',
            'phoneNumber' => 'nullable|max:24',
            'division' => 'nullable|max:64',
            'area' => 'nullable|max:64',
            'location' => 'nullable|max:64',
            'role' => 'required|integer',
            'userName' => 'required|max:48',
            'password' => 'required|string|min:8',
            'isActive' => 'integer',
            'userPhoto' => 'nullable|mimes:jpeg,jpg,png'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $userNameCheck = user::join('lms_user_login','lms_user_master.user_id','=','lms_user_login.user_id')
        ->where('lms_user_master.org_id',$organizationId)->where('lms_user_login.org_id',$organizationId)
        ->where('lms_user_login.user_name', 'like',$request->userName)
        ->where('lms_user_master.is_active','!=','4')->count();
        if($userNameCheck > 0){
            return response()->json(['status'=>false,'code'=>404,'error'=>'Username is already exist.'], 404);
        }

        try{

            $jobTitleArray = [];
            $jobTitleIds = Null;
            $explodeJobTitleIds = Null;
            if(!empty($request->jobTitle)){
                $explodeJobTitleIds = explode(',',$request->jobTitle);
                if(!empty($explodeJobTitleIds)){
                    foreach($explodeJobTitleIds as $explodeJobTitleId){

                        $jobTitle = JobTitle::where('job_title_id',$explodeJobTitleId)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($jobTitle->count() > 0){
                            $jobTitleArray[] = $explodeJobTitleId;
                        }else{
                            $jobTitle = new JobTitle;
                            $jobTitle->job_title_name = $explodeJobTitleId;
                            $jobTitle->is_active = 1;
                            $jobTitle->org_id = $organizationId;
                            $jobTitle->created_id = $authId;
                            $jobTitle->modified_id = $authId;
                            $jobTitle->save();
                            $jobTitleArray[] = $jobTitle->job_title_id;
                        }
                    }
                }

                if(!empty($jobTitleArray)){
                    $jobTitleIds = implode(',',$jobTitleArray);
                }
            }

            $divisionArray = [];
            $divisionIds = Null;
            $explodeDivisionIds = Null;
            if(!empty($request->division)){
                $explodeDivisionIds = explode(',',$request->division);
                if(!empty($explodeDivisionIds)){
                    foreach($explodeDivisionIds as $explodeDivisionId){
                        $division = Division::where('division_id',$explodeDivisionId)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($division->count() > 0){
                            $divisionArray[] = $explodeDivisionId;
                        }else{
                            $division = new Division;
                            $division->division_name = $explodeDivisionId;
                            $division->is_active = 1;
                            $division->org_id = $organizationId;
                            $division->created_id = $authId;
                            $division->modified_id = $authId;
                            $division->save();
                            $divisionArray[] = $division->division_id;
                        }
                    }
                }
                if(!empty($divisionArray)){
                    $divisionIds = implode(',',$divisionArray);
                }
            }

            $areaArray = [];
            $areaIds = Null;
            $explodeAreaIds = Null;
            if(!empty($request->area)){
                $explodeAreaIds = explode(',',$request->area);
                if(!empty($explodeAreaIds)){
                    foreach($explodeAreaIds as $explodeAreaId){
                        $area = Area::where('area_id',$explodeAreaId)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($area->count() > 0){
                            $areaArray[] = $explodeAreaId;
                        }else{
                            $area = new Area;
                            $area->area_name = $explodeAreaId;
                            $area->is_active = 1;
                            $area->org_id = $organizationId;
                            $area->created_id = $authId;
                            $area->modified_id = $authId;
                            $area->save();
                            $areaArray[] = $area->area_id;
                        }
                    }
                }
                if(!empty($areaArray)){
                    $areaIds = implode(',',$areaArray);
                }
            }

            $locationArray = [];
            $locationIds = Null;
            $explodeLocationIds = Null;
            if(!empty($request->location)){
                $explodeLocationIds = explode(',',$request->location);
                if(!empty($explodeLocationIds)){
                    foreach($explodeLocationIds as $explodeLocationId){
                        $location = Location::where('location_id',$explodeLocationId)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($location->count() > 0){
                            $locationArray[] = $explodeLocationId;
                        }else{
                            $location = new Location;
                            $location->location_name = $explodeLocationId;
                            $location->is_active = 1;
                            $location->org_id = $organizationId;
                            $location->created_id = $authId;
                            $location->modified_id = $authId;
                            $location->save();
                            $locationArray[] = $location->location_id;
                        }
                    }
                }
                if(!empty($locationArray)){
                    $locationIds = implode(',',$locationArray);
                }
            }

            $userPhoto = '';
            if ($request->file('userPhoto') != '') {
                $path = getPathS3Bucket() . '/user_photo';
                $s3UserPhoto = Storage::disk('s3')->put($path, $request->userPhoto);
                $userPhoto = substr($s3UserPhoto, strrpos($s3UserPhoto, '/') + 1);
            }


            $user = new User;
            $user->org_id = $organizationId;
            $user->user_guid = userCode();
            $user->first_name = $request->firstName;
            $user->last_name = $request->lastName;
            $user->email_id = $request->emailAddress;
            $user->phone_number = $request->phoneNumber;
            $user->user_photo = $userPhoto;
            $user->barcode = $request->barcode;
            $user->job_title = $jobTitleIds;
            $user->divisions = $divisionIds;
            $user->area = $areaIds;
            $user->location = $locationIds;
            $user->role_id = $request->role;
            $user->is_supervisor = $request->isSupervisor;
            $user->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $user->created_id = $authId;
            $user->modified_id = $authId;
            $user->save();

            if($user->user_id != ''){
                $login = new Login;
                $login->user_id = $user->user_id;
                $login->org_id = $organizationId;
                $login->domain_id = $domainId;

                $login->user_name = $request->userName;
                $login->user_password = base64_encode($request->password);

                $login->authentication_phone = $request->phoneNumber;
                $login->authentication_email = $request->emailAddress;
                $login->save();

                if(!empty($jobTitleIds)){
                    jobTitleGroup($jobTitleIds,$user->user_id,$organizationId,1,'add','');
                }
                if(!empty($areaIds)){
                    areaGroup($areaIds,$user->user_id,$organizationId,1,'add','');
                }
                if(!empty($locationIds)){
                    locationGroup($locationIds,$user->user_id,$organizationId,1,'add','');
                }
                if(!empty($divisionIds)){
                    divisionGroup($divisionIds,$user->user_id,$organizationId,1,'add','');
                }
                companyGroup($user->user_id,$organizationId,1,'add','');

                $menuMasters = MenuMaster::where('is_active','!=','0')
                ->whereRaw('FIND_IN_SET(?, roles)', [$request->role])
                ->select('menu_master_id','menu_name','is_active')
                ->get();
                if($menuMasters->count() > 0){

                    foreach($menuMasters as $menuMaster){

                        $modulMasters = ModuleMaster::where('is_active','!=','0')
                        ->where('menu_master_id',$menuMaster->menu_master_id)
                        ->select('module_id')
                        ->get();
                        if($modulMasters->count() > 0){

                            foreach($modulMasters as $modulMaster){

                                $menuPermissions = MenuPermission::where('module_id',$modulMaster->module_id)
                                ->where('menu_master_id',$menuMaster->menu_master_id)
                                ->where('org_id',$organizationId)
                                ->where('role_id',$request->role);
                                
                                if($menuPermissions->count() > 0){
                                    $menuPermissions->update([
                                        'is_active'=>$menuMaster->is_active
                                    ]);

                                }else{
                                    $menuPermission = new MenuPermission;
                                    $menuPermission->display_name = $menuMaster->menu_name;
                                    $menuPermission->menu_master_id = $menuMaster->menu_master_id;
                                    $menuPermission->module_id = $modulMaster->module_id;
                                    $menuPermission->org_id = $organizationId;
                                    $menuPermission->role_id = $request->role;
                                    $menuPermission->is_active = 1;
                                    $menuPermission->created_id = $authId;
                                    $menuPermission->modified_id = $authId;
                                    $menuPermission->save();
                                }

                                $actionMasters = ActionMaster::where('module_id',$modulMaster->module_id)
                                ->select('actions_id','module_id','is_active')
                                ->get();
                                if($actionMasters->count() > 0){
                                    foreach($actionMasters as $actionMaster){

                                        $permissions = Permission::where('module_id',$modulMaster->module_id)
                                        ->where('actions_id',$actionMaster->actions_id)
                                        ->where('org_id',$organizationId)
                                        ->where('role_id',$request->role);

                                        if($permissions->count() > 0){
                                            $permissions->update([
                                                'is_active'=>$actionMaster->is_active
                                            ]);
                                        }else{
                                            $permission = new Permission;
                                            $permission->actions_id = $actionMaster->actions_id;
                                            $permission->module_id = $modulMaster->module_id;
                                            $permission->org_id = $organizationId;
                                            $permission->role_id = $request->role;
                                            $permission->read_access = 1;
                                            $permission->write_access = 1;
                                            $permission->is_active = 1;
                                            $permission->created_id = $authId;
                                            $permission->modified_id = $authId;
                                            $permission->save();
                                        }
                                    }
                                }  
                            }
                        }
                    }
                }

                if(!empty($request->customFields)){
                    
                    $customFields = json_decode($request->customFields);

                    if(!empty($customFields->text)){
                        foreach($customFields->text as $text){
                            $id = $text->id;
                            $value = isset($text->value) ? $text->value : '';
                            if (strtotime($value) !== false) {
                                $value = date('Y-m-d',strtotime($value));
                            }

                            $OrganizationUserCustomField = new OrganizationUserCustomField;
                            $OrganizationUserCustomField->user_id = $user->user_id;
                            $OrganizationUserCustomField->custom_field_id = $id;
                            //$OrganizationUserCustomField->custom_number_of_field_id = '';
                            $OrganizationUserCustomField->custom_field_value = $value;
                            $OrganizationUserCustomField->org_id = $organizationId;
                            $OrganizationUserCustomField->created_id = $authId;
                            $OrganizationUserCustomField->modified_id = $authId;
                            $OrganizationUserCustomField->save();
                        }
                    }
                    if(!empty($customFields->radio)){
                        foreach($customFields->radio as $radio){
                            $id = $radio->id;
                            $value = isset($radio->value) ? $radio->value : '';

                            $OrganizationUserCustomField = new OrganizationUserCustomField;
                            $OrganizationUserCustomField->user_id = $user->user_id;
                            $OrganizationUserCustomField->custom_field_id = $id;
                            $OrganizationUserCustomField->custom_number_of_field_id = $value;
                            $OrganizationUserCustomField->custom_field_value = 1;
                            $OrganizationUserCustomField->org_id = $organizationId;
                            $OrganizationUserCustomField->created_id = $authId;
                            $OrganizationUserCustomField->modified_id = $authId;
                            $OrganizationUserCustomField->save();
                        }
                    }
                }
            }

            $organizationLogo = '';
            if(Auth::user()->organization->logo_image != ''){
                $organizationLogo = getFileS3Bucket(getPathS3Bucket().'/organization_logo/'.Auth::user()->organization->logo_image);
            }


            $notificationMaster = OrganizationNotification::where('is_active','1')->where('org_id', $organizationId)->where('org_notification_type','email')->where('org_notification_id',1);
            if($notificationMaster->count() > 0){

                $notificationMaster = $notificationMaster->first();

                $messageBody = dynamicField($notificationMaster->org_notification_content,$user->user_id);

                $mailData = [
                    'subject' => $notificationMaster->org_subject,
                    'messageBody' => $messageBody,
                    'organizationName' => Auth::user()->organization->organization_name,
                    'organizationLogo' => $organizationLogo
                ];

                Mail::to($request->emailAddress)->send(new WelcomeMail($mailData));
                twilioMessage($request->phoneNumber,$messageBody);
            }
        
            return response()->json(['status'=>true,'code'=>201,'message'=>'User has been created successfully.'],201);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function viewUserById($userId)
    {
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;
        
        $user = DB::table('lms_user_master as user')
        ->leftJoin('lms_roles as role','user.role_id','=','role.role_id')
        ->leftJoin('lms_user_login as login','user.user_id','=','login.user_id')
        ->leftJoin('lms_org_master as org','user.org_id','=','org.org_id')
        ->leftJoin('lms_user_master as supervisor','user.is_supervisor','=','supervisor.user_id')

        //->leftJoin('lms_job_title as job','user.job_title','=','job.job_title_id')
        //->leftJoin('lms_area as area','user.area','=','area.area_id')
        //->leftJoin('lms_division as division','user.divisions','=','division.division_id')
        //->leftJoin('lms_location as location','user.location','=','location.location_id')

        //->where('user.is_active','!=','0')
        ->where('role.is_active','1')
        ->where('org.is_active','!=','0')
        ->where('user.is_active','!=','4')
        ->where(function($query) use ($organizationId,$roleId){
            if($roleId != 1){
                $query->where('org.org_id',$organizationId);
                $query->orWhere('org.parent_org_id',$organizationId);
            }
        })
        ->where('user.user_id',$userId);
        if ($user->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'User is not found.'], 404);
        }
        $user = $user->select('user.user_id as userId', 'user.first_name as firstName', 'user.last_name as lastName', 'user.email_id as email', 'user.phone_number as phone', 'user.job_title as jobTitle', 'user.divisions as division', 'user.area as area', 'user.location as location', 'user.barcode as barcode', 'role.role_name as roleName', 'login.user_name as userName','org.organization_name as organizationName','user.user_photo as userPhoto','user.is_supervisor as isSupervisor',DB::raw('CONCAT(supervisor.first_name," ",supervisor.last_name) AS supervisorName'), 'user.is_active as isActive')->first();
        
        if(!empty($user->jobTitle)){
            $user->jobTitle = JobTitle::whereIn('job_title_id',explode(',',$user->jobTitle))->where('is_active','1')->pluck('job_title_name');
        }
        if(!empty($user->location)){
            $user->location = Location::whereIn('location_id',explode(',',$user->location))->where('is_active','1')->pluck('location_name');
        }
        if(!empty($user->division)){
            $user->division = Division::whereIn('division_id',explode(',',$user->division))->where('is_active','1')->pluck('division_name');
        }
        if(!empty($user->area)){
            $user->area = Area::whereIn('area_id',explode(',',$user->area))->where('is_active','1')->pluck('area_name');
        }

        if ($user->userPhoto != '') {
            $user->userPhoto = getFileS3Bucket(getPathS3Bucket() . '/user_photo/' . $user->userPhoto);
        }

        if($user->isActive == 0){
            $user->isActive = 'Deleted';
        }
        if($user->isActive == 1){
            $user->isActive = 'Active';
        }
        if($user->isActive == 2){
            $user->isActive = 'Inactive';
        }
        if($user->isActive == 3){
            $user->isActive = 'Archive';
        }

        $OrganizationCustomFields = OrganizationCustomField::
        leftJoin('lms_custom_field_type_master','lms_custom_field_type_master.custom_field_type_id','=','lms_org_custom_fields.custom_field_type_id')
        ->where('lms_org_custom_fields.is_active','1')
        ->where('lms_org_custom_fields.custom_field_for_id',1)
        ->where('lms_org_custom_fields.org_id',$organizationId)
        ->select('lms_org_custom_fields.custom_field_id as id','lms_org_custom_fields.field_name as fieldName','lms_org_custom_fields.label_name as labelName','lms_org_custom_fields.custom_field_type_id as customFieldTypeId','lms_custom_field_type_master.custom_field_type as customFieldType')
        ->get();
        if($OrganizationCustomFields->count() > 0){
            foreach($OrganizationCustomFields as $OrganizationCustomField){

                $customFieldTypeId = $OrganizationCustomField->customFieldTypeId;

                $OrganizationCustomNumberOfFields = OrganizationCustomNumberOfField::where('is_active','1')->where('custom_field_id',$OrganizationCustomField->id)
                ->select('custom_number_of_field_id as id','label_name as labelName')
                ->get();
                if($OrganizationCustomNumberOfFields->count() > 0){
                    $OrganizationCustomField->customNumberOfFields = $OrganizationCustomNumberOfFields;

                    foreach($OrganizationCustomNumberOfFields as $OrganizationCustomNumberOfField){
                        $OrganizationUserCustomFields = OrganizationUserCustomField::where('user_id',$userId)->where('is_active','1')->where('org_id',$organizationId)->where('custom_field_id',$OrganizationCustomField->id)->where('custom_number_of_field_id',$OrganizationCustomNumberOfField->id)->get();
                        if($OrganizationUserCustomFields->count() > 0){
                            foreach($OrganizationUserCustomFields as $OrganizationUserCustomField){
                                if($customFieldTypeId == 4){
                                    $OrganizationCustomNumberOfField->checked = $OrganizationUserCustomField->custom_field_value;
                                }else if($customFieldTypeId == 5){
                                    $OrganizationCustomNumberOfField->selected = $OrganizationUserCustomField->custom_field_value;
                                }else{
                                    $OrganizationCustomNumberOfField->customFieldValue = $OrganizationUserCustomField->custom_field_value;
                                }
                            }
                        }else{
                            if($customFieldTypeId == 4){
                                $OrganizationCustomNumberOfField->checked = '';
                            }else if($customFieldTypeId == 5){
                                $OrganizationCustomNumberOfField->selected = '';
                            }else{
                                $OrganizationCustomNumberOfField->customFieldValue = '';
                            }
                        }
                    }
                    
                }else{
                    $OrganizationUserCustomField = OrganizationUserCustomField::where('user_id',$userId)->where('is_active','1')->where('org_id',$organizationId)->where('custom_field_id',$OrganizationCustomField->id);
                    if($OrganizationUserCustomField->count() > 0){
                       // $OrganizationCustomField->customFieldValue = $OrganizationUserCustomField->first()->custom_field_value;
                        if($customFieldTypeId == 4){
                            $OrganizationCustomField->checked = $OrganizationUserCustomField->first()->custom_field_value;
                        }else if($customFieldTypeId == 5){
                            $OrganizationCustomField->selected = $OrganizationUserCustomField->first()->custom_field_value;
                        }else{
                            $OrganizationCustomField->customFieldValue = $OrganizationUserCustomField->first()->custom_field_value;
                        }
                    }else{
                        if($customFieldTypeId == 4){
                            $OrganizationCustomField->checked = '';
                        }else if($customFieldTypeId == 5){
                            $OrganizationCustomField->selected = '';
                        }else{
                            $OrganizationCustomField->customFieldValue = '';
                        }
                    }
                }
            }
        }
        $user->customFields = $OrganizationCustomFields;
        return response()->json(['status'=>true,'code'=>200,'data'=>$user],200);
    }

    public function getUserById($userId)
    {
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;
        
        $user = DB::table('lms_user_master as user')
        ->leftJoin('lms_roles as role','user.role_id','=','role.role_id')
        ->leftJoin('lms_user_login as login','user.user_id','=','login.user_id')
        ->leftJoin('lms_org_master as org','user.org_id','=','org.org_id')
        ->leftJoin('lms_user_master as supervisor','user.is_supervisor','=','supervisor.user_id')

        //->leftJoin('lms_job_title as job','user.job_title','=','job.job_title_id')
        //->leftJoin('lms_area as area','user.area','=','area.area_id')
        //->leftJoin('lms_division as division','user.divisions','=','division.division_id')
        //->leftJoin('lms_location as location','user.location','=','location.location_id')

        //->where('user.is_active','!=','0')
        ->where('role.is_active','1')
        ->where('org.is_active','!=','0')
        ->where('user.is_active','!=','4')
        ->where(function($query) use ($organizationId,$roleId){
            if($roleId != 1){
                $query->where('org.org_id',$organizationId);
                $query->orWhere('org.parent_org_id',$organizationId);
            }
        })
        ->where('user.user_id',$userId);
        if ($user->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'User is not found.'], 404);
        }
        $user = $user->select('user.user_id as userId', 'user.user_guid as userGuid', 'user.first_name as firstName', 'user.last_name as lastName', 'user.email_id as email', 'user.phone_number as phone', 'user.job_title as jobTitle', 'user.divisions as division', 'user.area as area', 'user.location as location', 'user.barcode as barcode', 'role.role_id as role', 'role.role_name as roleName', 'login.user_name as userName', 'login.user_password as userPassword', 'user.org_id as organizationId', 'org.organization_name as organizationName','user.user_photo as userPhoto','user.is_supervisor as isSupervisor',DB::raw('CONCAT(supervisor.first_name," ",supervisor.last_name) AS supervisorName'), 'user.is_active as isActive')->first();
        
        if(!empty($user->jobTitle)){
            $user->jobTitle = JobTitle::whereIn('job_title_id',explode(',',$user->jobTitle))->where('is_active','1')->select('job_title_id as jobTitleId','job_title_name as jobTitleName')->get();
        }
        if(!empty($user->location)){
            $user->location = Location::whereIn('location_id',explode(',',$user->location))->where('is_active','1')->select('location_id as locationId','location_name as locationName')->get();
        }
        if(!empty($user->division)){
            $user->division = Division::whereIn('division_id',explode(',',$user->division))->where('is_active','1')->select('division_id as divisionId','division_name as divisionName')->get();
        }
        if(!empty($user->area)){
            $user->area = Area::whereIn('area_id',explode(',',$user->area))->where('is_active','1')->select('area_id as areaId','area_name as areaName')->get();
        }
        if ($user->userPhoto != '') {
            $user->userPhoto = getFileS3Bucket(getPathS3Bucket() . '/user_photo/' . $user->userPhoto);
        }

        //$OrganizationUserCustomField = OrganizationUserCustomField::where('user_id',$userId)->where('is_active','1');
        $OrganizationCustomFields = OrganizationCustomField::
        leftJoin('lms_custom_field_type_master','lms_custom_field_type_master.custom_field_type_id','=','lms_org_custom_fields.custom_field_type_id')
        ->where('lms_org_custom_fields.is_active','1')
        ->where('lms_org_custom_fields.custom_field_for_id',1)
        ->where('lms_org_custom_fields.org_id',$user->organizationId)
        ->select('lms_org_custom_fields.custom_field_id as id','lms_org_custom_fields.field_name as fieldName','lms_org_custom_fields.label_name as labelName','lms_org_custom_fields.custom_field_type_id as customFieldTypeId','lms_custom_field_type_master.custom_field_type as customFieldType')
        ->get();
        if($OrganizationCustomFields->count() > 0){
            foreach($OrganizationCustomFields as $OrganizationCustomField){

                $customFieldTypeId = $OrganizationCustomField->customFieldTypeId;

                $OrganizationCustomNumberOfFields = OrganizationCustomNumberOfField::where('is_active','1')->where('custom_field_id',$OrganizationCustomField->id)
                ->select('custom_number_of_field_id as id','label_name as labelName')
                ->get();
                if($OrganizationCustomNumberOfFields->count() > 0){
                    $OrganizationCustomField->customNumberOfFields = $OrganizationCustomNumberOfFields;

                    foreach($OrganizationCustomNumberOfFields as $OrganizationCustomNumberOfField){
                        $OrganizationUserCustomFields = OrganizationUserCustomField::where('user_id',$userId)->where('is_active','1')->where('org_id',$user->organizationId)->where('custom_field_id',$OrganizationCustomField->id)->where('custom_number_of_field_id',$OrganizationCustomNumberOfField->id)->get();
                        if($OrganizationUserCustomFields->count() > 0){
                            foreach($OrganizationUserCustomFields as $OrganizationUserCustomField){
                                if($customFieldTypeId == 4){
                                    $OrganizationCustomNumberOfField->checked = $OrganizationUserCustomField->custom_field_value;
                                }else if($customFieldTypeId == 5){
                                    $OrganizationCustomNumberOfField->selected = $OrganizationUserCustomField->custom_field_value;
                                }else{
                                    $OrganizationCustomNumberOfField->customFieldValue = $OrganizationUserCustomField->custom_field_value;
                                }
                            }
                        }else{
                            if($customFieldTypeId == 4){
                                $OrganizationCustomNumberOfField->checked = '';
                            }else if($customFieldTypeId == 5){
                                $OrganizationCustomNumberOfField->selected = '';
                            }else{
                                $OrganizationCustomNumberOfField->customFieldValue = '';
                            }
                        }
                    }
                    
                }else{
                    $OrganizationUserCustomField = OrganizationUserCustomField::where('user_id',$userId)->where('is_active','1')->where('org_id',$user->organizationId)->where('custom_field_id',$OrganizationCustomField->id);
                    if($OrganizationUserCustomField->count() > 0){
                       // $OrganizationCustomField->customFieldValue = $OrganizationUserCustomField->first()->custom_field_value;
                        if($customFieldTypeId == 4){
                            $OrganizationCustomField->checked = $OrganizationUserCustomField->first()->custom_field_value;
                        }else if($customFieldTypeId == 5){
                            $OrganizationCustomField->selected = $OrganizationUserCustomField->first()->custom_field_value;
                        }else{
                            $OrganizationCustomField->customFieldValue = $OrganizationUserCustomField->first()->custom_field_value;
                        }
                    }else{
                        if($customFieldTypeId == 4){
                            $OrganizationCustomField->checked = '';
                        }else if($customFieldTypeId == 5){
                            $OrganizationCustomField->selected = '';
                        }else{
                            $OrganizationCustomField->customFieldValue = '';
                        }
                    }
                }
            }
        }
        $user->customFields = $OrganizationCustomFields;


        return response()->json(['status'=>true,'code'=>200,'data'=>$user],200);
    }


    public function updateUser(Request $request)
    {
        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;
        $organizationId = $request->organizationId; //Auth::user()->org_id;
        
        $validator = Validator::make($request->all(), [
            'userId' => 'required|integer',
            'firstName' => 'required|max:32',
            'lastName' => 'required|max:32',
            'jobTitle' => 'nullable|max:64',
            'emailAddress' => 'nullable|max:256|regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix|unique:lms_user_master,email_id,'.$request->userId.',user_id',
            'barcode' => 'nullable|max:24',
            'phoneNumber' => 'nullable|max:24',
            'division' => 'nullable|max:64',
            'area' => 'nullable|max:64',
            'location' => 'nullable|max:64',
            'role' => 'required|integer',
            //'userName' => 'required|max:48|unique:lms_user_login,user_name,'.$request->userId.',user_id',
            'userName' => 'required|max:48',
            'password' => 'nullable|string|min:8',
            'userPhoto' => 'nullable|mimes:jpeg,jpg,png',
            'isActive' => 'integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $userNameCheck = user::join('lms_user_login','lms_user_master.user_id','=','lms_user_login.user_id')
        ->where('lms_user_master.org_id',$organizationId)->where('lms_user_login.org_id',$organizationId)
        ->where('lms_user_login.user_name', 'like',$request->userName)
        //->where('lms_user_master.user_id',$request->userId)
        ->where('lms_user_master.is_active','!=','4');
        if($userNameCheck->count() > 0){
            if($userNameCheck->first()->user_id == $request->userId){

            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'Username is already exist.'], 404);
            }
        }


        try{

            

        
            $user = User::where('is_active','1')
            // ->where(function($query) use ($organizationId,$roleId){
            //     if($roleId != 1){
            //         $query->where('org_id',$organizationId);
            //     }
            // })
            ->where('user_id',$request->userId);

            if ($user->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'User is not found.'], 404);
            } 

            $jobTitleId = $userNameCheck->first()->job_title;
            $divisionsId = $userNameCheck->first()->divisions;
            $areaId = $userNameCheck->first()->area;
            $locationId = $userNameCheck->first()->location;
            $orgId = $userNameCheck->first()->org_id;

            $org = User::join('lms_org_master','lms_user_master.email_id','=','lms_org_master.contact_email')
            ->where('lms_org_master.org_id',$organizationId)
            ->where('lms_user_master.user_id',$request->userId);
            if($org->count() > 0){
                Organization::where('org_id',$organizationId)
                ->where('contact_email',$org->first()->contact_email)
                ->update(['contact_email'=>$request->emailAddress]);
            }
            

            // $jobTitleIds = Null;
            // if(!empty($request->jobTitle)){
            //     $explodeJobTitleIds = explode(',',$request->jobTitle);
            //     $jobTitleIds = implode(',',$explodeJobTitleIds);
            // }

            // $divisionIds = Null;
            // if(!empty($request->division)){
            //     $explodeDivisionIds = explode(',',$request->division);
            //     $divisionIds = implode(',',$explodeDivisionIds);
            // }

            // $areaIds = Null;
            // if(!empty($request->area)){
            //     $explodeAreaIds = explode(',',$request->area);
            //     $areaIds = implode(',',$explodeAreaIds);
            // }

            // $locationIds = Null;
            // if(!empty($request->location)){
            //     $explodeLocationIds = explode(',',$request->location);
            //     $locationIds = implode(',',$explodeLocationIds);
            // }

            $jobTitleArray = [];
            $jobTitleIds = Null;
            $explodeJobTitleIds = Null;
            if(!empty($request->jobTitle)){
                $explodeJobTitleIds = explode(',',$request->jobTitle);
                if(!empty($explodeJobTitleIds)){
                    foreach($explodeJobTitleIds as $explodeJobTitleId){

                        $jobTitle = JobTitle::where('job_title_id',$explodeJobTitleId)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($jobTitle->count() > 0){
                            $jobTitleArray[] = $explodeJobTitleId;
                        }else{
                            $jobTitle = new JobTitle;
                            $jobTitle->job_title_name = $explodeJobTitleId;
                            $jobTitle->is_active = 1;
                            $jobTitle->org_id = $organizationId;
                            $jobTitle->created_id = $authId;
                            $jobTitle->modified_id = $authId;
                            $jobTitle->save();
                            $jobTitleArray[] = $jobTitle->job_title_id;
                        }
                    }
                }

                if(!empty($jobTitleArray)){
                    $jobTitleIds = implode(',',$jobTitleArray);
                }
            }

            $divisionArray = [];
            $divisionIds = Null;
            $explodeDivisionIds = Null;
            if(!empty($request->division)){
                $explodeDivisionIds = explode(',',$request->division);
                if(!empty($explodeDivisionIds)){
                    foreach($explodeDivisionIds as $explodeDivisionId){
                        $division = Division::where('division_id',$explodeDivisionId)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($division->count() > 0){
                            $divisionArray[] = $explodeDivisionId;
                        }else{
                            $division = new Division;
                            $division->division_name = $explodeDivisionId;
                            $division->is_active = 1;
                            $division->org_id = $organizationId;
                            $division->created_id = $authId;
                            $division->modified_id = $authId;
                            $division->save();
                            $divisionArray[] = $division->division_id;
                        }
                    }
                }
                if(!empty($divisionArray)){
                    $divisionIds = implode(',',$divisionArray);
                }
            }

            $areaArray = [];
            $areaIds = Null;
            $explodeAreaIds = Null;
            if(!empty($request->area)){
                $explodeAreaIds = explode(',',$request->area);
                if(!empty($explodeAreaIds)){
                    foreach($explodeAreaIds as $explodeAreaId){
                        $area = Area::where('area_id',$explodeAreaId)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($area->count() > 0){
                            $areaArray[] = $explodeAreaId;
                        }else{
                            $area = new Area;
                            $area->area_name = $explodeAreaId;
                            $area->is_active = 1;
                            $area->org_id = $organizationId;
                            $area->created_id = $authId;
                            $area->modified_id = $authId;
                            $area->save();
                            $areaArray[] = $area->area_id;
                        }
                    }
                }
                if(!empty($areaArray)){
                    $areaIds = implode(',',$areaArray);
                }
            }

            $locationArray = [];
            $locationIds = Null;
            $explodeLocationIds = Null;
            if(!empty($request->location)){
                $explodeLocationIds = explode(',',$request->location);
                if(!empty($explodeLocationIds)){
                    foreach($explodeLocationIds as $explodeLocationId){
                        $location = Location::where('location_id',$explodeLocationId)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($location->count() > 0){
                            $locationArray[] = $explodeLocationId;
                        }else{
                            $location = new Location;
                            $location->location_name = $explodeLocationId;
                            $location->is_active = 1;
                            $location->org_id = $organizationId;
                            $location->created_id = $authId;
                            $location->modified_id = $authId;
                            $location->save();
                            $locationArray[] = $location->location_id;
                        }
                    }
                }
                if(!empty($locationArray)){
                    $locationIds = implode(',',$locationArray);
                }
            }

            $user->update([
                'first_name' => $request->firstName,
                'last_name' => $request->lastName,
                'job_title' => $jobTitleIds,
                'email_id' => $request->emailAddress,
                'phone_number' => $request->phoneNumber,
                'divisions' => $divisionIds,
                'area' => $areaIds,
                'location' => $locationIds,
                'barcode' => $request->barcode,
                'role_id' => $request->role,
                'is_supervisor' => $request->isSupervisor,
                'is_active' => $request->isActive == '' ? $user->first()->is_active ? $user->first()->is_active : '1' : $request->isActive,
                'modified_id' => $authId
            ]);


            if ($request->file('userPhoto') != '') {
                $path = getPathS3Bucket() . '/user_photo';
                $s3UserPhoto = Storage::disk('s3')->put($path, $request->userPhoto);
                $userPhoto = substr($s3UserPhoto, strrpos($s3UserPhoto, '/') + 1);
                $user->update([
                    'user_photo' => $userPhoto
                ]);
            }

            DB::table('lms_user_login')->where('user_id',$request->userId)
            ->where(function($query) use ($organizationId,$roleId){
                if($roleId != 1){
                    $query->where('org_id',$organizationId);
                }
            })
            ->update([
                'user_name' => $request->userName,
                'authentication_phone' => $request->phoneNumber,
                'authentication_email' => $request->emailAddress
            ]);

            if($request->password != ''){
                DB::table('lms_user_login')->where('user_id',$request->userId)
                ->where(function($query) use ($organizationId,$roleId){
                    if($roleId != 1){
                        $query->where('org_id',$organizationId);
                    }
                })
                ->update([
                    'user_password' => base64_encode($request->password)
                ]);
            }

            jobTitleGroup($jobTitleIds,$request->userId,$organizationId,1,'edit',$jobTitleId);
            areaGroup($areaIds,$request->userId,$organizationId,1,'edit',$areaId);
            locationGroup($locationIds,$request->userId,$organizationId,1,'edit',$locationId);
            divisionGroup($divisionIds,$request->userId,$organizationId,1,'edit',$divisionsId);
            companyGroup($request->userId,$organizationId,1,'edit',$orgId);

            if(!empty($request->customFields)){
                $customFields = json_decode($request->customFields);
                if(!empty($customFields->text)){
                    foreach($customFields->text as $text){
                        $id = $text->id;
                        $value = isset($text->value) ? $text->value : '';
                        if (strtotime($value) !== false) {
                            $value = date('Y-m-d',strtotime($value));
                        }

                        $OrganizationUserCustomField = OrganizationUserCustomField::where('custom_field_id',$id)->where('user_id',$request->userId);
                        if($OrganizationUserCustomField->count() > 0){
                            $OrganizationUserCustomField->update([
                                'custom_number_of_field_id' => '',
                                'custom_field_value' => $value,
                                'modified_id' => $authId,
                            ]);
                        }else{
                            $OrganizationUserCustomField = new OrganizationUserCustomField;
                            $OrganizationUserCustomField->user_id = $request->userId;
                            $OrganizationUserCustomField->custom_field_id = $id;
                            $OrganizationUserCustomField->custom_number_of_field_id = '';
                            $OrganizationUserCustomField->custom_field_value = $value;
                            $OrganizationUserCustomField->org_id = $organizationId;
                            $OrganizationUserCustomField->created_id = $authId;
                            $OrganizationUserCustomField->modified_id = $authId;
                            $OrganizationUserCustomField->save();
                        }
                    }
                }
                if(!empty($customFields->radio)){
                    foreach($customFields->radio as $radio){
                        $id = $radio->id;
                        $value = isset($radio->value) ? $radio->value : '';

                        $OrganizationUserCustomField = OrganizationUserCustomField::where('custom_field_id',$id)->where('user_id',$request->userId);
                        if($OrganizationUserCustomField->count() > 0){
                            $OrganizationUserCustomField->update([
                                'custom_number_of_field_id' => $value,
                                'custom_field_value' => 1,
                                'modified_id' => $authId,
                            ]);
                        }else{
                            $OrganizationUserCustomField = new OrganizationUserCustomField;
                            $OrganizationUserCustomField->user_id = $request->userId;
                            $OrganizationUserCustomField->custom_field_id = $id;
                            $OrganizationUserCustomField->custom_number_of_field_id = $value;
                            $OrganizationUserCustomField->custom_field_value = 1;
                            $OrganizationUserCustomField->org_id = $organizationId;
                            $OrganizationUserCustomField->created_id = $authId;
                            $OrganizationUserCustomField->modified_id = $authId;
                            $OrganizationUserCustomField->save();
                        }
                    }
                }
            }

            return response()->json(['status'=>true,'code'=>200,'message'=>'User has been updated successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function deleteUser(Request $request)
    {
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;
        $validator = Validator::make($request->all(), [
            'userId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $user = User::where('is_active','!=','0')->where('is_active','!=','4')
            // ->where(function($query) use ($organizationId,$roleId){
            //     if($roleId != 1){
            //         $query->where('org_id',$organizationId);
            //     }
            // })
            ->where('user_id',$request->userId);
            if($user->count() > 0){

                $user->update([
                    'is_active' => '0',
                ]);

                return response()->json(['status'=>true,'code'=>200,'message'=>'User has been deleted successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'User is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function permanentDeleteUser(Request $request)
    {
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;
        $validator = Validator::make($request->all(), [
            'userId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $user = User::where('is_active','0')
            // ->where(function($query) use ($organizationId,$roleId){
            //     if($roleId != 1){
            //         $query->where('org_id',$organizationId);
            //     }
            // })
            ->where('user_id',$request->userId);
            if($user->count() > 0){

                $user->update([
                    'is_active' => '4',
                ]);


                return response()->json(['status'=>true,'code'=>200,'message'=>'User has been deleted successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'User is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }
    
    public function archiveUser(Request $request)
    {
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;
        $validator = Validator::make($request->all(), [
            'userId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $user = User::where('is_active','0')->where('user_id',$request->userId);
            if($user->count() > 0){

                $user->update([
                    'is_active' => '3',
                ]);

                return response()->json(['status'=>true,'code'=>200,'message'=>'User has been archived successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'User is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function activeUser(Request $request)
    {
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;
        $validator = Validator::make($request->all(), [
            'userId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $user = User::where(function ($query) {
                $query->where('is_active','3')->orWhere('is_active','2')->orWhere('is_active','0');
            })->where('user_id',$request->userId);
            if($user->count() > 0){

                $user->update([
                    'is_active' => '1',
                ]);

                return response()->json(['status'=>true,'code'=>200,'message'=>'User has been activated successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'User is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function inactiveUser(Request $request)
    {
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;
        $validator = Validator::make($request->all(), [
            'userId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $user = User::where(function ($query) {
                $query->where('is_active','!=','4');
            })->where('user_id',$request->userId);
            if($user->count() > 0){

                $user->update([
                    'is_active' => '2',
                ]);

                return response()->json(['status'=>true,'code'=>200,'message'=>'User has been inactivated successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'User is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function userImport(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        $domainId = Auth::user()->domain_id;


        $validator = Validator::make($request->all(), [
            'userImportFile' => 'required|file|mimes:xls,xlsx,csv,txt'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

       $userImportFile = $request->file('userImportFile');

       try{
           $spreadsheet = IOFactory::load($userImportFile->getRealPath());
           $sheet        = $spreadsheet->getActiveSheet();
           $row_limit    = $sheet->getHighestDataRow();
           $column_limit = $sheet->getHighestDataColumn();
           $row_range    = range( 2, $row_limit );
           $column_range = range( 'F', $column_limit );
           $startcount = 2;
           $userData = [];
           
           $errorMessages = [];
           $errors = 0;

           if($row_limit == 1){
             return response()->json(['status'=>false,'code'=>400,'errors'=>'The file is not empty.'],400);
           }

           foreach ( $row_range as $row ) {   

                $i = 0;
                
                $errorMessage = [];
                $roleId = '';

                $firstName = $sheet->getCell( 'A' . $row )->getValue();
                $lastName = $sheet->getCell( 'B' . $row )->getValue();
                $emailId = $sheet->getCell( 'C' . $row )->getValue();
                $phoneNumber = $sheet->getCell( 'D' . $row )->getValue();
                $jobTitleName = $sheet->getCell( 'E' . $row )->getValue();
                $divisionName = $sheet->getCell( 'F' . $row )->getValue();
                $areaName = $sheet->getCell( 'G' . $row )->getValue();
                $locationName = $sheet->getCell( 'H' . $row )->getValue();
                $barcode = $sheet->getCell( 'I' . $row )->getValue();
                $isActive = $sheet->getCell( 'J' . $row )->getValue();
                $roleName = $sheet->getCell( 'K' . $row )->getValue();
                $userName = $sheet->getCell( 'L' . $row )->getValue();
                $password = $sheet->getCell( 'M' . $row )->getValue();

                $errorMessage['rowNumber'] = $row;

                if($firstName == ''){
                    $errorMessage['firstName'] = 'The first name field is required';
                    $errors++;
                    $i++;
                }
                if($lastName == ''){
                    $errorMessage['LastName'] = 'The last name field is required';
                    $errors++;
                    $i++;
                }

                if($roleName == ''){
                    $errorMessage['roleName'] = 'The role name field is required';
                    $errors++;
                    $i++;
                }else{
                    $roleMaster = RoleMaster::where('role_name','LIKE','%'.$roleName.'%')->where('is_active','1');
                    if($roleMaster->count() > 0){
                        $roleId = $roleMaster->first()->role_id;
                    }else{
                        $errorMessage['roleName'] = 'The role name is found';
                        $errors++;
                        $i++;
                    }
                }

                if($emailId != ''){
                    if(!filter_var($emailId, FILTER_VALIDATE_EMAIL)) {
                        $errorMessage['email'] = $emailId.' invalid email format.';
                        $errors++;
                        $i++;
                    }else{
                        $emailCheck = user::where('is_active','!=','0')->where('email_id',$emailId)->count();
                        if($emailCheck > 0){
                            $errorMessage['email'] = $emailId.' is duplicated.';
                            $errors++;
                            $i++;
                        }
                    }
                }else{
                    $errorMessage['email'] = 'The email field is required';
                    $errors++;
                    $i++;
                }

                if($userName == ''){
                    $errorMessage['userName'] = 'The username field is required';
                    $errors++;
                    $i++;
                }else{
                    $userNameCheck = Login::where('user_name',$userName);
                    if($userNameCheck->count() > 0){
                        $errorMessage['userName'] = $userName.' is duplicated.';
                        $errors++;
                        $i++;
                    }
                }

                if($password == ''){
                    $errorMessage['password'] = 'The password field is required';
                    $errors++;
                    $i++;
                }

                $jobTitleId = '';
                if($jobTitleName != ''){
                    $jobTitle = JobTitle::where('job_title_name',$jobTitleName)->where('is_active','!=','0');
                    if($jobTitle->count() > 0){
                        $jobTitleId = $jobTitle->first()->job_title_id;
                    }else{
                        $jobTitle = new JobTitle;
                        $jobTitle->job_title_name = $jobTitleName;
                        $jobTitle->is_active = 1;
                        $jobTitle->org_id = $organizationId;
                        $jobTitle->created_id = $authId;
                        $jobTitle->modified_id = $authId;
                        $jobTitle->save();
                        $jobTitleId = $jobTitle->job_title_id;
                    }
                }

                $divisionId = '';
                if($divisionName != ''){
                    $division = Division::where('division_name','LIKE','%'.$divisionName.'%')->where('is_active','!=','0');
                    if($division->count() > 0){
                        $divisionId = $division->first()->division_id;
                    }else{
                        $division = new Division;
                        $division->division_name = $divisionName;
                        $division->is_active = 1;
                        $division->org_id = $organizationId;
                        $division->created_id = $authId;
                        $division->modified_id = $authId;
                        $division->save();
                        $divisionId = $division->division_id;
                    }
                }

                $areaId = '';
                if($areaName != ''){
                    $area = Area::where('area_name','LIKE','%'.$areaName.'%')->where('is_active','!=','0');
                    if($area->count() > 0){
                        $areaId = $area->first()->area_id;
                    }else{
                        $area = new Area;
                        $area->area_name = $areaName;
                        $area->is_active = 1;
                        $area->org_id = $organizationId;
                        $area->created_id = $authId;
                        $area->modified_id = $authId;
                        $area->save();
                        $areaId = $area->area_id;
                    }
                }

                $locationId = '';
                if($locationName != ''){
                    $location = Location::where('location_name','LIKE','%'.$locationName.'%')->where('is_active','!=','0');
                    if($location->count() > 0){
                        $locationId = $location->first()->location_id;
                    }else{
                        $location = new Location;
                        $location->location_name = $locationName;
                        $location->is_active = 1;
                        $location->org_id = $organizationId;
                        $location->created_id = $authId;
                        $location->modified_id = $authId;
                        $location->save();
                        $locationId = $location->location_id;
                    }
                }
                
                if(!empty($errorMessage)){
                    $errorMessages[] = $errorMessage;
                }

                // $userData[] = [
                //     'firstName' =>$firstName,
                //     'lastName' => $lastName,
                //     'emailId' => $emailId,
                //     'phoneNumber' => $phoneNumber,
                //     'barcode' => $barcode,
                //     'jobTitle' => $jobTitle,
                //     'divisions' => $divisions,
                //     'area' => $area,
                //     'roleId' => $roleId,
                //     'isActive' => $isActive,
                //     'userName' => $userName,
                //     'password' => $password
                // ];
                
                if($i == 0){

                    $isActive = 1;
                    if(strtolower($isActive) == 'active'){
                        $isActive = 1;
                    }
                    if(strtolower($isActive) == 'inactive'){
                        $isActive = 2;
                    }
                    if(strtolower($isActive) == 'deleted'){
                        $isActive = 0;
                    }
                    if(strtolower($isActive) == 'archive'){
                        $isActive = 3;
                    }

                    $user = new User; 
                    $user->org_id = $organizationId;
                    $user->user_guid = userCode();
                    $user->first_name = $firstName;
                    $user->last_name = $lastName;
                    $user->email_id = $emailId;
                    $user->phone_number = $phoneNumber;
                    $user->barcode = $barcode;
                    $user->job_title = $jobTitleId;
                    $user->divisions = $divisionId;
                    $user->area = $areaId;
                    $user->location = $locationId;
                    $user->role_id = $roleId;
                    $user->is_active = $isActive;
                    $user->created_id = $authId;
                    $user->modified_id = $authId;
                    $user->save();

                    if($user->user_id != ''){
                        $login = new Login;
                        $login->user_id = $user->user_id;
                        $login->org_id = $organizationId;
                        $login->domain_id = $domainId;
                        $login->user_name = $userName;
                        $login->user_password = base64_encode($password);
                        $login->authentication_phone = $phoneNumber;
                        $login->authentication_email = $emailId;
                        $login->save();
                    }
                }
           }

            if($errors == 0){
               return response()->json(['status'=>true,'code'=>200,'message'=>'User has been imported successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>400,'errors'=>$errorMessages],400);
            }
       } catch (\Throwable $e) {
         return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
       }
    
    }

    public function bulkDeleteUser(Request $request){

        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;
        try{
            if(!empty($request->userIds)){
                foreach($request->userIds as $userId){
                    $user = User::where('is_active','!=','0')
                    ->where('user_id',$userId);
                    if($user->count() > 0){

                        $user->update([
                            'is_active' => '0',
                        ]);

                    }
                }
                return response()->json(['status'=>true,'code'=>200,'message'=>'User has been deleted successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'User is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function bulkArchiveUser(Request $request){

        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;
        try{
            if(!empty($request->userIds)){
                foreach($request->userIds as $userId){
                    $user = User::where('user_id',$userId);
                    if($user->count() > 0){

                        $user->update([
                            'is_active' => '3',
                        ]);

                    }
                }
                return response()->json(['status'=>true,'code'=>200,'message'=>'User has been deleted successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'User is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function bulkInactiveUser(Request $request){

        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;
        try{
            if(!empty($request->userIds)){
                foreach($request->userIds as $userId){
                    $user = User::where('is_active','!=','4')
                    ->where('user_id',$userId);
                    if($user->count() > 0){

                        $user->update([
                            'is_active' => '2',
                        ]);

                    }
                }
                return response()->json(['status'=>true,'code'=>200,'message'=>'User has been inactivated successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'User is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function getUserListByRoleId($roleId){
        $organizationId = '';
        if(Auth::user()->user->role_id != 1){
            $organizationId = Auth::user()->org_id;
        }

        $users = User::join('lms_roles as role','lms_user_master.role_id','=','role.role_id')
        ->where('lms_user_master.is_active','1')
        ->where('role.is_active','1')
        ->where('role.role_id',$roleId)
        ->where(function($query) use ($organizationId){
            if($organizationId != ''){
                $query->where('lms_user_master.org_id',$organizationId);
            }
        })
        ->select('lms_user_master.user_id as userId',DB::raw('CONCAT(lms_user_master.first_name," ",lms_user_master.last_name) AS userName'),'role.role_id as roleId','role.role_name as roleName')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$users],200);
    }


    public function getUserListByOrgId(Request $request){
        $validator = Validator::make($request->all(), [
            'organizationId' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        $users = User::where('is_active','1')->where('org_id',$request->organizationId)->select('user_id as userId',DB::raw('CONCAT(first_name," ",last_name) AS userName'))->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$users],200);
    }

    public function getProfileDetails(){
        $userId = Auth::user()->user_id;

        $user = DB::table('lms_user_master as user')
            ->leftJoin('lms_roles as role','user.role_id','=','role.role_id')
            ->leftJoin('lms_user_login as login','user.user_id','=','login.user_id')
            ->leftJoin('lms_org_master as org','user.org_id','=','org.org_id')

            ->leftJoin('lms_job_title as job','user.job_title','=','job.job_title_id')
            ->leftJoin('lms_area as area','user.area','=','area.area_id')
            ->leftJoin('lms_division as division','user.divisions','=','division.division_id')
            ->leftJoin('lms_location as location','user.location','=','location.location_id')

            //->where('user.is_active','!=','0')
            ->where('role.is_active','1')
            ->where('org.is_active','!=','0')
            ->where('user.is_active','!=','4')
            ->where('user.user_id',$userId);
            if ($user->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'User is not found.'], 404);
            }
            $user = $user->select('user.user_id as userId', 'user.first_name as firstName', 'user.last_name as lastName', 'user.email_id as email', 'user.phone_number as phone', 'job.job_title_name as jobTitleName', 'division.division_name as divisionName','area.area_name as areaName','location.location_name as locationName', 'user.barcode as barcode', 'role.role_name as roleName', 'login.user_name as userName','org.organization_name as organizationName','user.user_photo as userPhoto', 'user.is_active as isActive')->first();
            if($user->isActive == 0){
                $user->isActive = 'Deleted';
            }
            if($user->isActive == 1){
                $user->isActive = 'Active';
            }
            if($user->isActive == 2){
                $user->isActive = 'Inactive';
            }
            if($user->isActive == 3){
                $user->isActive = 'Archive';
            }
            if ($user->userPhoto != '') {
                $user->userPhoto = getFileS3Bucket(getPathS3Bucket() . '/user_photo/' . $user->userPhoto);
            }
            return response()->json(['status'=>true,'code'=>200,'data'=>$user],200);
    }

    public function updateProfileDetails(Request $request){
        $userId = Auth::user()->user_id;

        $validator = Validator::make($request->all(), [
            'userPhoto' => 'nullable|mimes:jpeg,jpg,png'
        ]);

        if ($request->file('userPhoto') != '') {
            $user = User::where('user_id',$userId);
            if($user->count() > 0){
                $path = getPathS3Bucket() . '/user_photo';
                $s3UserPhoto = Storage::disk('s3')->put($path, $request->userPhoto);
                $userPhoto = substr($s3UserPhoto, strrpos($s3UserPhoto, '/') + 1);
                $user->update([
                    'user_photo' => $userPhoto
                ]);
            }
        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'User has been updated successfully.'],200);
    }

    public function userNotificationAssignment(Request $request){

        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'userIds' => 'required|array',
            'notifications' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            if(is_array($request->userIds) && is_array($request->notifications)){

                if(count($request->userIds) > 0 && count($request->notifications) > 0){
    
                    foreach($request->userIds as $k => $userId){
    
                        foreach($request->notifications as $k => $notification){
    
                            $notificationId = $notification['notificationId'];
                            $isChecked = ($notification['isChecked'] == 1) ? 1 : 0;
    
                            $userNotification = DB::table('lms_user_notification_assignment')
                            ->where('user_id',$userId)
                            ->where('notification_id',$notificationId)
                            ->where('org_id',$organizationId);
                            if($userNotification->count() > 0){
                                $userNotification->update([
                                    'is_active' => $isChecked,
                                    'modified_id' => $authId,
                                    'date_modified'=> Carbon::now()
                                ]);
                            }else{
                                DB::table('lms_user_notification_assignment')->insert([
                                    'user_id' => $userId,
                                    'notification_id' => $notificationId,
                                    'org_id' => $organizationId,
                                    'is_active' => $isChecked,
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
            return response()->json(['status'=>true,'code'=>200,'message'=>'Notification assigned successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function getUserListByGroupId(Request $request){

        $groups = $request->groups;
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;

        $validator = Validator::make($request->all(), [
            'groups' => 'nullable|array'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        
        $users = DB::table('lms_user_master as user')
        ->join('lms_user_org_group as userGroup','user.user_id','=','userGroup.user_id')
        ->where('user.is_active','!=','0')
        ->where('user.is_active','!=','4')
        ->where('userGroup.is_active','1')
        
        ->where(function($query) use ($groups){
            if(!empty($groups)){
                $query->whereIn('userGroup.group_id',$groups);
            }
        })
        ->where(function($query) use ($organizationId,$roleId,$authId){
            if($roleId != 1){
                $query->where('userGroup.user_id',$user->userId);
                $query->where('userGroup.org_id',$organizationId);
            }
        })
        ->groupBy('user.user_id')
        ->select('user.user_id as userId', 'user.first_name as firstName', 'user.last_name as lastName')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$users],200);
    }

    public function getSupervisorUserList(Request $reuest)
    {
        $organizationId = Auth::user()->org_id;
        $users = User::where('is_active', '!=', '0')
        ->where('role_id', '!=', '1') 
        ->where('role_id', '!=', '7') 
        ->where('org_id', '=', $organizationId) 
        ->select('user_id as userId',DB::raw('CONCAT(first_name," ",last_name) AS supervisorName'))
        ->get();
        return response()->json(['status' => true, 'code' => 200, 'data' => $users], 200);
    }
}
