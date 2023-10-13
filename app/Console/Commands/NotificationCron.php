<?php

namespace App\Console\Commands;

use App\Models\NotificationMaster;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Auth;
use App\Mail\NotificationMail;
use Mail;
use DB;

class NotificationCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Notification:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notification Mail';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        DB::beginTransaction();

        $nowDate = date('Y-m-d H:i:s');
        $notifications = NotificationMaster::where('is_active','1')->where('notificationDate',$nowDate)->where('notification_type','email');
        if($notifications->count() > 0){
            foreach($notifications->get() as $notification){

                $organizations = Organization::where('is_active','1');
                if($organizations->count($organizations) > 0){
                    foreach($organizations->get() as $organization){
                        
                        $users = User::where('is_active','1')->where('role_id','7')->where('org_id',$organization->org_id);
                        if($users->count($users) > 0){
                            foreach($users->get() as $user){

                                try{ 

                                    $mailData = [
                                        'firstName' => $user->first_name,
                                        'lastName' => $user->last_name,
                                        'subject' => $notification->subject,
                                        'messageBody' => $notification->notification_content,
                                        'organizationName' => $organization->organization_name
                                    ];
                        
                        
                                    $organizationLogo = '';
                                    if($organization->logo_image != ''){
                                        $organizationLogo = getFileS3Bucket(getPathS3Bucket().'/organization_logo/'.$organization->logo_image);
                                    }
                        
                                    session(['organizationName' => $organization->organization_name,'organizationLogo' => $organizationLogo]);
                                     
                                    Mail::to($user->email_id)->send(new NotificationMail($mailData));

                                } catch (\Throwable $e) {
                                    abort(501, $e->getMessage());
                                }

                            }
                        }
                    }
                }
            }
        }
        DB::commit();
    }
}
