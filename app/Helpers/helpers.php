<?php
use App\Models\DynamicField;
use App\Models\NotificationDynamicField;
use App\Models\Organization;
use App\Models\User;
use Twilio\Rest\Client;
use App\Models\Location;
use App\Models\JobTitle;
use App\Models\Division;
use App\Models\Area;
use App\Models\GroupOrganizationSetting;
use App\Models\GroupOrganization;
use App\Models\ScoMenisfestReader;
use App\Models\ScoDetails;
use App\Models\OrganizationScoMenisfestReader as OrgScoMenisfestReader;
use App\Models\OrganizationScoDetails as OrgScoDetails;
use Illuminate\Support\Facades\Auth;;
use Illuminate\Support\Facades\Storage;

function getFileS3Bucket($path){
    $path = Storage::disk('s3')->url($path);
    return $path;
    // $s3 = \Storage::disk('s3');
    // $s3->getDriver();
    // $s3->getAdapter();
    // $client = $s3->getClient();
    // $command = $client->getCommand('GetObject', [
    //     'Bucket' => \config('filesystems.disks.s3.bucket'),
    //     'Key' => $path
    // ]);  
    // $request = $client->createPresignedRequest($command, '+20 minutes');
    // return (string) $request->getUri();
}

function getPathS3Bucket(){
    return 'elitelms';
}

function fileUploadS3Bucket($file,$module='media',$location='s3',$request='',$zipFileName=''){
    $pathName = getPathS3Bucket() . '/'.$module;
    $s3MediaUrl = Storage::disk('s3')->put($pathName, $file);
    $mediaUrl = substr($s3MediaUrl, strrpos($s3MediaUrl, '/') + 1);

    if(!empty($request->contentType) || !empty($request->trainingContent)){
        if($request->contentType == 3 || $request->trainingContent == 3 ){

            if(!empty($request->file('mediaUrl'))){
                $mediaSize = $request->file('mediaUrl')->getSize();
                $mediaType = $request->file('mediaUrl')->extension();
                $mediaName = $request->file('mediaUrl')->getClientOriginalName();
            }else{
                $mediaSize = $request->file('video')->getSize();
                $mediaType = $request->file('video')->extension();
                $mediaName = $request->file('video')->getClientOriginalName();
            }

            $mediaFileName = substr($mediaName, 0, strrpos($mediaName, '.'));
            $mediaFileName = str_replace(' ', '_', $mediaFileName);

            $zipFileName = $zipFileName;
            $zipFileNameWithExtension = $zipFileName.'.'.$mediaType;
            $mediaUrl = $zipFileNameWithExtension;

            if(!empty($request->file('mediaUrl'))){
                Storage::disk('public')->put('media/'.$zipFileNameWithExtension, file_get_contents($request->file('mediaUrl')));
            }else{
                Storage::disk('public')->put('media/'.$zipFileNameWithExtension, file_get_contents($request->file('video')));
            }
            $zip = new \ZipArchive();
            if ($zip->open(Storage::disk('public')->path('media/'.$zipFileNameWithExtension), \ZipArchive::CREATE) === TRUE) {
                $zip->extractTo(Storage::disk('public')->path('media/'.$zipFileName));
                $zip->close();

                $files = \File::allFiles(Storage::disk('public')->path('/media/'.$zipFileName));
                foreach ($files as $k => $file) {
                    $dirname = pathinfo($file)['dirname'];
                    $basename = pathinfo($file)['basename'];
                    $explode = explode($zipFileName, $dirname);
                    fileUploadS3Bucket(file_get_contents($dirname . '/' . $basename),$module.'/' . $zipFileName . '/' . $mediaFileName . $explode[1] . '/' . $basename,$location,'','');
                }
            }
        }
    }
    return $mediaUrl;
}

// function scormFileUpload($request,$path,$module,$zipFileName,$mediaId){
//     $authId = Auth::user()->user_id;
//     $organizationId = Auth::user()->org_id;
//     $roleId = Auth::user()->user->role_id;

//     if($module == 'media'){
//         if($request->contentType == 3 || $request->trainingContent == 3 ){

//             if($module == 'media' || $module == 'orgMedia'){
//                 $mediaSize = $request->file('mediaUrl')->getSize();
//                 $mediaType = $request->file('mediaUrl')->extension();
//                 $mediaName = $request->file('mediaUrl')->getClientOriginalName();
//             }
//             if($module == 'course' || $module == 'orgCourse'){
//                 $mediaSize = $request->file('video')->getSize();
//                 $mediaType = $request->file('video')->extension();
//                 $mediaName = $request->file('video')->getClientOriginalName();
//             }

//             $mediaFileName = substr($mediaName, 0, strrpos($mediaName, '.'));
//             $mediaFileName = str_replace(' ', '_', $mediaFileName);

//             $zipFileName = $zipFileName;
//             $zipFileNameWithExtension = $zipFileName.'.'.$mediaType;
//             $mediaUrl = $zipFileNameWithExtension;

//             Storage::disk('public')->put('media/'.$zipFileNameWithExtension, file_get_contents($request->file('mediaUrl')));
//             $zip = new \ZipArchive();
//             if ($zip->open(Storage::disk('public')->path('media/'.$zipFileNameWithExtension), \ZipArchive::CREATE) === TRUE) {
//                 $zip->extractTo(Storage::disk('public')->path('media/'.$zipFileName));

//                 $stream = $zip->getStream('imsmanifest.xml');
//                 $contents = '';
//                 while (!feof($stream)) {
//                     $contents .= fread($stream, 2);
//                 }
//                 fclose($stream);
//                 $dom = new \DOMDocument();

//                 if($dom->loadXML($contents)) {

//                     $manifest = $dom->getElementsByTagName('manifest')->item(0);
//                     $version = @$manifest->attributes->getNamedItem('version')->nodeValue;
//                     $manifestIdentifier = @$manifest->attributes->getNamedItem('identifier')->nodeValue;

//                     $organization = $dom->getElementsByTagName('organization')->item(0);
//                     $title = @$organization->getElementsByTagName('title')->item(0)->textContent;

//                     $resource = $dom->getElementsByTagName('resource')->item(0);
//                     $identifier = @$resource->attributes->getNamedItem('identifier')->nodeValue;
//                     $scormType = @$resource->attributes->getNamedItem('scormType')->nodeValue;


//                     // if($module == 'media' || $module == 'course'){
//                     //     $scoMenisfestReader = new ScoMenisfestReader;
//                     //     $scoMenisfestReader->course_id = '';
//                     //     $scoMenisfestReader->media_id = $mediaId;
//                     //     $scoMenisfestReader->name = $title;
//                     //     $scoMenisfestReader->scormtype = $scormType;
//                     //     $scoMenisfestReader->reference = $identifier;
//                     //     $scoMenisfestReader->version = $version;
//                     //     $scoMenisfestReader->created_id = $authId;
//                     //     $scoMenisfestReader->modified_id = $authId;
//                     //     $scoMenisfestReader->save();
//                     // }
//                     // if($module == 'orgMedia' || $module == 'orgCourse'){
//                     //     $scoMenisfestReader = new OrgScoMenisfestReader;
//                     //     $scoMenisfestReader->course_id = '';
//                     //     $scoMenisfestReader->media_id = $mediaId;
//                     //     $scoMenisfestReader->name = $title;
//                     //     $scoMenisfestReader->scormtype = $scormType;
//                     //     $scoMenisfestReader->reference = $identifier;
//                     //     $scoMenisfestReader->version = $version;
//                     //     $scoMenisfestReader->created_id = $authId;
//                     //     $scoMenisfestReader->modified_id = $authId;
//                     //     $scoMenisfestReader->save();
//                     // }

//                     $items = $dom->getElementsByTagName('item');
//                     $resources = $dom->getElementsByTagName('resource');
//                     if ($items->length > 0) {
//                         foreach ($items as $item) {

//                             $identifierref = @$item->attributes->getNamedItem('identifierref')->nodeValue;
//                             $title = @$item->getElementsByTagName('title')->item(0)->textContent;

//                             if ($resources->length > 0) {
//                                 foreach ($resources as $k => $resource) {
//                                     $identifier = @$resource->attributes->getNamedItem('identifier')->nodeValue;
//                                     $scormType = @$resource->attributes->getNamedItem('scormType')->nodeValue;
//                                     $launch = @$resource->attributes->getNamedItem('href')->nodeValue;

//                                     // if ($identifierref == $identifier) {
//                                     //     if($module == 'media' || $module == 'course'){
//                                     //         $scoDetails = new ScoDetails;
//                                     //         $scoDetails->scorm_id = $scoMenisfestReader->id;
//                                     //         $scoDetails->manifest = $manifestIdentifier;
//                                     //         $scoDetails->identifier = $identifier;
//                                     //         $scoDetails->launch = $launch;
//                                     //         $scoDetails->scormtype = $scormType;
//                                     //         $scoDetails->title = $title;
//                                     //         $scoDetails->organization_id = $organizationId;
//                                     //         $scoDetails->sortorder = $k;
//                                     //         $scoDetails->created_id = $authId;
//                                     //         $scoDetails->modified_id = $authId;
//                                     //         $scoDetails->save();
//                                     //     }
//                                     //     if($module == 'orgMedia' || $module == 'orgCourse'){
//                                     //         $scoDetails = new OrgScoDetails;
//                                     //         $scoDetails->scorm_id = $scoMenisfestReader->id;
//                                     //         $scoDetails->manifest = $manifestIdentifier;
//                                     //         $scoDetails->identifier = $identifier;
//                                     //         $scoDetails->launch = $launch;
//                                     //         $scoDetails->scormtype = $scormType;
//                                     //         $scoDetails->title = $title;
//                                     //         $scoDetails->organization_id = $organizationId;
//                                     //         //$scoDetails->parent_organization_id = '';
//                                     //         $scoDetails->sortorder = $k;
//                                     //         $scoDetails->created_id = $authId;
//                                     //         $scoDetails->modified_id = $authId;
//                                     //         $scoDetails->save();
//                                     //     }
//                                     // }
//                                 }
//                             }
//                         }
//                     }
//                 }
//                 $zip->close();

//                 $files = \File::allFiles(Storage::disk('public')->path('/media/'.$zipFileName));
//                 foreach ($files as $k => $file) {
//                     $dirname = pathinfo($file)['dirname'];
//                     $basename = pathinfo($file)['basename'];
//                     $explode = explode($zipFileName, $dirname);
//                     scormFileUpload(file_get_contents($dirname . '/' . $basename),'/media/' . $zipFileName . '/' . $mediaFileName . $explode[1] . '/' . $basename);
//                 }

//                 //\File::deleteDirectory(Storage::disk('public')->path('media/'.$zipFileName));
//                 //\File::delete(Storage::disk('public')->path('media/'.$zipFileNameWithExtension));
//             }
//         }
//     }
// }

function is_admin($id){
    if($id == 14){
        return true;
    }
    return false;
}

function country(){
    $country = \DB::table('lms_country_master')->where('is_active','1')->orderBy('country','ASC')
    ->select('country_id as countryId', 'iso', 'country', 'is_active as isActive')
    ->get();
    return $country;
}

function dynamicField($notificationContent,$id){
    $dynamicFields = NotificationDynamicField::where('is_active','1')->get();
    if($dynamicFields->count() > 0){
        foreach($dynamicFields as $dynamicField){
            $dynamicFieldsName = $dynamicField->dynamic_fields_name;
            $dynamicFieldsTag = $dynamicField->dynamic_fields_tag;
            $refTableName = $dynamicField->ref_table_name;
            $tableColumnName = $dynamicField->table_column_name;
            //if($refTableName != '' && $tableColumnName != ''){
                if($id !== '')
                {
                    if($dynamicFieldsName == 'User Name'){
                        $columnName = \DB::table('lms_user_master')->where('user_id',$id)->first()->first_name;
                    }
                    if($dynamicFieldsName == 'Company Name'){
                        $orgId = \DB::table('lms_user_master')->where('user_id',$id)->first()->org_id;
                        $columnName = \DB::table('lms_org_master')->where('org_id',$orgId)->first()->organization_name;
                    }
                    if($dynamicFieldsName == 'User Role'){
                        $roleId = \DB::table('lms_user_master')->where('user_id',$id)->first()->role_id;
                        $columnName = \DB::table('lms_roles')->where('role_id',$roleId)->first()->role_name;
                    }
                    if($dynamicFieldsName == 'Login Id'){
                        $columnName = \DB::table('lms_user_master')->where('user_id',$id)->first()->user_id;
                    }
                    if($dynamicFieldsName == 'Email Id'){
                        $columnName = \DB::table('lms_user_master')->where('user_id',$id)->first()->email_id;
                    }
                    if($dynamicFieldsName == 'Job Title'){
                        $jobTitles = \DB::table('lms_user_master')->where('user_id',$id)->first()->job_title;
                        if(!empty($jobTitles)){
                            $expoJobTitles = explode(',',$jobTitles);
                            $columnName = \DB::table('lms_job_title')->whereIn('job_title_id',$expoJobTitles)->select('job_title_name')->pluck('job_title_name')->toArray();
                            $columnName = implode(',',$columnName);
                        }
                    }
                    $notificationContent = str_replace($dynamicFieldsTag,$columnName,$notificationContent);
                }                
            }
        //}
    }
    return $notificationContent;
}

function dynamicFieldForCertificate($notificationContent,$userId){
    $columnName = '';
    $dynamicFields = DynamicField::where('is_active','1')->get();
    if($dynamicFields->count() > 0){
        foreach($dynamicFields as $dynamicField){
            $refTableName = $dynamicField->ref_table_name;
            $tableColumnName = $dynamicField->table_column_name;
            $dynamicFieldsTag = $dynamicField->dynamic_fields_tag;
            $dynamicFieldsValue = $dynamicField->dynamic_fields_value;
            if( $refTableName != ''){
                if($dynamicFieldsValue == 'first_name' || $dynamicFieldsValue == 'last_name' || $dynamicFieldsValue == 'email_id'){
                    $columnName = \DB::table($refTableName)->where('user_id',$userId)->first()->$tableColumnName;
                    if($dynamicFieldsValue == 'job_title'){
                        if(!empty($columnName)){
                            $expoJobTitles = explode(',',$columnName);
                            $columnName = \DB::table('lms_job_title')->whereIn('job_title_id',$expoJobTitles)->select('job_title_name')->pluck('job_title_name')->toArray();
                            $columnName = implode(',',$columnName);
                        }
                    }
                }
                if($dynamicFieldsValue == 'organization_name'){
                    $org_id = \DB::table('lms_user_master')->where('user_id',$userId)->first()->org_id;
                    $columnName = \DB::table($refTableName)->where('org_id',$org_id)->first()->$tableColumnName;
                }
                $notificationContent = str_replace($dynamicFieldsTag,$columnName,$notificationContent);
            }
        }
    }
    return $notificationContent;
}


function twilioMessage($receiverNumber,$message){
    $accountSid = getenv("TWILIO_SID");
    $authToken = getenv("TWILIO_TOKEN");
    $twilioNumber = getenv("TWILIO_FROM");

    if($accountSid != '' && $authToken != '' && $twilioNumber != '' && $receiverNumber != ''){
        $client = new Client($accountSid, $authToken);
        $client->messages->create($receiverNumber, [
            'from' => $twilioNumber, 
            'body' => $message
        ]);
    }
}


function userCode(){
    $user = User::count();
    if($user > 0){
        $user_guid = User::orderBy('user_guid','DESC')->select('user_guid')->first()->user_guid;
        $code = $user_guid+1;
    }else{
        $code = '111221';
    }
    return $code;
}

function groupCode($organizationId)
{
    $group = GroupOrganization::where('org_id',$organizationId)->orderBy('group_code','DESC');
    if($group->count() > 0){
        $groupCode = $group->first()->group_code + 1;
    }else{
        $groupCode = $organizationId.'000000001';
    }
    return $groupCode;
}

function jobTitleGroup($jobTitleIds,$userId,$organizationId,$isAuto,$action,$checkIds)
{
    $authId = \Auth::user()->user_id;

    if(!empty($jobTitleIds))
    {
        if($action == 'edit'){

            $results = array_intersect(explode(',',$jobTitleIds),explode(',',$checkIds));
            if(!empty($results)){
                $jobTitles = JobTitle::whereIn('job_title_id',$results)->where('org_id',$organizationId)->where('is_active','!=','0');
                if($jobTitles->count() > 0){
                    foreach($jobTitles->get() as $jobTitle){
                        
                        $jobTitleName = $jobTitle->job_title_name;
                        $explodeJobTitle = explode(' ',$jobTitleName);

                        $orgSettings = GroupOrganizationSetting::where('is_active','1')
                        ->where(function($query){
                            $query->orWhere('group_setting_id',1);
                            $query->orWhere('group_setting_id',2);
                            $query->orWhere('group_setting_id',3);
                        })
                        ->where('org_id',$organizationId);
                        if($orgSettings->count() > 0){
                            foreach($orgSettings->get() as $orgSetting){
                                $groupSettingId = $orgSetting->group_setting_id;
                                if($groupSettingId == 1 && isset($explodeJobTitle[0])){
                                    $groupName = $explodeJobTitle[0];
                                }
                                if($groupSettingId == 2 && isset($explodeJobTitle[1])){
                                    $groupName = $explodeJobTitle[1];
                                }
                                if($groupSettingId == 3){
                                    $groupName = $jobTitleName;
                                }

                                if(!empty($groupName)){
                                    $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                                    if($group->count() > 0){
                                    $groupId = $group->first()->group_id;

                                        $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId)->where('is_active','!=','0');
                                        if($groupAssign->count() > 0){

                                        }else{
                                            \DB::table('lms_user_org_group')->insert([
                                                'group_id'=>$groupId,
                                                'org_id'=>$organizationId,
                                                'user_id'=>$userId,
                                                'date_created' => date('Y-m-d H:i:s'),
                                                'date_modified' => date('Y-m-d H:i:s'),
                                                'created_id' => $authId,
                                                'modified_id' => $authId,
                                            ]);
                                        }
                                    }else{

                                        $groupMaster = new GroupOrganization;
                                        $groupMaster->group_name = $groupName;
                                        $groupMaster->group_code = groupCode($organizationId);
                                        $groupMaster->org_id = $organizationId;
                                        $groupMaster->is_active = 1;
                                        $groupMaster->group_type = 1;
                                        $groupMaster->is_auto = $isAuto;
                                        $groupMaster->created_id = $authId;
                                        $groupMaster->modified_id = $authId;
                                        $groupMaster->save();

                                        if($groupMaster->group_id){
                                            $groupId = $groupMaster->group_id;
                                            \DB::table('lms_user_org_group')->insert([
                                                'group_id'=>$groupId,
                                                'org_id'=>$organizationId,
                                                'user_id'=>$userId,
                                                'date_created' => date('Y-m-d H:i:s'),
                                                'date_modified' => date('Y-m-d H:i:s'),
                                                'created_id' => $authId,
                                                'modified_id' => $authId,
                                            ]);
                                        } 
                                    }
                                }
                            } 
                        }
                    }
                }
            }

            $results = array_diff(explode(',',$jobTitleIds),explode(',',$checkIds));
            if(!empty($results)){
                $jobTitles = JobTitle::whereIn('job_title_id',$results)->where('org_id',$organizationId)->where('is_active','!=','0');
                if($jobTitles->count() > 0){
                    foreach($jobTitles->get() as $jobTitle){

                        $jobTitleName = $jobTitle->job_title_name;
                        $explodeJobTitle = explode(' ',$jobTitleName);

                        $orgSettings = GroupOrganizationSetting::where('is_active','1')
                        ->where(function($query){
                            $query->orWhere('group_setting_id',1);
                            $query->orWhere('group_setting_id',2);
                            $query->orWhere('group_setting_id',3);
                        })
                        ->where('org_id',$organizationId);
                        if($orgSettings->count() > 0){
                            foreach($orgSettings->get() as $orgSetting){
                                $groupSettingId = $orgSetting->group_setting_id;
                                if($groupSettingId == 1 && isset($explodeJobTitle[0])){
                                    $groupName = $explodeJobTitle[0];
                                }
                                if($groupSettingId == 2 && isset($explodeJobTitle[1])){
                                    $groupName = $explodeJobTitle[1];
                                }
                                if($groupSettingId == 3){
                                    $groupName = $jobTitleName;
                                }

                                if(!empty($groupName)){
                                    $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                                    if($group->count() > 0){
                                    $groupId = $group->first()->group_id;

                                        $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId)->where('is_active','!=','0');
                                        if($groupAssign->count() > 0){

                                        }else{
                                            \DB::table('lms_user_org_group')->insert([
                                                'group_id'=>$groupId,
                                                'org_id'=>$organizationId,
                                                'user_id'=>$userId,
                                                'date_created' => date('Y-m-d H:i:s'),
                                                'date_modified' => date('Y-m-d H:i:s'),
                                                'created_id' => $authId,
                                                'modified_id' => $authId,
                                            ]);
                                        }
                                    }else{

                                        $groupMaster = new GroupOrganization;
                                        $groupMaster->group_name = $groupName;
                                        $groupMaster->group_code = groupCode($organizationId);
                                        $groupMaster->org_id = $organizationId;
                                        $groupMaster->is_active = 1;
                                        $groupMaster->group_type = 1;
                                        $groupMaster->is_auto = $isAuto;
                                        $groupMaster->created_id = $authId;
                                        $groupMaster->modified_id = $authId;
                                        $groupMaster->save();

                                        if($groupMaster->group_id){
                                            $groupId = $groupMaster->group_id;
                                            \DB::table('lms_user_org_group')->insert([
                                                'group_id'=>$groupId,
                                                'org_id'=>$organizationId,
                                                'user_id'=>$userId,
                                                'date_created' => date('Y-m-d H:i:s'),
                                                'date_modified' => date('Y-m-d H:i:s'),
                                                'created_id' => $authId,
                                                'modified_id' => $authId,
                                            ]);
                                        } 
                                    }
                                }
                            } 
                        }
                    }
                }

                $jobTitles = JobTitle::whereIn('job_title_id',explode(',',$checkIds))->where('org_id',$organizationId)->where('is_active','!=','0');
                if($jobTitles->count() > 0){
                    foreach($jobTitles->get() as $jobTitle){

                        $jobTitleName = $jobTitle->job_title_name;
                        $explodeJobTitle = explode(' ',$jobTitleName);

                        $orgSettings = GroupOrganizationSetting::where('is_active','1')
                        ->where(function($query){
                            $query->orWhere('group_setting_id',1);
                            $query->orWhere('group_setting_id',2);
                            $query->orWhere('group_setting_id',3);
                        })
                        ->where('org_id',$organizationId);
                        if($orgSettings->count() > 0){
                            foreach($orgSettings->get() as $orgSetting){
                                $groupSettingId = $orgSetting->group_setting_id;
                                if($groupSettingId == 1 && isset($explodeJobTitle[0])){
                                    $groupName = $explodeJobTitle[0];
                                }
                                if($groupSettingId == 2 && isset($explodeJobTitle[1])){
                                    $groupName = $explodeJobTitle[1];
                                }
                                if($groupSettingId == 3){
                                    $groupName = $jobTitleName;
                                }

                                if(!empty($groupName)){
                                    $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                                    if($group->count() > 0){
                                        $groupId = $group->first()->group_id;

                                        $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId);
                                        if($groupAssign->count() > 0){
                                            $groupAssign->update([
                                                'is_active' => 0,
                                                'date_modified' => date('Y-m-d H:i:s'),
                                                'modified_id' => $authId,
                                            ]);
                                        }
                                    }
                                }
                            } 
                        }
                    }
                }
            }
        }else{
            $jobTitles = JobTitle::whereIn('job_title_id',explode(',',$jobTitleIds))->where('org_id',$organizationId)->where('is_active','!=','0');
            if($jobTitles->count() > 0){
                foreach($jobTitles->get() as $jobTitle){

                    $jobTitleName = $jobTitle->job_title_name;
                    $explodeJobTitle = explode(' ',$jobTitleName);

                    $orgSettings = GroupOrganizationSetting::where('is_active','1')
                    ->where(function($query){
                        $query->orWhere('group_setting_id',1);
                        $query->orWhere('group_setting_id',2);
                        $query->orWhere('group_setting_id',3);
                    })
                    ->where('org_id',$organizationId);
                    if($orgSettings->count() > 0){
                        foreach($orgSettings->get() as $orgSetting){
                            $groupSettingId = $orgSetting->group_setting_id;
                            if($groupSettingId == 1 && isset($explodeJobTitle[0])){
                                $groupName = $explodeJobTitle[0];
                            }
                            if($groupSettingId == 2 && isset($explodeJobTitle[1])){
                                $groupName = $explodeJobTitle[1];
                            }
                            if($groupSettingId == 3){
                                $groupName = $jobTitleName;
                            }

                            if(!empty($groupName)){
                                $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                                if($group->count() > 0){
                                $groupId = $group->first()->group_id;

                                    $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId)->where('is_active','!=','0');
                                    if($groupAssign->count() > 0){

                                    }else{
                                        \DB::table('lms_user_org_group')->insert([
                                            'group_id'=>$groupId,
                                            'org_id'=>$organizationId,
                                            'user_id'=>$userId,
                                            'date_created' => date('Y-m-d H:i:s'),
                                            'date_modified' => date('Y-m-d H:i:s'),
                                            'created_id' => $authId,
                                            'modified_id' => $authId,
                                        ]);
                                    }
                                }else{

                                    $groupMaster = new GroupOrganization;
                                    $groupMaster->group_name = $groupName;
                                    $groupMaster->group_code = groupCode($organizationId);
                                    $groupMaster->org_id = $organizationId;
                                    $groupMaster->is_active = 1;
                                    $groupMaster->group_type = 1;
                                    $groupMaster->is_auto = $isAuto;
                                    $groupMaster->created_id = $authId;
                                    $groupMaster->modified_id = $authId;
                                    $groupMaster->save();

                                    if($groupMaster->group_id){
                                        $groupId = $groupMaster->group_id;
                                        \DB::table('lms_user_org_group')->insert([
                                            'group_id'=>$groupId,
                                            'org_id'=>$organizationId,
                                            'user_id'=>$userId,
                                            'date_created' => date('Y-m-d H:i:s'),
                                            'date_modified' => date('Y-m-d H:i:s'),
                                            'created_id' => $authId,
                                            'modified_id' => $authId,
                                        ]);
                                    } 
                                }
                            }
                        } 
                    }
                }
            }
        }
    }
}

function areaGroup($areaIds,$userId,$organizationId,$isAuto,$action,$checkIds)
{
    $authId = \Auth::user()->user_id;

    if(!empty($areaIds))
    {
        if($action == 'edit'){
            $results = array_intersect(explode(',',$areaIds),explode(',',$checkIds));
            if(!empty($results)){
                $areas = Area::whereIn('area_id',$results)->where('org_id',$organizationId)->where('is_active','!=','0');
                if($areas->count() > 0){
                    foreach($areas->get() as $area){
                        $groupName = $area->area_name;
                        
                        $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','6')->where('org_id',$organizationId);
                        if($orgSettings->count() > 0){
                                
                            $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                            if($group->count() > 0){
                                $groupId = $group->first()->group_id;

                                $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId)->where('is_active','!=','0');
                                if($groupAssign->count() > 0){

                                }else{
                                    \DB::table('lms_user_org_group')->insert([
                                        'group_id'=>$groupId,
                                        'org_id'=>$organizationId,
                                        'user_id'=>$userId,
                                        'date_created' => date('Y-m-d H:i:s'),
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                    ]);
                                }
                            }else{

                                $groupMaster = new GroupOrganization;
                                $groupMaster->group_name = $groupName;
                                $groupMaster->group_code = groupCode($organizationId);
                                $groupMaster->org_id = $organizationId;
                                $groupMaster->is_active = 1;
                                $groupMaster->group_type = 3;
                                $groupMaster->is_auto = $isAuto;
                                $groupMaster->created_id = $authId;
                                $groupMaster->modified_id = $authId;
                                $groupMaster->save();

                                if($groupMaster->group_id){
                                    $groupId = $groupMaster->group_id;
                                    \DB::table('lms_user_org_group')->insert([
                                        'group_id'=>$groupId,
                                        'org_id'=>$organizationId,
                                        'user_id'=>$userId,
                                        'date_created' => date('Y-m-d H:i:s'),
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                    ]);
                                } 
                            }
                            
                        }
                    }
                }
            }

            $results = array_diff(explode(',',$areaIds),explode(',',$checkIds));
            if(!empty($results)){
                $areas = Area::whereIn('area_id',$results)->where('org_id',$organizationId)->where('is_active','!=','0');
                if($areas->count() > 0){
                    foreach($areas->get() as $area){
                        $groupName = $area->area_name;
                        
                        $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','6')->where('org_id',$organizationId);
                        if($orgSettings->count() > 0){
                                
                            $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                            if($group->count() > 0){
                                $groupId = $group->first()->group_id;

                                $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId)->where('is_active','!=','0');
                                if($groupAssign->count() > 0){

                                }else{
                                    \DB::table('lms_user_org_group')->insert([
                                        'group_id'=>$groupId,
                                        'org_id'=>$organizationId,
                                        'user_id'=>$userId,
                                        'date_created' => date('Y-m-d H:i:s'),
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                    ]);
                                }
                            }else{

                                $groupMaster = new GroupOrganization;
                                $groupMaster->group_name = $groupName;
                                $groupMaster->group_code = groupCode($organizationId);
                                $groupMaster->org_id = $organizationId;
                                $groupMaster->is_active = 1;
                                $groupMaster->group_type = 3;
                                $groupMaster->is_auto = $isAuto;
                                $groupMaster->created_id = $authId;
                                $groupMaster->modified_id = $authId;
                                $groupMaster->save();

                                if($groupMaster->group_id){
                                    $groupId = $groupMaster->group_id;
                                    \DB::table('lms_user_org_group')->insert([
                                        'group_id'=>$groupId,
                                        'org_id'=>$organizationId,
                                        'user_id'=>$userId,
                                        'date_created' => date('Y-m-d H:i:s'),
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                    ]);
                                } 
                            }
                            
                        }
                    }
                }
                $areas = Area::whereIn('area_id',explode(',',$checkIds))->where('org_id',$organizationId)->where('is_active','!=','0');
                if($areas->count() > 0){
                    foreach($areas->get() as $area){
                        $groupName = $area->area_name;
                    
                        $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','6')->where('org_id',$organizationId);
                        if($orgSettings->count() > 0){
                                
                            $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                            if($group->count() > 0){
                                $groupId = $group->first()->group_id;

                                $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId);
                                if($groupAssign->count() > 0){
                                    $groupAssign->update([
                                        'is_active' => 0,
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'modified_id' => $authId,
                                    ]);
                                }
                            } 
                        }
                    }
                }
            }
        }else{
            $areas = Area::whereIn('area_id',explode(',',$areaIds))->where('org_id',$organizationId)->where('is_active','!=','0');
            if($areas->count() > 0){
                foreach($areas->get() as $area){
                    $groupName = $area->area_name;
                    
                    $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','6')->where('org_id',$organizationId);
                    if($orgSettings->count() > 0){
                            
                        $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($group->count() > 0){
                            $groupId = $group->first()->group_id;

                            $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId)->where('is_active','!=','0');
                            if($groupAssign->count() > 0){

                            }else{
                                \DB::table('lms_user_org_group')->insert([
                                    'group_id'=>$groupId,
                                    'org_id'=>$organizationId,
                                    'user_id'=>$userId,
                                    'date_created' => date('Y-m-d H:i:s'),
                                    'date_modified' => date('Y-m-d H:i:s'),
                                    'created_id' => $authId,
                                    'modified_id' => $authId,
                                ]);
                            }
                        }else{

                            $groupMaster = new GroupOrganization;
                            $groupMaster->group_name = $groupName;
                            $groupMaster->group_code = groupCode($organizationId);
                            $groupMaster->org_id = $organizationId;
                            $groupMaster->is_active = 1;
                            $groupMaster->group_type = 3;
                            $groupMaster->is_auto = $isAuto;
                            $groupMaster->created_id = $authId;
                            $groupMaster->modified_id = $authId;
                            $groupMaster->save();

                            if($groupMaster->group_id){
                                $groupId = $groupMaster->group_id;
                                \DB::table('lms_user_org_group')->insert([
                                    'group_id'=>$groupId,
                                    'org_id'=>$organizationId,
                                    'user_id'=>$userId,
                                    'date_created' => date('Y-m-d H:i:s'),
                                    'date_modified' => date('Y-m-d H:i:s'),
                                    'created_id' => $authId,
                                    'modified_id' => $authId,
                                ]);
                            } 
                        }
                        
                    }
                }
            }
        }
    }
}

function locationGroup($locationIds,$userId,$organizationId,$isAuto,$action,$checkIds)
{
    $authId = \Auth::user()->user_id;

    if(!empty($locationIds))
    {
        if($action == 'edit'){
            $results = array_intersect(explode(',',$locationIds),explode(',',$checkIds));
            if(!empty($results)){
                $locations = Location::whereIn('location_id',$results)->where('org_id',$organizationId)->where('is_active','!=','0');
                if($locations->count() > 0){
                    foreach($locations->get() as $location){
                        $groupName = $location->location_name;
                        
                        $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','7')->where('org_id',$organizationId);
                        if($orgSettings->count() > 0){
                                
                            $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                            if($group->count() > 0){
                                $groupId = $group->first()->group_id;
        
                                $groupAssign = \DB::table('lms_user_org_group')->where('user_id',$userId)->where('group_id',$groupId)->where('org_id',$organizationId)->where('is_active','!=','0');
                                if($groupAssign->count() > 0){
        
                                }else{
                                    \DB::table('lms_user_org_group')->insert([
                                        'group_id'=>$groupId,
                                        'org_id'=>$organizationId,
                                        'user_id'=>$userId,
                                        'date_created' => date('Y-m-d H:i:s'),
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                    ]);
                                }
                            }else{
        
                                $groupMaster = new GroupOrganization;
                                $groupMaster->group_name = $groupName;
                                $groupMaster->group_code = groupCode($organizationId);
                                $groupMaster->org_id = $organizationId;
                                $groupMaster->is_active = 1;
                                $groupMaster->group_type = 4;
                                $groupMaster->is_auto = $isAuto;
                                $groupMaster->created_id = $authId;
                                $groupMaster->modified_id = $authId;
                                $groupMaster->save();
        
                                if($groupMaster->group_id){
                                    $groupId = $groupMaster->group_id;
                                    \DB::table('lms_user_org_group')->insert([
                                        'group_id'=>$groupId,
                                        'org_id'=>$organizationId,
                                        'user_id'=>$userId,
                                        'date_created' => date('Y-m-d H:i:s'),
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                    ]);
                                } 
                            }
                            
                        }
                    }
                }
            }
            
            $results = array_diff(explode(',',$locationIds),explode(',',$checkIds));
            if(!empty($results)){
                $locations = Location::whereIn('location_id',$results)->where('org_id',$organizationId)->where('is_active','!=','0');
                if($locations->count() > 0){
                    foreach($locations->get() as $location){
                        $groupName = $location->location_name;
                        
                        $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','7')->where('org_id',$organizationId);
                        if($orgSettings->count() > 0){
                                
                            $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                            if($group->count() > 0){
                                $groupId = $group->first()->group_id;
        
                                $groupAssign = \DB::table('lms_user_org_group')->where('user_id',$userId)->where('group_id',$groupId)->where('org_id',$organizationId)->where('is_active','!=','0');
                                if($groupAssign->count() > 0){
        
                                }else{
                                    \DB::table('lms_user_org_group')->insert([
                                        'group_id'=>$groupId,
                                        'org_id'=>$organizationId,
                                        'user_id'=>$userId,
                                        'date_created' => date('Y-m-d H:i:s'),
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                    ]);
                                }
                            }else{
        
                                $groupMaster = new GroupOrganization;
                                $groupMaster->group_name = $groupName;
                                $groupMaster->group_code = groupCode($organizationId);
                                $groupMaster->org_id = $organizationId;
                                $groupMaster->is_active = 1;
                                $groupMaster->group_type = 4;
                                $groupMaster->is_auto = $isAuto;
                                $groupMaster->created_id = $authId;
                                $groupMaster->modified_id = $authId;
                                $groupMaster->save();
        
                                if($groupMaster->group_id){
                                    $groupId = $groupMaster->group_id;
                                    \DB::table('lms_user_org_group')->insert([
                                        'group_id'=>$groupId,
                                        'org_id'=>$organizationId,
                                        'user_id'=>$userId,
                                        'date_created' => date('Y-m-d H:i:s'),
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                    ]);
                                } 
                            }
                            
                        }
                    }
                }

                $locations = Location::whereIn('location_id',explode(',',$checkIds))->where('org_id',$organizationId)->where('is_active','!=','0');
                if($locations->count() > 0){
                    foreach($locations->get() as $location){
                        $groupName = $location->location_name;
                        
                        $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','7')->where('org_id',$organizationId);
                        if($orgSettings->count() > 0){
                                
                            $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                            if($group->count() > 0){
                                $groupId = $group->first()->group_id;
        
                                $groupAssign = \DB::table('lms_user_org_group')->where('user_id',$userId)->where('group_id',$groupId)->where('org_id',$organizationId);
                                if($groupAssign->count() > 0){
                                    $groupAssign->update([
                                        'is_active' => 0,
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'modified_id' => $authId,
                                    ]);
                                }
                            } 
                        }
                    }
                }
            }
        }else{
            $locations = Location::whereIn('location_id',explode(',',$locationIds))->where('org_id',$organizationId)->where('is_active','!=','0');
            if($locations->count() > 0){
                foreach($locations->get() as $location){
                    $groupName = $location->location_name;
                    
                    $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','7')->where('org_id',$organizationId);
                    if($orgSettings->count() > 0){
                            
                        $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($group->count() > 0){
                            $groupId = $group->first()->group_id;

                            $groupAssign = \DB::table('lms_user_org_group')->where('user_id',$userId)->where('group_id',$groupId)->where('org_id',$organizationId)->where('is_active','!=','0');
                            if($groupAssign->count() > 0){

                            }else{
                                \DB::table('lms_user_org_group')->insert([
                                    'group_id'=>$groupId,
                                    'org_id'=>$organizationId,
                                    'user_id'=>$userId,
                                    'date_created' => date('Y-m-d H:i:s'),
                                    'date_modified' => date('Y-m-d H:i:s'),
                                    'created_id' => $authId,
                                    'modified_id' => $authId,
                                ]);
                            }
                        }else{

                            $groupMaster = new GroupOrganization;
                            $groupMaster->group_name = $groupName;
                            $groupMaster->group_code = groupCode($organizationId);
                            $groupMaster->org_id = $organizationId;
                            $groupMaster->is_active = 1;
                            $groupMaster->group_type = 4;
                            $groupMaster->is_auto = $isAuto;
                            $groupMaster->created_id = $authId;
                            $groupMaster->modified_id = $authId;
                            $groupMaster->save();

                            if($groupMaster->group_id){
                                $groupId = $groupMaster->group_id;
                                \DB::table('lms_user_org_group')->insert([
                                    'group_id'=>$groupId,
                                    'org_id'=>$organizationId,
                                    'user_id'=>$userId,
                                    'date_created' => date('Y-m-d H:i:s'),
                                    'date_modified' => date('Y-m-d H:i:s'),
                                    'created_id' => $authId,
                                    'modified_id' => $authId,
                                ]);
                            } 
                        }
                        
                    }
                }
            }
        }
    }
}

function divisionGroup($divisionIds,$userId,$organizationId,$isAuto,$action,$checkIds)
{
    $authId = \Auth::user()->user_id;

    if(!empty($divisionIds))
    {
        if($action == 'edit'){

            $results = array_intersect(explode(',',$divisionIds),explode(',',$checkIds));
            if(!empty($results)){
                $divisions = Division::whereIn('division_id',$results)->where('org_id',$organizationId)->where('is_active','!=','0');
                if($divisions->count() > 0){
                    foreach($divisions->get() as $division){
                        $groupName = $division->division_name;
                        
                        $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','5')->where('org_id',$organizationId);
                        if($orgSettings->count() > 0){
                                
                            $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                            if($group->count() > 0){
                                $groupId = $group->first()->group_id;

                                $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId)->where('is_active','!=','0');
                                if($groupAssign->count() > 0){

                                }else{
                                    \DB::table('lms_user_org_group')->insert([
                                        'group_id'=>$groupId,
                                        'org_id'=>$organizationId,
                                        'user_id'=>$userId,
                                        'date_created' => date('Y-m-d H:i:s'),
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                    ]);
                                }
                            }else{

                                $groupMaster = new GroupOrganization;
                                $groupMaster->group_name = $groupName;
                                $groupMaster->group_code = groupCode($organizationId);
                                $groupMaster->org_id = $organizationId;
                                $groupMaster->is_active = 1;
                                $groupMaster->group_type = 2;
                                $groupMaster->is_auto = $isAuto;
                                $groupMaster->created_id = $authId;
                                $groupMaster->modified_id = $authId;
                                $groupMaster->save();

                                if($groupMaster->group_id){
                                    $groupId = $groupMaster->group_id;
                                    \DB::table('lms_user_org_group')->insert([
                                        'group_id'=>$groupId,
                                        'org_id'=>$organizationId,
                                        'user_id'=>$userId,
                                        'date_created' => date('Y-m-d H:i:s'),
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                    ]);
                                } 
                            }
                            
                        }
                    }
                }
            }
            $results = array_diff(explode(',',$divisionIds),explode(',',$checkIds));
            if(!empty($results)){
                $divisions = Division::whereIn('division_id',$results)->where('org_id',$organizationId)->where('is_active','!=','0');
                if($divisions->count() > 0){
                    foreach($divisions->get() as $division){
                        $groupName = $division->division_name;
                        
                        $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','5')->where('org_id',$organizationId);
                        if($orgSettings->count() > 0){
                                
                            $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                            if($group->count() > 0){
                                $groupId = $group->first()->group_id;

                                $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId)->where('is_active','!=','0');
                                if($groupAssign->count() > 0){

                                }else{
                                    \DB::table('lms_user_org_group')->insert([
                                        'group_id'=>$groupId,
                                        'org_id'=>$organizationId,
                                        'user_id'=>$userId,
                                        'date_created' => date('Y-m-d H:i:s'),
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                    ]);
                                }
                            }else{

                                $groupMaster = new GroupOrganization;
                                $groupMaster->group_name = $groupName;
                                $groupMaster->group_code = groupCode($organizationId);
                                $groupMaster->org_id = $organizationId;
                                $groupMaster->is_active = 1;
                                $groupMaster->group_type = 2;
                                $groupMaster->is_auto = $isAuto;
                                $groupMaster->created_id = $authId;
                                $groupMaster->modified_id = $authId;
                                $groupMaster->save();

                                if($groupMaster->group_id){
                                    $groupId = $groupMaster->group_id;
                                    \DB::table('lms_user_org_group')->insert([
                                        'group_id'=>$groupId,
                                        'org_id'=>$organizationId,
                                        'user_id'=>$userId,
                                        'date_created' => date('Y-m-d H:i:s'),
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                    ]);
                                } 
                            }
                            
                        }
                    }
                }

                $divisions = Division::whereIn('division_id',explode(',',$checkIds))->where('org_id',$organizationId)->where('is_active','!=','0');
                if($divisions->count() > 0){
                    foreach($divisions->get() as $division){
                        $groupName = $division->division_name;
                        
                        $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','5')->where('org_id',$organizationId);
                        if($orgSettings->count() > 0){
                                
                            $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                            if($group->count() > 0){
                                $groupId = $group->first()->group_id;

                                $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId);
                                if($groupAssign->count() > 0){
                                    $groupAssign->update([
                                        'is_active' => 0,
                                        'date_modified' => date('Y-m-d H:i:s'),
                                        'modified_id' => $authId,
                                    ]);
                                }
                            }  
                        }
                    }
                }
            }
        }else{
            $divisions = Division::whereIn('division_id',explode(',',$divisionIds))->where('org_id',$organizationId)->where('is_active','!=','0');
            if($divisions->count() > 0){
                foreach($divisions->get() as $division){
                    $groupName = $division->division_name;
                    
                    $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','5')->where('org_id',$organizationId);
                    if($orgSettings->count() > 0){
                            
                        $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($group->count() > 0){
                            $groupId = $group->first()->group_id;

                            $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId)->where('is_active','!=','0');
                            if($groupAssign->count() > 0){

                            }else{
                                \DB::table('lms_user_org_group')->insert([
                                    'group_id'=>$groupId,
                                    'org_id'=>$organizationId,
                                    'user_id'=>$userId,
                                    'date_created' => date('Y-m-d H:i:s'),
                                    'date_modified' => date('Y-m-d H:i:s'),
                                    'created_id' => $authId,
                                    'modified_id' => $authId,
                                ]);
                            }
                        }else{

                            $groupMaster = new GroupOrganization;
                            $groupMaster->group_name = $groupName;
                            $groupMaster->group_code = groupCode($organizationId);
                            $groupMaster->org_id = $organizationId;
                            $groupMaster->is_active = 1;
                            $groupMaster->group_type = 2;
                            $groupMaster->is_auto = $isAuto;
                            $groupMaster->created_id = $authId;
                            $groupMaster->modified_id = $authId;
                            $groupMaster->save();

                            if($groupMaster->group_id){
                                $groupId = $groupMaster->group_id;
                                \DB::table('lms_user_org_group')->insert([
                                    'group_id'=>$groupId,
                                    'org_id'=>$organizationId,
                                    'user_id'=>$userId,
                                    'date_created' => date('Y-m-d H:i:s'),
                                    'date_modified' => date('Y-m-d H:i:s'),
                                    'created_id' => $authId,
                                    'modified_id' => $authId,
                                ]);
                            } 
                        }
                        
                    }
                }
            }
        }
    }
}

function companyGroup($userId,$organizationId,$isAuto,$action,$checkId)
{
    $authId = \Auth::user()->user_id;

    if(!empty($organizationId))
    {
        if($action == 'edit'){
            if($organizationId == $checkId){
                $organization = Organization::where('org_id',$organizationId)->where('is_active','!=','0');
                if($organization->count() > 0){
                    $organization = $organization->select('organization_name')->first();
                    $groupName = $organization->organization_name;
                    
                    $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','4')->where('org_id',$organizationId);
                    if($orgSettings->count() > 0){
                            
                        $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($group->count() > 0){
                            $groupId = $group->first()->group_id;
    
                            $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId)->where('is_active','!=','0');
                            if($groupAssign->count() > 0){
    
                            }else{
                                \DB::table('lms_user_org_group')->insert([
                                    'group_id'=>$groupId,
                                    'org_id'=>$organizationId,
                                    'user_id'=>$userId,
                                    'date_created' => date('Y-m-d H:i:s'),
                                    'date_modified' => date('Y-m-d H:i:s'),
                                    'created_id' => $authId,
                                    'modified_id' => $authId,
                                ]);
                            }
                        }else{
    
                            $groupMaster = new GroupOrganization;
                            $groupMaster->group_name = $groupName;
                            $groupMaster->group_code = groupCode($organizationId);
                            $groupMaster->org_id = $organizationId;
                            $groupMaster->is_active = 1;
                            $groupMaster->group_type = 5;
                            $groupMaster->is_auto = $isAuto;
                            $groupMaster->created_id = $authId;
                            $groupMaster->modified_id = $authId;
                            $groupMaster->save();
    
                            if($groupMaster->group_id){
                                $groupId = $groupMaster->group_id;
                                \DB::table('lms_user_org_group')->insert([
                                    'group_id'=>$groupId,
                                    'org_id'=>$organizationId,
                                    'user_id'=>$userId,
                                    'date_created' => date('Y-m-d H:i:s'),
                                    'date_modified' => date('Y-m-d H:i:s'),
                                    'created_id' => $authId,
                                    'modified_id' => $authId,
                                ]);
                            } 
                        }
                        
                    }
                }
            }else{
                $organization = Organization::where('org_id',$organizationId)->where('is_active','!=','0');
                if($organization->count() > 0){
                    $organization = $organization->select('organization_name')->first();
                    $groupName = $organization->organization_name;
                    
                    $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','4')->where('org_id',$organizationId);
                    if($orgSettings->count() > 0){
                            
                        $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($group->count() > 0){
                            $groupId = $group->first()->group_id;
    
                            $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId)->where('is_active','!=','0');
                            if($groupAssign->count() > 0){
    
                            }else{
                                \DB::table('lms_user_org_group')->insert([
                                    'group_id'=>$groupId,
                                    'org_id'=>$organizationId,
                                    'user_id'=>$userId,
                                    'date_created' => date('Y-m-d H:i:s'),
                                    'date_modified' => date('Y-m-d H:i:s'),
                                    'created_id' => $authId,
                                    'modified_id' => $authId,
                                ]);
                            }
                        }else{
    
                            $groupMaster = new GroupOrganization;
                            $groupMaster->group_name = $groupName;
                            $groupMaster->group_code = groupCode($organizationId);
                            $groupMaster->org_id = $organizationId;
                            $groupMaster->is_active = 1;
                            $groupMaster->group_type = 5;
                            $groupMaster->is_auto = $isAuto;
                            $groupMaster->created_id = $authId;
                            $groupMaster->modified_id = $authId;
                            $groupMaster->save();
    
                            if($groupMaster->group_id){
                                $groupId = $groupMaster->group_id;
                                \DB::table('lms_user_org_group')->insert([
                                    'group_id'=>$groupId,
                                    'org_id'=>$organizationId,
                                    'user_id'=>$userId,
                                    'date_created' => date('Y-m-d H:i:s'),
                                    'date_modified' => date('Y-m-d H:i:s'),
                                    'created_id' => $authId,
                                    'modified_id' => $authId,
                                ]);
                            } 
                        }
                        
                    }
                }

                $organization = Organization::where('org_id',$checkId)->where('is_active','!=','0');
                if($organization->count() > 0){
                    $organization = $organization->select('organization_name')->first();
                    $groupName = $organization->organization_name;
                    
                    $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','4')->where('org_id',$organizationId);
                    if($orgSettings->count() > 0){
                            
                        $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($group->count() > 0){
                            $groupId = $group->first()->group_id;
    
                            $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId);
                            if($groupAssign->count() > 0){
                                $groupAssign->update([
                                    'is_active' => 0,
                                    'date_modified' => date('Y-m-d H:i:s'),
                                    'modified_id' => $authId,
                                ]);
                            }
                        }
                    }
                }
            }
        }else{
            $organization = Organization::where('org_id',$organizationId)->where('is_active','!=','0');
            if($organization->count() > 0){
                $organization = $organization->select('organization_name')->first();
                $groupName = $organization->organization_name;
                
                $orgSettings = GroupOrganizationSetting::where('is_active','1')->where('group_setting_id','4')->where('org_id',$organizationId);
                if($orgSettings->count() > 0){
                        
                    $group = GroupOrganization::where('group_name','LIKE',$groupName)->where('is_auto',$isAuto)->where('org_id',$organizationId)->where('is_active','!=','0');
                    if($group->count() > 0){
                        $groupId = $group->first()->group_id;

                        $groupAssign = \DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId)->where('is_active','!=','0');
                        if($groupAssign->count() > 0){

                        }else{
                            \DB::table('lms_user_org_group')->insert([
                                'group_id'=>$groupId,
                                'org_id'=>$organizationId,
                                'user_id'=>$userId,
                                'date_created' => date('Y-m-d H:i:s'),
                                'date_modified' => date('Y-m-d H:i:s'),
                                'created_id' => $authId,
                                'modified_id' => $authId,
                            ]);
                        }
                    }else{

                        $groupMaster = new GroupOrganization;
                        $groupMaster->group_name = $groupName;
                        $groupMaster->group_code = groupCode($organizationId);
                        $groupMaster->org_id = $organizationId;
                        $groupMaster->is_active = 1;
                        $groupMaster->group_type = 5;
                        $groupMaster->is_auto = $isAuto;
                        $groupMaster->created_id = $authId;
                        $groupMaster->modified_id = $authId;
                        $groupMaster->save();

                        if($groupMaster->group_id){
                            $groupId = $groupMaster->group_id;
                            \DB::table('lms_user_org_group')->insert([
                                'group_id'=>$groupId,
                                'org_id'=>$organizationId,
                                'user_id'=>$userId,
                                'date_created' => date('Y-m-d H:i:s'),
                                'date_modified' => date('Y-m-d H:i:s'),
                                'created_id' => $authId,
                                'modified_id' => $authId,
                            ]);
                        } 
                    }
                    
                }
            }
        }
    }
}

function userArray($authId,$roleId,$organizationId){
    $userArray2 = [];
    $userArray = DB::table('lms_user_master')
    ->where('org_id',$organizationId)
    ->where('role_id','>',$roleId)
    ->pluck('created_id')
    ->toArray();
    if(!empty($userArray)){
        $userArray2 = DB::table('lms_user_master')
        ->whereIn('user_id',$userArray)
        ->where('org_id',$organizationId)
        ->where('role_id','>',$roleId)
        ->orWhere('created_id',$authId)
        ->pluck('user_id')
        ->toArray();
    }
    return $userArray2;
}

