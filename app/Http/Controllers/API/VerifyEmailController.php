<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\Login;
use App\Http\Controllers\Controller;
use App\Http\Controllers\API\BaseController as BaseController;


class VerifyEmailController extends BaseController
{
    public function verifyEmail($user_id, Request $request) {
        if (!$request->hasValidSignature()) {
            return response()->json(['status'=>false,'message'=>401,'error'=>'Invalid/Expired url provided.'],401);
        }
        $user = Login::findOrFail($user_id);
    
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }else{
            return response()->json(['status'=>false,'code' => 401, 'message' => "Email already verified."], 401);
        }
        return response()->json(['status'=>true,'code' => 200, 'message' => "Email verified successfully."], 200);
    }

    public function resendEmail() {
        if (auth()->user()->hasVerifiedEmail()) {
            return response()->json(['status'=>false,'code' => 400, 'message' => "Email already verified."], 400);
        }
    
        auth()->user()->sendEmailVerificationNotification();

        return response()->json(['status'=>true,'code' => 200, 'message' => "Email verification link sent on your email id."], 200);
    }
}
