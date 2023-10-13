<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Auth;
use DB;
use Carbon\Carbon;

class LastActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $to_time = strtotime(Carbon::now());
            $from_time = strtotime(Auth::User()->last_login_date);
            $diff_time = $to_time - $from_time; // 5 min.
            if($diff_time >= 300){
                DB::table('oauth_access_tokens')->where('user_id', Auth::id())->delete();
                return response()->json(['status'=>false,'code'=>401,'error'=>'Your session is about to expired due to inactivity.'],401);
            }else{
                DB::table('lms_user_login')->where('login_id',Auth::id())->update(['last_login_date'=>Carbon::now()]);
            }
        }
        return $next($request);
    }
}
