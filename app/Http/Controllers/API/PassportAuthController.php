<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Validator;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Login;
use App\Models\Organization;
use Auth;
use DB;
use App\Http\Controllers\API\BaseController as BaseController;


class PassportAuthController extends BaseController
{
    public function GetMACAdd(){
        ob_start();
        system('getmac');
        $Content = ob_get_contents();
        ob_clean();
        return substr($Content, strpos($Content,'\\')-20, 17);
    }
    public function login(Request $request){

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:48',
            'password' => 'required|string|min:8',
            'organization' => 'required|integer',
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try {
            $Organization = Organization::where('org_id',$request->organization)->orWhere('parent_org_id',$request->organization)->where('is_active','1');
            if($Organization->count() > 0){
                $login = Login::where('user_name',$request->username);
                if($login->count() > 0){
                    $login = $login->select('login_id','user_id','user_password')->first();
                    if(base64_decode($login->user_password) == $request->password){

                        $User = User::where('user_id',$login->user_id)->where('is_active','1');
                        if($User->count() > 0){
                            $user_login_token= $login->createToken('PassportLMS')->accessToken;
                        
                            Login::where('login_id',$login->login_id)->update(['last_login_date'=>Carbon::now(),'ip_address'=>\Request::ip(),'session_id'=>session()->getId(),'mac_address'=>$this->GetMACAdd()]);
                            
                            return response()->json(['status'=>true,'code'=>200,'api_token' => $user_login_token], 200);
                        }else{
                            return response()->json(['status'=>false,'code'=>400,'errors' => 'Username or password incorrect.'], 400);
                        }

                    }else{
                        return response()->json(['status'=>false,'code'=>400,'errors' => 'Username or password incorrect.'], 400);
                    }
                }else{
                    return response()->json(['status'=>false,'code'=>400,'errors' => 'Username or password incorrect.'], 400);
                }

                // if(auth()->attempt(['user_name'=>$request->username,'password'=>$request->password,'org_id' => $request->organization],true)){
                //     //$token = DB::table('oauth_access_tokens')->where('user_id', auth()->user()->login_id);
                //     //if($token->count() > 0){
                //         //return response()->json(['status'=>false,'code'=>409,'errors' => 'You are already logged in to same other device please logout from other device to continue logging in.'], 409);
                //     //}else{ 
                //         $User = User::where('user_id',auth()->user()->user_id)->where('is_active','1');
                //         if($User->count() > 0){
                //             $user_login_token= auth()->user()->createToken('PassportLMS')->accessToken;
                        
                //             Login::where('login_id',auth()->id())->update(['last_login_date'=>Carbon::now(),'ip_address'=>\Request::ip(),'session_id'=>session()->getId(),'mac_address'=>$this->GetMACAdd()]);
                            
                //             return response()->json(['status'=>true,'code'=>200,'api_token' => $user_login_token], 200);
                //         }else{
                //             return response()->json(['status'=>false,'code'=>400,'errors' => 'Username or password incorrect.'], 400);
                //         }
                //     //}
                // }else{
                //     return response()->json(['status'=>false,'code'=>400,'errors' => 'Username or password incorrect.'], 400);
                // }
            }else{
                return response()->json(['status'=>false,'code'=>400,'errors' => 'Username or password incorrect.'], 400);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);  
        }
    }


    public function verify_token(Request $request){
        $User = User::with([
            'role' => function($query){
                $query->where(['is_active'=>'1'])
                ->select('role_id','role_name');
            },
            'organization' => function($query){
                $query->where(['is_active'=>'1'])
                ->select('org_id','organization_name','logo_image', 'logo_text');
            }
        ])->where('user_id',auth()->user()->user_id)->where('is_active','1')->select('user_id','first_name','last_name','role_id','org_id')->first();
        
        $data = [];
        $data['orgId'] = $User->organization->org_id;
        $data['orgName'] = $User->organization->organization_name;
        $data['orgLogoText'] = $User->organization->logo_text;
        $data['userId'] = $User->user_id;
        $data['firstName'] = $User->first_name;
        $data['lastName'] = $User->last_name;
        $data['roleName'] = $User->role->role_name;
        $data['userName'] = Auth::user()->user_name;
        $data['organizationLogo'] = getFileS3Bucket(getPathS3Bucket().'/organization_logo/'.$User->organization->logo_image);
        $data['userPhoto'] = getFileS3Bucket(getPathS3Bucket().'/user_photo/'.Auth::user()->user->user_photo);
        return response()->json(['status'=>true,'code'=>200,'data'=>$data,'api_token' => $request->bearerToken()], 200);
    }

    public function logout(Request $request){
        $token = $request->user()->token();
        $token->delete();
        return response()->json(['status'=>true,'code'=>200,'message' => 'You have been successfully logged out.'], 200);
    }

    public function clear_token(){
        DB::table('oauth_access_tokens')->delete();
    }

    public function previewOrganization(Request $request){
        $validator = Validator::make($request->all(), [
            'userId' => 'required',
            'organizationId' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $user = User::where('user_id',$request->userId)->where('is_active','1');
            if($user->count() > 0){
                $roleId = $user->first()->role_id;
                if($roleId == 1){
                    $organizationUser = User::with([
                        'login_details' => function($query){
                            $query->select('user_id','login_id');
                        }
                    ])->where('org_id',$request->organizationId)->where('is_active','1');
                    if($organizationUser->count() > 0){

                        $userId = $organizationUser->first()->user_id;
                        $loginId = $organizationUser->first()->login_details->login_id;

                        $authId = Auth::user()->user_id;
                        if($authId != $userId){
                            Auth::guard('previewOrganization')->loginUsingId($loginId);
                            $data = [];
                            $data['firstName'] = Auth::guard('previewOrganization')->user()->user->first_name;
                            $data['lastName'] = Auth::guard('previewOrganization')->user()->user->last_name;
                            $data['domain'] = Auth::guard('previewOrganization')->user()->domain->domain_name;
                            

                            $user_login_token= Auth::guard('previewOrganization')->user()->createToken('PassportLMS')->accessToken;
                                    
                            Login::where('login_id',Auth::guard('previewOrganization')->id())->update(['last_login_date'=>Carbon::now(),'ip_address'=>\Request::ip(),'session_id'=>session()->getId(),'mac_address'=>$this->GetMACAdd()]);
                                        
                            return response()->json(['status'=>true,'code'=>200,'api_token' => $user_login_token,'data'=>$data], 200);

                        }else{
                            return response()->json(['status'=>false,'code'=>403,'errors' => 'User is already login.'], 403);
                        }  
                    }else{
                        return response()->json(['status'=>false,'code'=>403,'errors' => 'Organization is not found.'], 403);
                    }
                }else{
                    return response()->json(['status'=>false,'code'=>403,'errors' => 'Permission is only for super admin.'], 403);
                } 
            }else{
                return response()->json(['status'=>false,'code'=>400,'errors' => 'User is not found.'], 400);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function scormPreview(Request $request){
        $validator = Validator::make($request->all(), [
            'userId' => 'required',
            'orgId' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $user = User::with([
            'login_details' => function($query){
                $query->select('user_id','login_id');
            }
        ])->where('user_id',$request->userId)->where('org_id',$request->orgId)->where('is_active','1');
        if($user->count() > 0){

            $loginId = $user->first()->login_details->login_id;

            Auth::guard('scormPreview')->loginUsingId($loginId);

            $data = [];

            $user_login_token= Auth::guard('scormPreview')->user()->createToken('scormPreview')->accessToken;
                                          
            return response()->json(['status'=>true,'code'=>200,'api_token' => $user_login_token,'data'=>$data], 200);

        }else{
            return response()->json(['status'=>false,'code'=>404,'errors' => 'Data is not found.'], 404);
        }
            
    }
}
