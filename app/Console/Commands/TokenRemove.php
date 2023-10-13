<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Auth;
use DB;

class TokenRemove extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'TokenRemove:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Token Remove';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $users = DB::table('oauth_access_tokens as token')
        ->join('lms_user_login as login','token.user_id','=','login.login_id');
        if($users->count() > 0){
            foreach($users->select('login.login_id','login.last_login_date')->get() as $user){
                $to_time = strtotime(Carbon::now());
                $from_time = strtotime($user->last_login_date);
                $diff_time = $to_time - $from_time; // 300 sec.
                if($diff_time >= 300){
                    DB::table('oauth_access_tokens')->where('user_id', $user->login_id)->delete();
                }
            }
        }
    }
}
