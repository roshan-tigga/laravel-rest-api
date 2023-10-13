<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Validator;
use App\Models\User;
use App\Http\Controllers\API\BaseController as BaseController;

class ForgotPasswordController extends BaseController
{
    public function forgotPassword(Request $request) 
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:lms_user_login,authentication_email'
        ]);

        if($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);      
        }
        
        $status = Password::sendResetLink(
            ['authentication_email' => $request->email]
        );

        if ($status == Password::RESET_LINK_SENT) {
            return response()->json(['status'=>true,'code'=>200,'message'=>__($status)], 200);
        }
        return response()->json(['status'=>false,'code'=>400,'errors'=>__($status)], 400);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        if($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);     
        }

        $reset_password_status = Password::reset(['token'=>$request->token,'authentication_email'=>$request->email,'password'=>$request->password], function ($user, $password) {
            $user->user_password = Hash::make($password);
            $user->save();
        });

        if ($reset_password_status == Password::PASSWORD_RESET) {
            return response()->json(['status'=>true,'code'=>200,"message" => "Password has been successfully changed."],200);
        }

        return response()->json(['status'=>false,'code'=>400,"errors" => "Invalid token provided."], 400);

    }
}
