<?php

namespace App\Console\Commands;

use App\Models\OrganizationNotification as OrgNotification;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Auth;

class OrganizationNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'OrganizationNotification:cron';

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
        $nowDate = date('Y-m-d H:i:s');
        $organizationId = Auth::user()->org_id;

        $notifications = OrgNotification::where('is_active','1')->where('notificationDate',$nowDate);
        if($notifications->count() > 0){
            foreach($notifications->get() as $notification){

                $organizations = Organization::where('is_active','1')->where('org_id',$organizationId);
                if($organizations->count($organizations) > 0){
                    $organization = $organizations->first();
                        
                    $users = User::where('is_active','1')->where('role_id','7')->where('org_id',$organization->org_id);
                    if($users->count($users) > 0){
                        foreach($users->get() as $user){

                            try{

                                $listId = env('MAILCHIMP_LIST_ID');
                                $mailchimp = new \Mailchimp(env('MAILCHIMP_KEY'));
                                $campaign = $mailchimp->campaigns->create('regular', [
                                    'list_id' => $listId,
                                    'subject' => $notification->subject,
                                    'from_email' => $organization->email_id,
                                    'from_name' => $organization->organization_name,
                                    'to_name' => $user->first_name.' '.$user->last_name
                                ], [
                                    'html' => $notification->notification_content,
                                    'text' => strip_tags($notification->notification_content)
                                ]);

                                $mailchimp->campaigns->send($campaign['id']);

                            } catch (\Throwable $e) {
                                abort(501, $e->getMessage());
                            }

                        }
                    }
                }
            }
        }
    }
}
