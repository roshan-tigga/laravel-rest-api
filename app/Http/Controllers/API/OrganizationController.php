<?php

namespace App\Http\Controllers\API;

use App\Models\Organization;
use App\Models\User;
use App\Models\Tag;
use App\Models\Login;
use App\Models\Domain;
use App\Models\AuthenticationType;

use App\Models\MenuMaster;
use App\Models\ModuleMaster;
use App\Models\ActionMaster;
use App\Models\MenuPermission;
use App\Models\Permission;
use App\Models\NotificationMaster;

use App\Models\RoleMaster;
use App\Models\OrganizationRole;
use App\Models\OrganizationNotification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use Carbon\Carbon;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Hash;
use Illuminate\Support\Facades\Storage;
use DB;

use App\Mail\WelcomeMail;
use Mail;


class OrganizationController extends Controller
{

    public function getOrganizationList(Request $request)
    {
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;

        $sort = $request->has('sort') ? $request->get('sort') : 'lms_org.org_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';
        $status = $request->has('status') ? $request->get('status') : '';
        $type = $request->has('type') ? $request->get('type') : '';

        $sortColumn = $sort;
        if ($sort == 'organizationCode') {
            $sortColumn = 'lms_org.org_code';
        } elseif ($sort == 'organizationName') {
            $sortColumn = 'lms_org.organization_name';
        } elseif ($sort == 'isPrimary') {
            $sortColumn = 'lms_org.is_primary';
        } elseif ($sort == 'parentOrganizationName') {
            $sortColumn = 'parent_org.organization_name';
        } elseif ($sort == 'domainName') {
            $sortColumn = 'lms_domain.domain_name';
        } elseif ($sort == 'adminName') {
            $sortColumn = 'lms_user.first_name';
            $sortColumn = 'lms_user.last_name';
        } elseif ($sort == 'isActive') {
            $sortColumn = 'menu.is_active';
        }

        $companies = DB::table('lms_org_master as lms_org')
            ->leftJoin('lms_domain', 'lms_org.domain_id', '=', 'lms_domain.domain_id')
            // ->leftJoin('lms_org_master as child', 'lms_org.org_id', '=', 'child.parent_org_id')
            ->leftJoin('lms_user_master as lms_user', 'lms_org.org_id', '=', 'lms_user.org_id')
            ->leftJoin('lms_org_master as parent_org', 'lms_org.parent_org_id', '=', 'parent_org.org_id')
            ->where('lms_org.is_active', '!=', '0')
            ->where(function ($query) use ($type) {
                if ($type == 'active') {
                    $query->where('lms_org.is_active', 1);
                }
                if ($type == 'inactive') {
                    $query->where('lms_org.is_active', 2);
                }
            })
            ->where(function ($query) use ($status) {
                if ($status != '') {
                    $query->where('lms_org.is_active', $status);
                }
            })
            ->where(function ($query) use ($search) {
                if ($search != '') {
                    $query->where('lms_org.org_code', 'LIKE', '%' . $search . '%');
                    $query->orWhere('lms_org.organization_name', 'LIKE', '%' . $search . '%');
                    $query->orWhere('lms_org.is_primary', 'LIKE', '%' . $search . '%');
                    $query->orWhere('parent_org.organization_name', 'LIKE', '%' . $search . '%');
                    $query->orWhere('lms_domain.domain_name', 'LIKE', '%' . $search . '%');
                    $query->orWhere('lms_user.first_name', 'LIKE', '%' . $search . '%');
                    $query->orWhere('lms_user.last_name', 'LIKE', '%' . $search . '%');

                    if (in_array($search, ['active', 'act', 'acti', 'activ'])) {
                        $query->orWhere('lms_org.is_active', '1');
                    }
                    if (in_array($search, ['inactive', 'inact', 'inacti', 'inactiv'])) {
                        $query->orWhere('lms_org.is_active', '2');
                    }
                }
            })
            ->where(function ($query) use ($organizationId, $roleId) {
                if ($roleId != 1) {
                    $query->where('lms_org.org_id', $organizationId);
                    $query->orWhere('lms_org.parent_org_id', $organizationId);
                }
            })
            ->when($sort == 'adminName', function ($query) use ($order) {
                return $query->orderBy("lms_user.first_name", $order)->orderBy('lms_user.last_name', $order);
            }, function ($query) use ($sortColumn, $order) {
            return $query->orderBy($sortColumn, $order);
        })
            ->groupBy('lms_org.org_id')
            ->select(
                'lms_org.org_id as organizationId',
                'lms_org.org_code as organizationCode',
                'lms_org.organization_name as organizationName',
                DB::raw('(CASE WHEN lms_org.is_primary = 1 THEN "Parent Organization" ELSE "Child Organization" END) AS isPrimary'),
                'lms_org.parent_org_id as parentOrganizationId',
                'parent_org.organization_name as parentOrganizationName',
                'lms_domain.domain_name as domainName', DB::raw('CONCAT(lms_user.first_name," ",lms_user.last_name) AS adminName'),
                'lms_org.is_active as isActive'
            )
            ->get();
        return response()->json(['status' => true, 'code' => 200, 'data' => $companies], 200);
    }


    public function addNewOrganization(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;

        $domainId = '';
        if ($request->isPrimary == 1 || $request->isPrimary == 2) {
            $validator = Validator::make($request->all(), [
                'organizationLogo' => 'nullable|mimes:jpeg,jpg,png',
                'logoText' => 'max:255',
                'organizationName' => 'required|max:64',
                'organizationNote' => 'nullable|max:255',

                'organizationType' => 'nullable',


                'isPrimary' => 'required|integer',

                'domainName' => 'required|max:64',
                'domainType' => 'integer',

                'address' => 'nullable|max:255',
                'zipCode' => 'nullable|numeric',
                'country' => 'nullable|integer',
                'state' => 'nullable|max:255',

                'userFirstName' => 'required|max:32',
                'userLastName' => 'required|max:32',
                'userEmailId' => 'nullable|max:50|unique:lms_user_master,email_id|regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix',
                'userPhoneNumber' => 'nullable|numeric',
                //'adminUsername' => 'required|max:48|unique:lms_user_login,user_name',
                'adminUsername' => 'required|max:48',
                'adminPassword' => 'required|string|min:8',
                'role' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
            }

            $userNameCheck = user::join('lms_user_login', 'lms_user_master.user_id', '=', 'lms_user_login.user_id')
                ->where('lms_user_master.org_id', $organizationId)->where('lms_user_login.org_id', $organizationId)
                ->where('lms_user_login.user_name', 'like', $request->adminUsername)
                ->where('lms_user_master.is_active', '!=', '4')->count();
            if ($userNameCheck > 0) {
                return response()->json(['status' => false, 'code' => 404, 'error' => 'Username is already exist.'], 404);
            }

            $domain = new Domain;
            $domain->domain_name = $request->domainName;
            $domain->is_production = $request->domainType;
            $domain->save();

            $domainId = $domain->domain_id;

        } else {

            $validator = Validator::make($request->all(), [
                'organizationLogo' => 'nullable|mimes:jpeg,jpg,png',
                'logoText' => 'max:255',
                'organizationName' => 'required|max:64',
                'organizationNote' => 'nullable|max:255',
                'isPrimary' => 'required|integer',
                'primaryOrganization' => 'required|integer',

                'organizationType' => 'nullable',


                'address' => 'nullable|max:255',
                'zipCode' => 'nullable|numeric',
                'country' => 'nullable|integer',
                'state' => 'nullable|max:255',

                'userFirstName' => 'required|max:32',
                'userLastName' => 'required|max:32',
                'userEmailId' => 'nullable|max:50|unique:lms_user_master,email_id|regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix',
                'userPhoneNumber' => 'nullable|numeric',
                //'adminUsername' => 'required|max:48|unique:lms_user_login,user_name',
                'adminUsername' => 'required|max:48',
                'adminPassword' => 'required|string|min:8',
                'role' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
            }

            $primaryOrganizationId = $request->primaryOrganization;
            $userNameCheck = user::join('lms_user_login', 'lms_user_master.user_id', '=', 'lms_user_login.user_id')
                ->where('lms_user_master.org_id', $primaryOrganizationId)->where('lms_user_login.org_id', $primaryOrganizationId)
                ->where('lms_user_login.user_name', 'like', $request->adminUsername)
                ->where('lms_user_master.is_active', '!=', '4')->count();
            if ($userNameCheck > 0) {

                return response()->json(['status' => false, 'code' => 404, 'error' => 'Username is already exist.'], 404);
            }

            $domain = DB::table('lms_org_master as lms_org')
                ->join('lms_domain', 'lms_org.domain_id', 'lms_domain.domain_id')
                ->where('lms_org.is_active', '1')
                ->where('lms_domain.is_active', '1')
                ->where('lms_org.org_id', $request->primaryOrganization)
                ->select('lms_domain.domain_id');

            if ($domain->count() > 0) {
                $domainId = $domain->first()->domain_id;
            }
        }

        if (isset($domainId)) {

            $organizationLogo = '';
            if ($request->file('organizationLogo') != '') {
                $path = getPathS3Bucket() . '/organization_logo';
                $s3OrganizationLogo = Storage::disk('s3')->put($path, $request->organizationLogo);
                $organizationLogo = substr($s3OrganizationLogo, strrpos($s3OrganizationLogo, '/') + 1);
            }

            $organization = new Organization;
            $organization->domain_id = $domainId;
            $organization->org_code = $this->organizationCode();
            $organization->organization_name = $request->organizationName;
            if ($request->organizationNote != '') {
                $organization->organization_notes = $request->organizationNote;
            }
            $organization->is_primary = $request->isPrimary;
            if ($request->isPrimary == 1) {
                $organization->parent_org_id = $organizationId;
            }
            if ($request->isPrimary == 0) {
                $organization->parent_org_id = $request->primaryOrganization;
            }
            if ($request->userEmailId != '') {
                $organization->email_id = $request->userEmailId;
            }
            if ($request->userPhoneNumber != '') {
                $organization->phone_number = $request->userPhoneNumber;
            }
            if ($request->address != '') {
                $organization->address = $request->address;
            }
            if ($request->zipCode != '') {
                $organization->zip_code = $request->zipCode;
            }
            if ($request->country != '') {
                $organization->country = $request->country;
            }
            if ($request->state != '') {
                $organization->state = $request->state;
            }
            $organization->logo_image = $organizationLogo;
            $organization->logo_text = $request->logoText;

            if ($request->organizationType != '' && $request->organizationType != 'undefined') {
                $organization->organization_type_id = $request->organizationType;
            }
            if ($request->userPhoneNumber != '') {
                $organization->contact_number = $request->userPhoneNumber;
            }
            $organization->contact_person = $request->userFirstName;

            if ($request->userEmailId != '') {
                $organization->contact_email = $request->userEmailId;
            }
            $organization->is_active = $request->isActive ? $request->isActive : 1;
            $organization->authentication_type_id = $request->authenticationType;
            $organization->session_time_out = $request->sessionTimeOut ? date('H:m:s',strtotime($request->sessionTimeOut)) : Null;
            $organization->created_id = $authId;
            $organization->modified_id = $authId;
            $organization->save();

            if (isset($organization->org_id)) {

                $user = new User;
                $user->org_id = $organization->org_id;
                $user->user_guid = $this->userCode();
                $user->first_name = $request->userFirstName;
                $user->last_name = $request->userLastName;
                $user->email_id = $request->userEmailId;
                $user->phone_number = $request->userPhoneNumber;
                $user->role_id = $request->role;
                $user->created_id = $authId;
                $user->modified_id = $authId;
                $user->save();

                if (isset($user->user_id)) {

                    Organization::where('org_id', $organization->org_id)->update([
                        'user_id' => $user->user_id
                    ]);

                    $login = new Login;
                    $login->user_id = $user->user_id;
                    $login->org_id = $organization->org_id;
                    $login->domain_id = $domainId;
                    $login->user_name = $request->adminUsername;
                    $login->user_password = base64_encode($request->adminPassword);
                    $login->password_date = Carbon::now();
                    $login->last_login_date = Carbon::now();
                    $login->authentication_phone = $request->userPhoneNumber;
                    $login->authentication_email = $request->userEmailId;
                    $login->save();

                    if ($request->tags != '' && $request->tags != 'undefined') {
                        $tags = explode(',', $request->tags);
                        if (count($tags) > 0) {
                            $tagArray = [];
                            $tagsArray = [];
                            foreach ($tags as $tag) {
                                if (isset($tag)) {
                                    $tagArray = [
                                        'tag_name' => $tag,
                                        'ref_table_name' => 'lms_org_master',
                                        'org_id' => $organization->org_id,
                                        'date_created' => Carbon::now(),
                                        'date_modified' => Carbon::now(),
                                        'created_id' => $authId,
                                        'modified_id' => $authId
                                    ];

                                    $tagsArray[] = $tagArray;
                                }
                            }
                            if (isset($tagsArray)) {
                                Tag::insert($tagsArray);
                            }
                        }
                    }

                    $menuMasters = MenuMaster::where('is_active', '!=', '0')
                        ->whereRaw('FIND_IN_SET(?, roles)', [$request->role])
                        ->select('menu_master_id', 'menu_name', 'is_active')
                        ->get();
                    if ($menuMasters->count() > 0) {

                        foreach ($menuMasters as $menuMaster) {

                            $modulMasters = ModuleMaster::where('is_active', '!=', '0')
                                ->where('menu_master_id', $menuMaster->menu_master_id)
                                ->select('module_id')
                                ->get();
                            if ($modulMasters->count() > 0) {

                                foreach ($modulMasters as $modulMaster) {

                                    $menuPermission = new MenuPermission;
                                    $menuPermission->display_name = $menuMaster->menu_name;
                                    $menuPermission->menu_master_id = $menuMaster->menu_master_id;
                                    $menuPermission->module_id = $modulMaster->module_id;
                                    $menuPermission->org_id = $organization->org_id;
                                    $menuPermission->role_id = $request->role;
                                    $menuPermission->is_active = 1;
                                    $menuPermission->created_id = $authId;
                                    $menuPermission->modified_id = $authId;
                                    $menuPermission->save();

                                    // $menuPermissions = MenuPermission::
                                    //     //where('module_id', $modulMaster->module_id)
                                    //     where('menu_master_id', $menuMaster->menu_master_id)
                                    //     ->where('org_id', $organization->org_id)
                                    //     ->where('role_id', $request->role);
                                    // if ($menuPermissions->count() > 0) {
                                    //     $menuPermissions->update([
                                    //         'is_active' => 1
                                    //     ]);
                                    // } else {
                                    //     $menuPermission = new MenuPermission;
                                    //     $menuPermission->display_name = $menuMaster->menu_name;
                                    //     $menuPermission->menu_master_id = $menuMaster->menu_master_id;
                                    //     $menuPermission->module_id = $modulMaster->module_id;
                                    //     $menuPermission->org_id = $organization->org_id;
                                    //     $menuPermission->role_id = $request->role;
                                    //     $menuPermission->is_active = 1;
                                    //     $menuPermission->created_id = $authId;
                                    //     $menuPermission->modified_id = $authId;
                                    //     $menuPermission->save();
                                    // }

                                    $actionMasters = ActionMaster::where('module_id', $modulMaster->module_id)
                                        ->select('actions_id', 'module_id', 'is_active')
                                        ->get();
                                    if ($actionMasters->count() > 0) {
                                        foreach ($actionMasters as $actionMaster) {

                                            $permissions = Permission::where('module_id', $modulMaster->module_id)
                                                ->where('actions_id', $actionMaster->actions_id)
                                                ->where('org_id', $organization->org_id)
                                                ->where('role_id', $request->role);
                                            if ($permissions->count() > 0) {

                                                $permissions->update([
                                                    'is_active' => $actionMaster->is_active
                                                ]);

                                            } else {
                                                $permission = new Permission;
                                                $permission->actions_id = $actionMaster->actions_id;
                                                $permission->module_id = $modulMaster->module_id;
                                                $permission->org_id = $organization->org_id;
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
                            }else{
                                $menuPermission = new MenuPermission;
                                $menuPermission->display_name = $menuMaster->menu_name;
                                $menuPermission->menu_master_id = $menuMaster->menu_master_id;
                                //$menuPermission->module_id = $modulMaster->module_id;
                                $menuPermission->org_id = $organization->org_id;
                                $menuPermission->role_id = $request->role;
                                $menuPermission->is_active = 1;
                                $menuPermission->created_id = $authId;
                                $menuPermission->modified_id = $authId;
                                $menuPermission->save();
                            }
                        }
                    }

                    $roleMasters = RoleMaster::where('is_active','!=', '0')->where('role_id','!=','1');
                    if ($roleMasters->count() > 0) {
                        foreach ($roleMasters->get() as $roleMaster) {
                            $organizationRole = new OrganizationRole;
                            $organizationRole->role_id = $roleMaster->role_id;
                            $organizationRole->role_name = $roleMaster->role_name;
                            $organizationRole->role_type = $roleMaster->role_type;
                            $organizationRole->description = $roleMaster->description;
                            $organizationRole->org_id = $organization->org_id;
                            $organizationRole->su_assigned = 1;
                            $organizationRole->assigned = 1;
                            $organizationRole->created_id = $authId;
                            $organizationRole->modified_id = $authId;
                            $organizationRole->date_created = date('Y-m-d H:i:s');
                            $organizationRole->date_modified = date('Y-m-d H:i:s');
                            $organizationRole->save();
                        }
                    }

                    $notificationMasters = NotificationMaster::where('is_active', 1);
                    if ($notificationMasters->count() > 0) {
                        foreach ($notificationMasters->get() as $notificationMaster) {
                            $organizationNotification = new OrganizationNotification;
                            $organizationNotification->notification_id = $notificationMaster->notification_id;
                            $organizationNotification->org_notification_name = $notificationMaster->notification_name;
                            $organizationNotification->org_notification_type = $notificationMaster->notification_type;
                            $organizationNotification->org_subject = $notificationMaster->subject;

                            $organizationNotification->org_notification_content = $notificationMaster->notification_content;
                            $organizationNotification->notification_event_id = $notificationMaster->notification_event_id;
                            $organizationNotification->notification_category_id = $notificationMaster->notification_category_id;
                            $organizationNotification->su_assigned = 1;
                            $organizationNotification->assigned = 1;
                            $organizationNotification->notification_date = $notificationMaster->notification_date;

                            $organizationNotification->org_id = $organization->org_id;
                            $organizationNotification->created_id = $authId;
                            $organizationNotification->modified_id = $authId;
                            $organizationNotification->date_created = date('Y-m-d H:i:s');
                            $organizationNotification->date_modified = date('Y-m-d H:i:s');
                            $organizationNotification->save();
                        }
                    }

                    return response()->json(['status' => true, 'code' => 201, 'message' => 'Organization has been created successfully.'], 201);
                }

                if (!empty($request->userEmailId)) {
                    $notificationMaster = OrganizationNotification::where('is_active', '1')->where('org_id', $organization->org_id)->where('org_notification_type', 'email')->where('org_notification_id', 1);
                    if ($notificationMaster->count() > 0) {

                        $notificationMaster = $notificationMaster->first();

                        $messageBody = dynamicField($notificationMaster->org_notification_content,$user->user_id);

                        $mailData = [
                            'subject' => $notificationMaster->org_subject,
                            'messageBody' => $messageBody,
                            'organizationName' => $request->organizationName,
                            'organizationLogo' => $organizationLogo
                        ];

                        Mail::to($request->userEmailId)->send(new WelcomeMail($mailData));
                        twilioMessage($request->userPhoneNumber, $messageBody);
                    }
                }
            }

        } else {
            return response()->json(['status' => false, 'code' => 400, 'message' => 'Organization has been not create.'], 400);
        }

    }


    public function getOrganization(Request $request)
    {
        $organizationId = Auth::user()->org_id;

        $organization = DB::table('lms_org_master as org_master')
            ->leftJoin('lms_domain as domain', 'org_master.domain_id', '=', 'domain.domain_id')
            ->leftJoin('lms_org_master as parent_org_master', 'org_master.parent_org_id', '=', 'parent_org_master.org_id')
            ->leftJoin('lms_organization_type as organization_type', 'org_master.organization_type_id', '=', 'organization_type.organization_type_id')
            ->leftJoin('lms_country_master as country_master', 'org_master.country', '=', 'country_master.country_id')

            ->leftJoin('lms_user_master as user_master', 'org_master.user_id', '=', 'user_master.user_id')

            ->leftJoin('lms_roles as role', 'user_master.role_id', '=', 'role.role_id')
            ->leftJoin('lms_user_login as user_login', 'user_master.user_id', '=', 'user_login.user_id')
            ->where('org_master.is_active', '!=', '0')
            ->where('org_master.org_id', $organizationId)->where('user_master.org_id', $organizationId)
            ->where('domain.is_active', '!=', '0');

        if ($organization->count() < 1) {
            return response()->json(['status' => false, 'code' => 404, 'error' => 'Organization is not found.'], 404);
        }

        $organization = $organization
            ->selectRaw("COALESCE(org_master.org_id,'') as organizationId, COALESCE(org_master.domain_id,'') as domainId, COALESCE(domain.domain_name,'') as domainName, COALESCE(domain.is_production,'') as domainType, COALESCE(org_master.organization_name,'') as organizationName,COALESCE(org_master.org_code,'') as organizationCode, COALESCE(org_master.organization_notes,'') as organizationNote, COALESCE(org_master.is_primary,'') as isPrimary,
        COALESCE(org_master.parent_org_id,'') as primaryOrganization, COALESCE(parent_org_master.organization_name,'') as primaryOrganizationName,
        COALESCE(org_master.organization_type_id,'') as organizationType, COALESCE(organization_type.organization_type,'') as organizationTypeName,
        COALESCE(user_master.first_name,'') as userFirstName,COALESCE(user_master.last_Name,'') as userLastName,COALESCE(user_master.email_id,'') as userEmailId, COALESCE(user_master.phone_number,'') as userPhoneNumber, COALESCE(user_master.role_id,'') as role, COALESCE(role.role_name,'') as roleName, COALESCE(org_master.address,'') as address, COALESCE(org_master.zip_code,'') as zipCode, COALESCE(org_master.country,'') as country, COALESCE(country_master.country,'') as countryName, COALESCE(org_master.state,'') as state, COALESCE(org_master.logo_image,'') as organizationLogo, COALESCE(org_master.logo_text,'') as logoText,
        COALESCE(user_login.user_name,'') as adminUsername,COALESCE(user_login.login_id,'') as adminLoginId, COALESCE(user_login.user_password,'') as userPassword, COALESCE(user_master.user_id,'') as userId, COALESCE(org_master.is_active,'') as isActive")->first();

        if ($organization->organizationLogo != '') {
            $organization->organizationLogo = getFileS3Bucket(getPathS3Bucket() . '/organization_logo/' . $organization->organizationLogo);
        }

        if (isset($organization->organizationId)) {
            $organization->tags = Tag::where('org_id', $organization->organizationId)->where('is_active', '1')->pluck('tag_name');
        }

        return response()->json(['status' => true, 'code' => 200, 'data' => $organization], 200);
    }

    public function getOrganizationById($organizationId)
    {
        $organization = DB::table('lms_org_master as org_master')
            ->leftJoin('lms_domain as domain', 'org_master.domain_id', '=', 'domain.domain_id')
            ->leftJoin('lms_org_master as parent_org_master', 'org_master.parent_org_id', '=', 'parent_org_master.org_id')
            ->leftJoin('lms_organization_type as organization_type', 'org_master.organization_type_id', '=', 'organization_type.organization_type_id')
            ->leftJoin('lms_country_master as country_master', 'org_master.country', '=', 'country_master.country_id')

            ->leftJoin('lms_user_master as user_master', 'org_master.user_id', '=', 'user_master.user_id')

            ->leftJoin('lms_roles as role', 'user_master.role_id', '=', 'role.role_id')
            ->leftJoin('lms_user_login as user_login', 'user_master.user_id', '=', 'user_login.user_id')
            ->leftJoin('lms_authentication_type','lms_authentication_type.authentication_type_id','=','org_master.authentication_type_id')
            ->where('org_master.is_active', '!=', '0')
            ->where('org_master.org_id', $organizationId)->where('user_master.org_id', $organizationId)
            ->where('domain.is_active', '!=', '0');

        if ($organization->count() < 1) {
            return response()->json(['status' => false, 'code' => 404, 'error' => 'Organization is not found.'], 404);
        }

        $organization = $organization
            ->selectRaw("COALESCE(org_master.org_id,'') as organizationId, COALESCE(org_master.domain_id,'') as domainId, COALESCE(domain.domain_name,'') as domainName, COALESCE(domain.is_production,'') as domainType, COALESCE(org_master.organization_name,'') as organizationName,COALESCE(org_master.org_code,'') as organizationCode, COALESCE(org_master.organization_notes,'') as organizationNote, COALESCE(org_master.is_primary,'') as isPrimary,
        COALESCE(org_master.parent_org_id,'') as primaryOrganization, COALESCE(parent_org_master.organization_name,'') as primaryOrganizationName,
        COALESCE(org_master.organization_type_id,'') as organizationType, COALESCE(organization_type.organization_type,'') as organizationTypeName,
        COALESCE(user_master.first_name,'') as userFirstName,COALESCE(user_master.last_Name,'') as userLastName,COALESCE(user_master.email_id,'') as userEmailId, COALESCE(user_master.phone_number,'') as userPhoneNumber, COALESCE(user_master.role_id,'') as role, COALESCE(role.role_name,'') as roleName, COALESCE(org_master.address,'') as address, COALESCE(org_master.zip_code,'') as zipCode, COALESCE(org_master.country,'') as country, COALESCE(country_master.country,'') as countryName, COALESCE(org_master.state,'') as state, COALESCE(org_master.logo_image,'') as organizationLogo, COALESCE(org_master.logo_text,'') as logoText,
        COALESCE(user_login.user_name,'') as adminUsername,COALESCE(user_login.login_id,'') as adminLoginId, COALESCE(user_login.user_password,'') as userPassword, COALESCE(user_master.user_id,'') as userId, COALESCE(org_master.is_active,'') as isActive, org_master.authentication_type_id as authenticationTypeId, lms_authentication_type.authentication_type as authenticationType , org_master.session_time_out as sessionTimeOut")->first();

        if ($organization->organizationLogo != '') {
            $organization->organizationLogo = getFileS3Bucket(getPathS3Bucket() . '/organization_logo/' . $organization->organizationLogo);
        }

        if (isset($organization->organizationId)) {
            $organization->tags = Tag::where('org_id', $organization->organizationId)->where('is_active', '1')->pluck('tag_name');
        }

        return response()->json(['status' => true, 'code' => 200, 'data' => $organization], 200);
    }

    public function updateOrganizationById(Request $request)
    {
        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;

        if ($request->isPrimary == 1 || $request->isPrimary == 2) {
            $validator = Validator::make($request->all(), [
                'organizationId' => 'required',
                'adminLoginId' => 'required',
                'userId' => 'required',
                'organizationLogo' => 'nullable|mimes:jpeg,jpg,png',
                'organizationName' => 'required|max:64',
                'isPrimary' => 'required',
                'domainName' => 'required|max:64',
                'userFirstName' => 'required|max:32',
                'userLastName' => 'required|max:32',
                'userEmailId' => 'nullable|max:50|regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix',
                //'adminUsername' => 'required|max:48|unique:lms_user_login,user_name,'.$request->adminLoginId.',login_id,org_id,'.$request->organizationId,
                'adminUsername' => 'required|max:48',
                'adminPassword' => 'nullable|min:8',
                'role' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
            }

            $organizationId = $request->organizationId;
            $userNameCheck = user::join('lms_user_login', 'lms_user_master.user_id', '=', 'lms_user_login.user_id')
                ->where('lms_user_master.org_id', $organizationId)->where('lms_user_login.org_id', $organizationId)
                ->where('lms_user_login.user_name', 'like', $request->adminUsername)
                //->where('lms_user_master.user_id',$request->userId)
                ->where('lms_user_master.is_active', '!=', '4');
            if ($userNameCheck->count() > 0) {
                if ($userNameCheck->first()->user_id == $request->userId) {

                } else {
                    return response()->json(['status' => false, 'code' => 404, 'error' => 'Username is already exist.'], 404);
                }
            }

            $organization = Organization::where('org_id', $request->organizationId)->where('is_active', '!=', '0');
            if ($organization->count() < 1) {
                return response()->json(['status' => false, 'code' => 404, 'error' => 'Organization is not found.'], 404);
            }

            if ($organization->where('is_primary', $request->isPrimary)->count() > 0) {
                $domainId = $organization->first()->domain_id;
                Domain::where('domain_id', $domainId)->update(['domain_name' => $request->domainName, 'is_production' => $request->domainType]);
            } else {
                $domain = new Domain;
                $domain->domain_name = $request->domainName;
                $domain->is_production = $request->domainType;
                $domain->save();

                $domainId = $domain->domain_id;
            }

            $organizationType = '';
            if ($request->organizationType != '' && $request->organizationType != 'undefined') {
                $organizationType = $request->organizationType;
            }


            $organization = Organization::where('org_id', $request->organizationId)->where('is_active', '!=', '0');
            $organization->update([
                'domain_id' => $domainId,
                'organization_name' => $request->organizationName,
                'organization_notes' => $request->organizationNote,
                'is_primary' => $request->isPrimary,
                'email_id' => $request->userEmailId,
                'phone_number' => $request->userPhoneNumber,
                'address' => $request->address,
                'zip_code' => $request->zipCode,
                'country' => $request->country,
                'state' => $request->state,
                'logo_text' => $request->logoText,
                'organization_type_id' => $organizationType,
                'is_active' => $request->isActive == '' ? $organization->first()->is_active ? $organization->first()->is_active : '1' : $request->isActive,
                'authentication_type_id' => $request->authenticationType,
                'session_time_out' => $request->sessionTimeOut ? date('H:m:s',strtotime($request->sessionTimeOut)) : Null,
                'modified_id' => $authId
            ]);

            if ($request->file('organizationLogo') != '') {
                $path = getPathS3Bucket() . '/organization_logo';
                $s3OrganizationLogo = Storage::disk('s3')->put($path, $request->organizationLogo);
                $organizationLogo = substr($s3OrganizationLogo, strrpos($s3OrganizationLogo, '/') + 1);
                $organization->update([
                    'logo_image' => $organizationLogo
                ]);
            }

        } else {
            $validator = Validator::make($request->all(), [
                'organizationId' => 'required',
                'adminLoginId' => 'required',
                'userId' => 'required',
                'organizationLogo' => 'nullable|mimes:jpeg,jpg,png',
                'organizationName' => 'required|max:64',
                'isPrimary' => 'required',
                'primaryOrganization' => 'required',
                'userFirstName' => 'required|max:32',
                'userLastName' => 'required|max:32',
                'userEmailId' => 'nullable|max:50|regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix',
                //'adminUsername' => 'required|max:48|unique:lms_user_login,user_name,'.$request->adminLoginId.',login_id,org_id,'.$request->organizationId,
                'adminUsername' => 'required|max:48',
                'adminPassword' => 'nullable|min:8',
                'role' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
            }

            $primaryOrganizationId = $request->primaryOrganization;
            $userNameCheck = user::join('lms_user_login', 'lms_user_master.user_id', '=', 'lms_user_login.user_id')
                ->where('lms_user_master.org_id', $primaryOrganizationId)->where('lms_user_login.org_id', $primaryOrganizationId)
                ->where('lms_user_login.user_name', 'like', $request->adminUsername)
                //->where('lms_user_master.user_id',$request->userId)
                ->where('lms_user_master.is_active', '!=', '4');
            if ($userNameCheck->count() > 0) {
                if ($userNameCheck->first()->user_id == $request->userId) {

                } else {
                    return response()->json(['status' => false, 'code' => 404, 'error' => 'Username is already exist.'], 404);
                }
            }

            $organization = Organization::where('org_id', $request->organizationId)->where('is_active', '!=', '0');
            if ($organization->count() < 1) {
                return response()->json(['status' => false, 'code' => 404, 'error' => 'Organization is not found.'], 404);
            }


            $domain = DB::table('lms_org_master as lms_org')
                ->join('lms_domain', 'lms_org.domain_id', 'lms_domain.domain_id')
                ->where('lms_org.is_active', '1')
                ->where('lms_domain.is_active', '1')
                ->where('lms_org.org_id', $request->primaryOrganization)
                ->select('lms_domain.domain_id');

            if ($domain->count() > 0) {
                $domainId = $domain->first()->domain_id;
                Domain::where('domain_id', $domainId)->update(['is_production' => $request->domainType]);
            }


            $organization->update([
                'domain_id' => $domainId,
                'organization_name' => $request->organizationName,
                'organization_notes' => $request->organizationNote,
                'is_primary' => $request->isPrimary,
                'parent_org_id' => $request->primaryOrganization,

                'email_id' => $request->userEmailId,
                'contact_email' => $request->userEmailId,
                'phone_number' => $request->userPhoneNumber,


                'address' => $request->address,
                'zip_code' => $request->zipCode,
                'country' => $request->country,
                'state' => $request->state,
                'logo_text' => $request->logoText,
                'organization_type_id' => $request->organizationType,
                'is_active' => $request->isActive == '' ? $organization->first()->is_active ? $organization->first()->is_active : '1' : $request->isActive,
                'authentication_type_id' => $request->authenticationType,
                'session_time_out' => $request->sessionTimeOut ? date('H:m:s',strtotime($request->sessionTimeOut)) : Null,
                'modified_id' => $authId
            ]);

            if ($request->file('organizationLogo') != '') {
                $path = getPathS3Bucket() . '/organization_logo';
                $s3OrganizationLogo = Storage::disk('s3')->put($path, $request->organizationLogo);
                $organizationLogo = substr($s3OrganizationLogo, strrpos($s3OrganizationLogo, '/') + 1);
                $organization->update([
                    'logo_image' => $organizationLogo
                ]);
            }

        }


        User::where(['user_id' => $request->userId, 'org_id' => $request->organizationId])->update([
            'email_id' => $request->userEmailId,
            'phone_number' => $request->userPhoneNumber,
            'first_name' => $request->userFirstName,
            'last_name' => $request->userLastName,
            'role_id' => $request->role
        ]);


        Login::where(['login_id' => $request->adminLoginId, 'org_id' => $request->organizationId])->update([
            'domain_id' => $domainId,
            'user_name' => $request->adminUsername
        ]);

        if ($request->adminPassword != '') {
            Login::where(['login_id' => $request->adminLoginId, 'org_id' => $request->organizationId])->update([
                'user_password' => base64_encode($request->adminPassword)
            ]);
        }

        if ($request->tags != '' && $request->tags != 'undefined') {
            $tags = explode(',', $request->tags);
            if (count($tags) > 0) {
                $tagArray = [];
                $tagsArray = [];
                Tag::where('org_id', $request->organizationId)->update(['is_active' => '0']);
                foreach ($tags as $tagName) {
                    if (isset($tagName)) {
                        $tag = Tag::where('tag_name', $tagName)->where('org_id', $request->organizationId);
                        if ($tag->count() > 0) {
                            $tag->update([
                                'is_active' => 1,
                                'modified_id' => $authId,
                                'date_modified' => Carbon::now()
                            ]);
                        } else {
                            $tagArray = [
                                'tag_name' => $tagName,
                                'ref_table_name' => 'lms_org_master',
                                'org_id' => $request->organizationId,
                                'date_created' => Carbon::now(),
                                'date_modified' => Carbon::now(),
                                'created_id' => $authId,
                                'modified_id' => $authId
                            ];
                            $tagsArray[] = $tagArray;
                        }
                    }
                }

                if (isset($tagsArray)) {
                    Tag::insert($tagsArray);
                }
            }
        }

        return response()->json(['status' => true, 'code' => 200, 'message' => 'Organization has been updated successfully.'], 200);
    }




    public function updateOrganization(Request $request)
    {
        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'organizationLogo' => 'nullable|mimes:jpeg,jpg,png',
            'organizationName' => 'required|max:64',
            'userEmailId' => 'nullable|max:50|regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix',
            //'adminUsername' => 'required|max:48|unique:lms_user_login,user_name,'.$request->adminLoginId.',login_id,org_id,'.$request->organizationId,
            'adminUsername' => 'required|max:48',
            'adminPassword' => 'nullable|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
        }

        $organization = Organization::where('org_id', $organizationId)->where('user_id', $authId)->where('is_active', '!=', '0');

        $userId = $organization->first()->user_id;

        $organization->update([
            'organization_name' => $request->organizationName,
            'logo_text' => $request->logoText,
            'email_id' => $request->userEmailId,
            'address' => $request->address,
            'modified_id' => $authId
        ]);

        if ($request->file('organizationLogo') != '') {
            $path = getPathS3Bucket() . '/organization_logo';
            $s3OrganizationLogo = Storage::disk('s3')->put($path, $request->organizationLogo);
            $organizationLogo = substr($s3OrganizationLogo, strrpos($s3OrganizationLogo, '/') + 1);
            $organization->update([
                'logo_image' => $organizationLogo
            ]);
        }


        User::where(['user_id' => $userId, 'org_id' => $organizationId])->update([
            'email_id' => $request->userEmailId,
            'phone_number' => $request->userPhoneNumber
        ]);


        Login::where(['user_id' => $userId, 'org_id' => $organizationId])->update([
            'user_name' => $request->adminUsername
        ]);

        if ($request->adminPassword != '') {
            Login::where(['user_id' => $userId, 'org_id' => $organizationId])->update([
                'user_password' => base64_encode($request->adminPassword)
            ]);
        }

        return response()->json(['status' => true, 'code' => 200, 'message' => 'Organization has been updated successfully.'], 200);


        exit;

        if ($request->isPrimary == 1) {
            $validator = Validator::make($request->all(), [
                'organizationId' => 'required',
                'adminLoginId' => 'required',
                'userId' => 'required',
                'organizationLogo' => 'nullable|mimes:jpeg,jpg,png',
                'organizationName' => 'required|max:64',
                'isPrimary' => 'required',
                'domainName' => 'required|max:64',
                'userFirstName' => 'required|max:32',
                'userLastName' => 'required|max:32',
                'userEmailId' => 'nullable|max:50|regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix',
                //'adminUsername' => 'required|max:48|unique:lms_user_login,user_name,'.$request->adminLoginId.',login_id,org_id,'.$request->organizationId,
                'adminUsername' => 'required|max:48',
                'adminPassword' => 'nullable|min:8',
                'role' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
            }

            $organizationId = $request->organizationId;
            $userNameCheck = user::join('lms_user_login', 'lms_user_master.user_id', '=', 'lms_user_login.user_id')
                ->where('lms_user_master.org_id', $organizationId)->where('lms_user_login.org_id', $organizationId)
                ->where('lms_user_login.user_name', 'like', $request->adminUsername)
                //->where('lms_user_master.user_id',$request->userId)
                ->where('lms_user_master.is_active', '!=', '4');
            if ($userNameCheck->count() > 0) {
                if ($userNameCheck->first()->user_id == $request->userId) {

                } else {
                    return response()->json(['status' => false, 'code' => 404, 'error' => 'Username is already exist.'], 404);
                }
            }

            $organization = Organization::where('org_id', $request->organizationId)->where('is_active', '!=', '0');
            if ($organization->count() < 1) {
                return response()->json(['status' => false, 'code' => 404, 'error' => 'Organization is not found.'], 404);
            }

            if ($organization->where('is_primary', $request->isPrimary)->count() > 0) {
                $domainId = $organization->first()->domain_id;
                Domain::where('domain_id', $domainId)->update(['domain_name' => $request->domainName, 'is_production' => $request->domainType]);
            } else {
                $domain = new Domain;
                $domain->domain_name = $request->domainName;
                $domain->is_production = $request->domainType;
                $domain->save();

                $domainId = $domain->domain_id;
            }

            $organizationType = '';
            if ($request->organizationType != '' && $request->organizationType != 'undefined') {
                $organizationType = $request->organizationType;
            }


            $organization = Organization::where('org_id', $request->organizationId)->where('is_active', '!=', '0');
            $organization->update([
                'domain_id' => $domainId,
                'organization_name' => $request->organizationName,
                'organization_notes' => $request->organizationNote,
                'is_primary' => $request->isPrimary,
                'email_id' => $request->userEmailId,
                'phone_number' => $request->userPhoneNumber,
                'address' => $request->address,
                'zip_code' => $request->zipCode,
                'country' => $request->country,
                'state' => $request->state,
                'logo_text' => $request->logoText,
                'organization_type_id' => $organizationType,
                'is_active' => $request->isActive == '' ? $organization->first()->is_active ? $organization->first()->is_active : '1' : $request->isActive,
                'modified_id' => $authId
            ]);

            if ($request->file('organizationLogo') != '') {
                $path = getPathS3Bucket() . '/organization_logo';
                $s3OrganizationLogo = Storage::disk('s3')->put($path, $request->organizationLogo);
                $organizationLogo = substr($s3OrganizationLogo, strrpos($s3OrganizationLogo, '/') + 1);
                $organization->update([
                    'logo_image' => $organizationLogo
                ]);
            }

        } else {
            $validator = Validator::make($request->all(), [
                'organizationId' => 'required',
                'adminLoginId' => 'required',
                'userId' => 'required',
                'organizationLogo' => 'nullable|mimes:jpeg,jpg,png',
                'organizationName' => 'required|max:64',
                'isPrimary' => 'required',
                'primaryOrganization' => 'required',
                'userFirstName' => 'required|max:32',
                'userLastName' => 'required|max:32',
                'userEmailId' => 'nullable|max:50|regex:/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix',
                //'adminUsername' => 'required|max:48|unique:lms_user_login,user_name,'.$request->adminLoginId.',login_id,org_id,'.$request->organizationId,
                'adminUsername' => 'required|max:48',
                'adminPassword' => 'nullable|min:8',
                'role' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
            }

            $primaryOrganizationId = $request->primaryOrganization;
            $userNameCheck = user::join('lms_user_login', 'lms_user_master.user_id', '=', 'lms_user_login.user_id')
                ->where('lms_user_master.org_id', $primaryOrganizationId)->where('lms_user_login.org_id', $primaryOrganizationId)
                ->where('lms_user_login.user_name', 'like', $request->adminUsername)
                //->where('lms_user_master.user_id',$request->userId)
                ->where('lms_user_master.is_active', '!=', '4');
            if ($userNameCheck->count() > 0) {
                if ($userNameCheck->first()->user_id == $request->userId) {

                } else {
                    return response()->json(['status' => false, 'code' => 404, 'error' => 'Username is already exist.'], 404);
                }
            }

            $organization = Organization::where('org_id', $request->organizationId)->where('is_active', '!=', '0');
            if ($organization->count() < 1) {
                return response()->json(['status' => false, 'code' => 404, 'error' => 'Organization is not found.'], 404);
            }


            $domain = DB::table('lms_org_master as lms_org')
                ->join('lms_domain', 'lms_org.domain_id', 'lms_domain.domain_id')
                ->where('lms_org.is_active', '1')
                ->where('lms_domain.is_active', '1')
                ->where('lms_org.org_id', $request->primaryOrganization)
                ->select('lms_domain.domain_id');

            if ($domain->count() > 0) {
                $domainId = $domain->first()->domain_id;
                Domain::where('domain_id', $domainId)->update(['is_production' => $request->domainType]);
            }


            $organization->update([
                'domain_id' => $domainId,
                'organization_name' => $request->organizationName,
                'organization_notes' => $request->organizationNote,
                'is_primary' => $request->isPrimary,
                'parent_org_id' => $request->primaryOrganization,

                'email_id' => $request->userEmailId,
                'contact_email' => $request->userEmailId,
                'phone_number' => $request->userPhoneNumber,


                'address' => $request->address,
                'zip_code' => $request->zipCode,
                'country' => $request->country,
                'state' => $request->state,
                'logo_text' => $request->logoText,
                'organization_type_id' => $request->organizationType,
                'is_active' => $request->isActive == '' ? $organization->first()->is_active ? $organization->first()->is_active : '1' : $request->isActive,
                'modified_id' => $authId
            ]);

            if ($request->file('organizationLogo') != '') {
                $path = getPathS3Bucket() . '/organization_logo';
                $s3OrganizationLogo = Storage::disk('s3')->put($path, $request->organizationLogo);
                $organizationLogo = substr($s3OrganizationLogo, strrpos($s3OrganizationLogo, '/') + 1);
                $organization->update([
                    'logo_image' => $organizationLogo
                ]);
            }

        }


        User::where(['user_id' => $request->userId, 'org_id' => $request->organizationId])->update([
            'email_id' => $request->userEmailId,
            'phone_number' => $request->userPhoneNumber,
            'first_name' => $request->userFirstName,
            'last_name' => $request->userLastName,
            'role_id' => $request->role
        ]);


        Login::where(['login_id' => $request->adminLoginId, 'org_id' => $request->organizationId])->update([
            'domain_id' => $domainId,
            'user_name' => $request->adminUsername
        ]);

        if ($request->adminPassword != '') {
            Login::where(['login_id' => $request->adminLoginId, 'org_id' => $request->organizationId])->update([
                'user_password' => base64_encode($request->adminPassword)
            ]);
        }

        if ($request->tags != '' && $request->tags != 'undefined') {
            $tags = explode(',', $request->tags);
            if (count($tags) > 0) {
                $tagArray = [];
                $tagsArray = [];
                Tag::where('org_id', $request->organizationId)->update(['is_active' => '0']);
                foreach ($tags as $tagName) {
                    if (isset($tagName)) {
                        $tag = Tag::where('tag_name', $tagName)->where('org_id', $request->organizationId);
                        if ($tag->count() > 0) {
                            $tag->update([
                                'is_active' => 1,
                                'modified_id' => $authId,
                                'date_modified' => Carbon::now()
                            ]);
                        } else {
                            $tagArray = [
                                'tag_name' => $tagName,
                                'ref_table_name' => 'lms_org_master',
                                'org_id' => $request->organizationId,
                                'date_created' => Carbon::now(),
                                'date_modified' => Carbon::now(),
                                'created_id' => $authId,
                                'modified_id' => $authId
                            ];
                            $tagsArray[] = $tagArray;
                        }
                    }
                }

                if (isset($tagsArray)) {
                    Tag::insert($tagsArray);
                }
            }
        }

        return response()->json(['status' => true, 'code' => 200, 'message' => 'Organization has been updated successfully.'], 200);
    }



    public function verifyOrganization(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'domainName' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
        }

        $organization = DB::table('lms_org_master as org_master')
            ->join('lms_domain as domain', 'org_master.domain_id', 'domain.domain_id')
            ->where('domain.domain_name', $request->domainName)
            ->where('domain.is_active', '1')
            ->where('domain.is_https', $request->isHttps)
            ->where('org_master.is_primary', '1')
            ->where('org_master.is_active', '1');

        if ($organization->count() > 0) {
            $organization = $organization->select('domain.domain_id as domainId', 'domain.domain_name as domainName', 'org_master.org_id as organizationId', 'org_master.organization_name as organizationName', 'org_master.logo_image as organizationLogo', 'org_master.logo_text as organizationLogoText')->first();

            if ($organization->organizationLogo != '') {
                $organization->organizationLogo = getFileS3Bucket(getPathS3Bucket() . '/organization_logo/' . $organization->organizationLogo);
            }
            return response()->json(['status' => true, 'code' => 200, 'data' => $organization], 200);

        } else {
            return response()->json(['status' => false, 'code' => 400, 'message' => 'Domain is not available.'], 400);
        }
    }

    public function getPrimaryOrganizationList(Request $request)
    {
        $organization = Organization::where('is_primary', 1)
            ->where('is_active', '1')
            ->select('org_id as orgId', 'organization_name as organizationName')
            ->get();
        return response()->json(['status' => true, 'code' => 200, 'data' => $organization], 200);
    }

    public function getPrimaryOrganizationDomain(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'organizationId' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
        }

        $domain = DB::table('lms_org_master as lms_org')

            ->join('lms_domain', 'lms_org.domain_id', 'lms_domain.domain_id')
            ->where('lms_org.is_active', '1')
            ->where('lms_domain.is_active', '1')
            ->where('lms_org.org_id', $request->organizationId)

            ->select('lms_domain.domain_id as domainId', 'lms_domain.domain_name as domainName')
            ->first();
        return response()->json(['status' => true, 'code' => 200, 'data' => $domain], 200);
    }

    public function getCountryList()
    {
        return response()->json(['status' => true, 'code' => 200, 'data' => country()], 200);
    }

    function organizationCode()
    {
        $organization = Organization::count();
        if ($organization > 0) {
            $org_code = Organization::orderBy('org_code', 'DESC')->select('org_code')->first()->org_code;
            $code = $org_code + 1;
        } else {
            $code = '110001';
        }
        return $code;
    }

    function userCode()
    {
        $user = User::count();
        if ($user > 0) {
            $user_guid = User::orderBy('user_guid', 'DESC')->select('user_guid')->first()->user_guid;
            $code = $user_guid + 1;
        } else {
            $code = '111221';
        }
        return $code;
    }

    public function getOrganizationOptionsList(Request $request)
    {
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $withoutSA = $request->withoutSA ? $request->withoutSA : '';
        $organization = [];
        if ($roleId == 1) {
            $organization = DB::table('lms_org_master as organization')
                ->Join('lms_domain as domain', 'organization.domain_id', '=', 'domain.domain_id')
                ->where('organization.is_active', '1')
                ->where('organization.org_id','!=','1')
                // ->where(function($query) use ($withoutSA){
                //     if($withoutSA == 1){
                //         $query->where('organization.org_id','!=',1);
                //     }
                // })
                ->orderBy('organization.organization_name', 'ASC')
                ->select('organization.org_id as organizationId', 'organization.organization_name as organizationName', 'domain.domain_name as domainName')
                ->get();
        } else {
            $organization = DB::table('lms_org_master as organization')
                ->Join('lms_domain as domain', 'organization.domain_id', '=', 'domain.domain_id')
                ->where('organization.is_active', '1')
                ->where('organization.org_id','!=','1')
                ->where('organization.org_id', $organizationId)
                ->orderBy('organization.organization_name', 'ASC')
                ->select('organization.org_id as organizationId', 'organization.organization_name as organizationName', 'domain.domain_name as domainName')
                ->get();
        }
        return response()->json(['status' => true, 'code' => 200, 'data' => $organization], 200);
    }

    public function getUserCompanyList(Request $request)
    {

        $superAdminId = $request->superAdmin;
        $primaryOrganizationId = $request->primaryOrganization;

        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;

        if ($roleId == 1) {
            $organizations = Organization::where('is_active', '!=', '0')
                ->select('org_id as organizationId', 'organization_name as organizationName')
                ->get();
        } else {
            $organizations = Organization::where('is_active', '!=', '0')
                ->where('parent_org_id', $primaryOrganizationId)
                ->select('org_id as organizationId', 'organization_name as organizationName')
                ->get();
        }

        return response()->json(['status' => true, 'code' => 200, 'data' => $organizations], 200);

    }


    public function parentChildOrgList()
    {
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $organizations = Organization::where('is_active', '!=', '0')
            ->where(function ($query) use ($organizationId) {
                $query->where('org_id', $organizationId);
                $query->orWhere('parent_org_id', $organizationId);
            })
            ->select('org_id as organizationId', 'organization_name as organizationName')
            ->get();
        return response()->json(['status' => true, 'code' => 200, 'data' => $organizations], 200);
    }

    public function getAuthenticationType()
    {
        $authenticationTypes = AuthenticationType::where('is_active','1')->select('authentication_type_id as id','authentication_type as authenticationType')->get();
        return response()->json(['status' => true, 'code' => 200, 'data' => $authenticationTypes], 200);
    }
}