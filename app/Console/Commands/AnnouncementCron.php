<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Auth;
use App\Mail\AnnouncementMail;
use Mail;
use DB;

class AnnouncementCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Announcement:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Announcement Mail';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        DB::beginTransaction();

        $nowDate = date('Y-m-d');
        $nowTime = date('H:i');
        $announcements = Announcement::where('is_active','1')->whereDate('from_date','<=',$nowDate)->whereDate('to_date','>=',$nowDate)
        ->whereTime('from_time','<=',$nowTime)->whereTime('to_time','>=',$nowTime);
        if($announcements->count() > 0){
            foreach($announcements->get() as $announcement){

                $organizations = Organization::where('is_active','1');
                if($organizations->count($organizations) > 0){
                    foreach($organizations->get() as $organization){
                        
                        $users = User::where('is_active','1')->where('org_id',$organization->org_id);
                        if($users->count($users) > 0){
                            foreach($users->get() as $user){

                                try{

                                    $mailData = [
                                        'firstName' => $user->first_name,
                                        'lastName' => $user->last_name,
                                        'subject' => $announcement->announcement_title,
                                        'messageBody' => $announcement->announcement_description,
                                        'organizationName' => $organization->organization_name
                                    ];
                        
                        
                                    $organizationLogo = '';
                                    if($organization->logo_image != ''){
                                        $organizationLogo = getFileS3Bucket(getPathS3Bucket().'/organization_logo/'.$organization->logo_image);
                                    }
                        
                                    session(['organizationName' => $organization->organization_name,'organizationLogo' => $organizationLogo]);
                                     
                                    Mail::to($user->email_id)->send(new AnnouncementMail($mailData));

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
