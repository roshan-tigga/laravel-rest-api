<?php

namespace App\Http\Controllers\API;

use App\Models\OrganizationContentLibrary as OrgContentLibrary;
use App\Models\OrganizationContentType as OrgContentType;
use App\Models\ContentType;
use App\Models\OrganizationMedia as OrgMedia;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\OrganizationScoMenisfestReader as OrgScoMenisfestReader;
use App\Models\OrganizationScoDetails as OrgScoDetails;
use App\Models\OrganizationScoTrack as OrgScoTrack;
use DOMDocument;

class OrganizationMediaController extends BaseController
{
    public function getOrgMediaList(Request $request)
    {

        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;

        $sort = $request->has('sort') ? $request->get('sort') : 'content_library.content_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if ($sort == 'contentName') {
            $sortColumn = 'content_library.content_name';
        } elseif ($sort == 'contentVersion') {
            $sortColumn = 'content_library.content_version';
        } elseif ($sort == 'contentType') {
            $sortColumn = 'content_type.content_type';
        } elseif ($sort == 'mediaName') {
            $sortColumn = 'media.media_name';
        } elseif ($sort == 'parentContentName') {
            $sortColumn = 'parent_content_library.content_name';
        } elseif ($sort == 'organizationName') {
            //$sortColumn = 'org_master.organization_name';
        } elseif ($sort == 'isActive') {
            $sortColumn = 'content_library.is_active';
        }



        $medias = DB::table('lms_org_content_library as content_library')
            ->leftJoin('lms_org_master as org_master', 'content_library.org_id', '=', 'org_master.org_id')
            ->leftJoin('lms_org_media as media', 'content_library.media_id', '=', 'media.media_id')
            ->leftJoin('lms_org_content_types as content_type', 'content_library.content_types_id', '=', 'content_type.content_types_id')
            ->leftJoin('lms_org_content_library as parent_content_library', 'content_library.parent_content_id', '=', 'parent_content_library.content_id')
            ->where('content_library.is_active', '!=', '0')
            ->where('org_master.is_active', '1')
            ->where('media.is_active', '1')
            ->where(function ($query) use ($search) {
                if ($search != '') {
                    $query->where('content_library.content_name', 'LIKE', '%' . $search . '%');
                    $query->orWhere('content_library.content_version', 'LIKE', '%' . $search . '%');
                    $query->orWhere('content_type.content_type', 'LIKE', '%' . $search . '%');
                    $query->orWhere('media.media_name', 'LIKE', '%' . $search . '%');
                    //$query->orWhere('parent_content_library.content_name', 'LIKE', '%'.$search.'%');
                    //$query->orWhere('org_master.organization_name', 'LIKE', '%'.$search.'%');
                    if (in_array($search, ['active', 'act', 'acti', 'activ'])) {
                        $query->orWhere('content_library.is_active', '1');
                    }
                    if (in_array($search, ['inactive', 'inact', 'inacti', 'inactiv'])) {
                        $query->orWhere('content_library.is_active', '2');
                    }
                }
            })
            ->where(function ($query) use ($organizationId, $roleId, $authId) {
                if ($roleId == 1) {
                    $query->where('content_library.org_id', $organizationId);
                    $query->where('content_library.created_id', $authId);
                } else {
                    $query->where('content_library.org_id', $organizationId);
                }
            })
            ->orderBy($sortColumn, $order)
            ->select(
                'content_library.content_id as contentId',
                'content_library.content_name as contentName',
                'content_library.content_version as contentVersion',
                'content_type.content_type as contentType',
                'media.media_name as mediaName',
                'parent_content_library.content_name as parentContentName',
                'content_library.date_created as dateCreated',
                'content_library.date_modified as dateModified',
                DB::raw('(CASE WHEN content_library.is_active = 1 THEN "Active" ELSE "Inactive" END) AS isActive')
            )
            ->get();

        return response()->json(['status' => true, 'code' => 200, 'data' => $medias], 200);
    }

    public function addOrgMedia(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;

        if ($request->optionType == 1) {
            $validator = Validator::make($request->all(), [
                'optionType' => 'required|integer',
                'parentContent' => 'required|integer',
                'contentVersion' => 'required',
                'contentType' => 'required|integer',
                'mediaUrl' => 'required',
                'isActive' => 'integer'
            ]);
        } elseif ($request->optionType == 2) {
            $validator = Validator::make($request->all(), [
                'optionType' => 'required|integer',
                'parentContent' => 'required|integer',
                'contentType' => 'required|integer',
                'mediaUrl' => 'required',
                'isActive' => 'integer'
            ]);
        } elseif ($request->optionType == 3) {
            $validator = Validator::make($request->all(), [
                'optionType' => 'required|integer',
                'contentName' => 'required|max:64',
                'contentType' => 'required|integer',
                'mediaUrl' => 'required',
                'isActive' => 'integer'
            ]);
        } else {
            return response()->json(['status' => false, 'code' => 400, 'error' => 'The content type fields is required.'], 400);
        }


        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
        }

        $mediaUrl = $mediaSize = $mediaType = $mediaName = '';
        if ($request->file('mediaUrl') != '') {
            $mediaSize = $request->file('mediaUrl')->getSize();
            $mediaType = $request->file('mediaUrl')->extension();
            $mediaName = $request->file('mediaUrl')->getClientOriginalName();

            $mediaFileName = substr($mediaName, 0, strrpos($mediaName, '.'));
            $mediaFileName = str_replace(' ', '_', $mediaFileName);

            if($request->contentType == 3) {
                $zipFileName = time().Str::random(16);
                $zipFileNameWithExtension = $zipFileName.'.'.$mediaType;
                $mediaUrl = $zipFileName;
                $mediaName = $mediaFileName;
            } else {
                $mediaUrl = fileUploadS3Bucket($request->mediaUrl,'media');
            }
        } else {
            $mediaUrl = $request->mediaUrl;
            $mediaName = $mediaUrl;
            $mediaType = '';

            if ($request->contentType == 5) {
                $mediaType = 'Embedded Code';
            }
            if ($request->contentType == 8) {
                $mediaType = 'Link(URL)';
            }
        }


        if ($request->optionType == 1) {

            $content = OrgContentLibrary::where('is_active', '!=', '0')
                ->where(function ($query) use ($organizationId, $roleId, $authId) {
                    if ($roleId == 1) {
                        $query->where('org_id', $organizationId);
                        $query->where('created_id', $authId);
                    } else {
                        $query->where('org_id', $organizationId);
                    }
                })
                ->where('content_id', $request->parentContent)
                ->where('content_version', $request->contentVersion);
            if ($content->count() > 0) {
                $mediaId = $content->first()->media_id;
                $content->update([
                    'content_types_id' => $request->contentType
                ]);
            } else {
                $parentContent = OrgContentLibrary::where('is_active', '!=', '0')
                    ->where(function ($query) use ($organizationId, $roleId, $authId) {
                        if ($roleId == 1) {
                            $query->where('org_id', $organizationId);
                            $query->where('created_id', $authId);
                        } else {
                            $query->where('org_id', $organizationId);
                        }
                    })
                    ->where('parent_content_id', $request->parentContent)->where('content_version', $request->contentVersion);
                if ($parentContent->count() > 0) {
                    $mediaId = $parentContent->first()->media_id;
                    $parentContent->update([
                        'content_types_id' => $request->contentType
                    ]);
                } else {
                    return response()->json(['status' => false, 'code' => 400, 'message' => 'Media has been not updated.'], 400);
                }
            }

            $media = OrgMedia::find($mediaId);
            $media->media_name = $mediaName;
            $media->media_url = $mediaUrl;
            $media->media_size = $mediaSize;
            $media->media_type = $mediaType;
            $media->modified_id = $authId;
            $media->save();

            return response()->json(['status' => true, 'code' => 200, 'message' => 'Media has been updated successfully.'], 200);

        } elseif ($request->optionType == 2) {

            $media = new OrgMedia;
            $media->media_name = $mediaName;
            $media->media_url = $mediaUrl;
            $media->media_size = $mediaSize;
            $media->media_type = $mediaType;
            $media->org_id = $organizationId;
            $media->created_id = $authId;
            $media->modified_id = $authId;
            $media->save();

            if ($media->media_id != '') {

                if($request->file('mediaUrl') && $request->contentType == 3){
                    fileUploadS3Bucket($request->file('mediaUrl'),'media','s3',$request,$zipFileName);
                    $zip = new \ZipArchive();
                    if ($zip->open(Storage::disk('public')->path('media/'.$zipFileNameWithExtension), \ZipArchive::CREATE) === TRUE) {
                        //$zip->extractTo(Storage::disk('public')->path('media/'.$zipFileName));

                        $stream = $zip->getStream('imsmanifest.xml');
                        $contents = '';
                        while (!feof($stream)) {
                            $contents .= fread($stream, 2);
                        }
                        fclose($stream);
                        $dom = new \DOMDocument();

                        if($dom->loadXML($contents)) {

                            $manifest = $dom->getElementsByTagName('manifest')->item(0);
                            $version = @$manifest->attributes->getNamedItem('version')->nodeValue;
                            $manifestIdentifier = @$manifest->attributes->getNamedItem('identifier')->nodeValue;

                            $organization = $dom->getElementsByTagName('organization')->item(0);
                            $title = @$organization->getElementsByTagName('title')->item(0)->textContent;

                            $resource = $dom->getElementsByTagName('resource')->item(0);
                            $identifier = @$resource->attributes->getNamedItem('identifier')->nodeValue;
                            $scormType = @$resource->attributes->getNamedItem('scormType')->nodeValue;


                            $scoMenisfestReader = new OrgScoMenisfestReader;
                            $scoMenisfestReader->course_id = '';
                            $scoMenisfestReader->media_id = $media->media_id;
                            $scoMenisfestReader->name = $title;
                            $scoMenisfestReader->scormtype = $scormType;
                            $scoMenisfestReader->reference = $identifier;
                            $scoMenisfestReader->version = $version;
                            $scoMenisfestReader->created_id = $authId;
                            $scoMenisfestReader->modified_id = $authId;
                            $scoMenisfestReader->save();

                            

                            $items = $dom->getElementsByTagName('item');
                            $resources = $dom->getElementsByTagName('resource');
                            if ($items->length > 0) {
                                foreach ($items as $item) {

                                    $identifierref = @$item->attributes->getNamedItem('identifierref')->nodeValue;
                                    $title = @$item->getElementsByTagName('title')->item(0)->textContent;

                                    if ($resources->length > 0) {
                                        foreach ($resources as $k => $resource) {
                                            $identifier = @$resource->attributes->getNamedItem('identifier')->nodeValue;
                                            $scormType = @$resource->attributes->getNamedItem('scormType')->nodeValue;
                                            $launch = @$resource->attributes->getNamedItem('href')->nodeValue;

                                            if ($identifierref == $identifier) {
                                                $scoDetails = new OrgScoDetails;
                                                $scoDetails->scorm_id = $scoMenisfestReader->id;
                                                $scoDetails->manifest = $manifestIdentifier;
                                                $scoDetails->identifier = $identifier;
                                                $scoDetails->launch = $launch;
                                                $scoDetails->scormtype = $scormType;
                                                $scoDetails->title = $title;
                                                $scoDetails->organization_id = $organizationId;
                                                //$scoDetails->parent_organization_id = '';
                                                $scoDetails->sortorder = $k;
                                                $scoDetails->created_id = $authId;
                                                $scoDetails->modified_id = $authId;
                                                $scoDetails->save();
                                            }

                                        }
                                    }
                                }
                            }
                        }
                        $zip->close();

                        // $files = \File::allFiles(Storage::disk('public')->path('/media/'.$zipFileName));
                        // foreach ($files as $k => $file) {
                        //     $dirname = pathinfo($file)['dirname'];
                        //     $basename = pathinfo($file)['basename'];
                        //     $explode = explode($zipFileName, $dirname);
                        //     scormFileUpload(file_get_contents($dirname . '/' . $basename),'/media/' . $zipFileName . '/' . $mediaFileName . $explode[1] . '/' . $basename);
                        // }

                        \File::deleteDirectory(Storage::disk('public')->path('media/'.$zipFileName));
                        \File::delete(Storage::disk('public')->path('media/'.$zipFileNameWithExtension));
                    }
                }

                $content = OrgContentLibrary::where('is_active', '!=', '0')
                    ->where(function ($query) use ($organizationId, $roleId, $authId) {
                        if ($roleId == 1) {
                            $query->where('org_id', $organizationId);
                            $query->where('created_id', $authId);
                        } else {
                            $query->where('org_id', $organizationId);
                        }
                    })
                    ->where('content_id', $request->parentContent);
                if ($content->count() > 0) {

                    $contentName = $content->first()->content_name;

                    $parentContent = OrgContentLibrary::where('is_active', '!=', '0')
                        ->where(function ($query) use ($organizationId, $roleId, $authId) {
                            if ($roleId == 1) {
                                $query->where('org_id', $organizationId);
                                $query->where('created_id', $authId);
                            } else {
                                $query->where('org_id', $organizationId);
                            }
                        })
                        ->where('parent_content_id', $request->parentContent)->orderBy('content_id', 'DESC');
                    if ($parentContent->count() > 0) {

                        $number = $parentContent->first()->content_version;
                        $pre_number = strtok($number, ".");
                        $post_number = substr($number, strrpos($number, '.') + 1);
                        if ($post_number != '') {
                            $contentVersion = $pre_number . "." . $post_number + 1;
                        } else {
                            $contentVersion = $number + 0.1;
                        }
                        $contentTypesId = $parentContent->select('content_types_id')->first()->content_types_id;
                    } else {
                        $contentVersion = $content->max('content_version') + 0.1;
                        $contentTypesId = $content->select('content_types_id')->first()->content_types_id;
                    }

                    $contentLibrary = new OrgContentLibrary;
                    $contentLibrary->content_name = $contentName;
                    $contentLibrary->parent_content_id = $request->parentContent;
                    $contentLibrary->content_version = $contentVersion;
                    $contentLibrary->content_types_id = $request->contentType;
                    $contentLibrary->media_id = $media->media_id;
                    $contentLibrary->org_id = $organizationId;
                    $contentLibrary->is_active = $request->isActive == '' ? '1' : $request->isActive;
                    $contentLibrary->created_id = $authId;
                    $contentLibrary->modified_id = $authId;
                    $contentLibrary->save();

                    return response()->json(['status' => true, 'code' => 200, 'message' => 'Media has been created successfully.'], 200);

                } else {
                    return response()->json(['status' => false, 'code' => 400, 'message' => 'Media has been not created.'], 400);
                }
            }
        } elseif ($request->optionType == 3) {
            $media = new OrgMedia;
            $media->media_name = $mediaName;
            $media->media_url = $mediaUrl;
            $media->media_size = $mediaSize;
            $media->media_type = $mediaType;
            $media->org_id = $organizationId;
            $media->created_id = $authId;
            $media->modified_id = $authId;
            $media->save();

            if ($media->media_id != '') {

                if($request->file('mediaUrl') && $request->contentType == 3){
                    fileUploadS3Bucket($request->file('mediaUrl'),'media','s3',$request,$zipFileName);
                    $zip = new \ZipArchive();
                    if ($zip->open(Storage::disk('public')->path('media/'.$zipFileNameWithExtension), \ZipArchive::CREATE) === TRUE) {
                        //$zip->extractTo(Storage::disk('public')->path('media/'.$zipFileName));

                        $stream = $zip->getStream('imsmanifest.xml');
                        $contents = '';
                        while (!feof($stream)) {
                            $contents .= fread($stream, 2);
                        }
                        fclose($stream);
                        $dom = new \DOMDocument();

                        if($dom->loadXML($contents)) {

                            $manifest = $dom->getElementsByTagName('manifest')->item(0);
                            $version = @$manifest->attributes->getNamedItem('version')->nodeValue;
                            $manifestIdentifier = @$manifest->attributes->getNamedItem('identifier')->nodeValue;

                            $organization = $dom->getElementsByTagName('organization')->item(0);
                            $title = @$organization->getElementsByTagName('title')->item(0)->textContent;

                            $resource = $dom->getElementsByTagName('resource')->item(0);
                            $identifier = @$resource->attributes->getNamedItem('identifier')->nodeValue;
                            $scormType = @$resource->attributes->getNamedItem('scormType')->nodeValue;


                            $scoMenisfestReader = new OrgScoMenisfestReader;
                            $scoMenisfestReader->course_id = '';
                            $scoMenisfestReader->media_id = $media->media_id;
                            $scoMenisfestReader->name = $title;
                            $scoMenisfestReader->scormtype = $scormType;
                            $scoMenisfestReader->reference = $identifier;
                            $scoMenisfestReader->version = $version;
                            $scoMenisfestReader->created_id = $authId;
                            $scoMenisfestReader->modified_id = $authId;
                            $scoMenisfestReader->save();

                            

                            $items = $dom->getElementsByTagName('item');
                            $resources = $dom->getElementsByTagName('resource');
                            if ($items->length > 0) {
                                foreach ($items as $item) {

                                    $identifierref = @$item->attributes->getNamedItem('identifierref')->nodeValue;
                                    $title = @$item->getElementsByTagName('title')->item(0)->textContent;

                                    if ($resources->length > 0) {
                                        foreach ($resources as $k => $resource) {
                                            $identifier = @$resource->attributes->getNamedItem('identifier')->nodeValue;
                                            $scormType = @$resource->attributes->getNamedItem('scormType')->nodeValue;
                                            $launch = @$resource->attributes->getNamedItem('href')->nodeValue;

                                            if ($identifierref == $identifier) {
                                                $scoDetails = new OrgScoDetails;
                                                $scoDetails->scorm_id = $scoMenisfestReader->id;
                                                $scoDetails->manifest = $manifestIdentifier;
                                                $scoDetails->identifier = $identifier;
                                                $scoDetails->launch = $launch;
                                                $scoDetails->scormtype = $scormType;
                                                $scoDetails->title = $title;
                                                $scoDetails->organization_id = $organizationId;
                                                //$scoDetails->parent_organization_id = '';
                                                $scoDetails->sortorder = $k;
                                                $scoDetails->created_id = $authId;
                                                $scoDetails->modified_id = $authId;
                                                $scoDetails->save();
                                            }

                                        }
                                    }
                                }
                            }
                        }
                        $zip->close();

                        // $files = \File::allFiles(Storage::disk('public')->path('/media/'.$zipFileName));
                        // foreach ($files as $k => $file) {
                        //     $dirname = pathinfo($file)['dirname'];
                        //     $basename = pathinfo($file)['basename'];
                        //     $explode = explode($zipFileName, $dirname);
                        //     scormFileUpload(file_get_contents($dirname . '/' . $basename),'/media/' . $zipFileName . '/' . $mediaFileName . $explode[1] . '/' . $basename);
                        // }

                        \File::deleteDirectory(Storage::disk('public')->path('media/'.$zipFileName));
                        \File::delete(Storage::disk('public')->path('media/'.$zipFileNameWithExtension));
                    }
                }

                $contentVersion = "1.0";

                $contentLibrary = new OrgContentLibrary;
                $contentLibrary->content_name = $request->contentName;
                $contentLibrary->content_version = $contentVersion;
                $contentLibrary->content_types_id = $request->contentType;
                $contentLibrary->media_id = $media->media_id;
                $contentLibrary->org_id = $organizationId;
                $contentLibrary->is_active = $request->isActive == '' ? '1' : $request->isActive;
                $contentLibrary->created_id = $authId;
                $contentLibrary->modified_id = $authId;
                $contentLibrary->save();
            }

            return response()->json(['status' => true, 'code' => 200, 'message' => 'Media has been created successfully.'], 200);
        } else {
            return response()->json(['status' => false, 'code' => 400, 'message' => 'Media has been created successfully.'], 400);
        }

    }

    public function getOrgMediaById($contentId)
    {

        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;

        $contentLibrary = DB::table('lms_org_content_library as content_library')
            ->leftJoin('lms_org_master as org_master', 'content_library.org_id', '=', 'org_master.org_id')
            ->leftJoin('lms_org_media as media', 'content_library.media_id', '=', 'media.media_id')
            ->leftJoin('lms_org_content_types as content_type', 'content_library.content_types_id', '=', 'content_type.content_types_id')
            ->leftJoin('lms_org_content_library as parent_content_library', 'content_library.parent_content_id', '=', 'parent_content_library.content_id')
            ->leftJoin('lms_org_sco_menisfest_reader as scorm', 'media.media_id', '=', 'scorm.media_id')
            ->leftJoin('lms_org_sco_details as scormDetails', 'scorm.id', '=', 'scormDetails.scorm_id')
            ->groupBy('scormDetails.scorm_id')
            ->where('org_master.is_active', '1')
            ->where('media.is_active', '1')
            ->where('content_library.is_active', '!=', '0')
            ->where(['content_library.content_id' => $contentId])
            ->where(function ($query) use ($organizationId, $roleId, $authId) {
                if ($roleId == 1) {
                    $query->where('content_library.org_id', $organizationId);
                    $query->where('content_library.created_id', $authId);

                    $query->where('media.org_id', $organizationId);
                    $query->where('media.created_id', $authId);
                } else {
                    $query->where('content_library.org_id', $organizationId);
                    $query->where('media.org_id', $organizationId);
                }
            });

        if ($contentLibrary->count() < 1) {
            return response()->json(['status' => false, 'code' => 404, 'error' => 'Media is not found.'], 404);
        }
        $contentLibrary = $contentLibrary->select('content_library.content_id as contentId', 'content_library.content_name as contentName', 'content_library.content_version as contentVersion', 'content_library.content_types_id as contentTypeId', 'content_type.content_type as contentType', 'content_library.media_id as mediaId', 'media.media_name as mediaName', 'media.media_url as mediaUrl', 'content_library.parent_content_id as parentContentId', 'parent_content_library.content_name as parentContentName','scorm.id as scormId','scorm.version as scormVersion', 'scormDetails.launch', 'content_library.is_active as isActive')->first();

        if ($contentLibrary->mediaUrl != '') {
            if ($contentLibrary->contentTypeId == 3) {

                $mediaName = $contentLibrary->mediaName;
                $mediaUrl = $contentLibrary->mediaUrl;

                if ($contentLibrary->launch) {
                    $contentLibrary->mediaUrl = getFileS3Bucket(getPathS3Bucket()) . '/media/' . $mediaUrl . '/' . $mediaName . '/' . $contentLibrary->launch;
                } 
            } else if ($contentLibrary->contentTypeId == 5 || $contentLibrary->contentTypeId == 8) {
                $contentLibrary->mediaUrl = $contentLibrary->mediaUrl;
            } else {
                $contentLibrary->mediaUrl = getFileS3Bucket(getPathS3Bucket() . '/media/' . $contentLibrary->mediaUrl);
            }

        }

        return response()->json(['status' => true, 'code' => 200, 'data' => $contentLibrary], 200);

    }


    public function deleteOrgMedia(Request $request)
    {
        try {
            $authId = Auth::user()->user_id;
            $organizationId = Auth::user()->org_id;
            $roleId = Auth::user()->user->role_id;

            $contentLibrary = ContentLibrary::where('is_active', '!=', '0')
                ->where(function ($query) use ($organizationId, $roleId, $authId) {
                    if ($roleId == 1) {
                        $query->where('org_id', $organizationId);
                        $query->where('created_id', $authId);
                    } else {
                        $query->where('org_id', $organizationId);
                    }
                })
                ->where('content_id', $request->contentId);
            if ($contentLibrary->count() < 1) {
                return response()->json(['status' => false, 'code' => 404, 'error' => 'Media is not found.'], 404);
            }
            $contentLibrary->update([
                'is_active' => 0
            ]);
            return response()->json(['status' => true, 'code' => 200, 'message' => 'Media has been deleted successfully.'], 200);
        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'code' => 501, 'message' => $e->getMessage()], 501);
        }
    }

    public function getOrgParentContentList()
    {

        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;

        $contents = OrgContentLibrary::where('is_active', '!=', '0')
            ->whereNull('parent_content_id')
            ->where(function ($query) use ($organizationId, $roleId, $authId) {
                if ($roleId == 1) {
                    $query->where('org_id', $organizationId);
                    $query->where('created_id', $authId);
                } else {
                    $query->where('org_id', $organizationId);
                }
            })
            ->select('content_id as contentId', 'content_name as contentName')
            ->get();
        return response()->json(['status' => true, 'code' => 200, 'data' => $contents], 200);
    }

    public function getOrgContentVersion(Request $request)
    {

        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;

        $contentId = $request->contentId;
        $optionType = $request->optionType;
        $version = $allVersion = [];

        $contentTypesId = '';
        $contentTypesName = '';
        $mediaName = '';

        if ($optionType == 1) {
            $contentVersion = '';
            $content = OrgContentLibrary::join('lms_org_content_types', 'lms_org_content_library.content_types_id', '=', 'lms_org_content_types.content_types_id')
                ->join('lms_org_media', 'lms_org_content_library.media_id', '=', 'lms_org_media.media_id')
                ->where(function ($query) use ($organizationId, $roleId, $authId) {
                    if ($roleId == 1) {
                        $query->where('lms_org_content_library.org_id', $organizationId);
                        $query->where('lms_org_content_library.created_id', $authId);

                        $query->where('lms_org_media.org_id', $organizationId);
                        $query->where('lms_org_media.created_id', $authId);
                    } else {
                        $query->where('lms_org_content_library.org_id', $organizationId);
                        $query->where('lms_org_media.org_id', $organizationId);
                    }
                })
                ->where('lms_org_content_library.is_active', '!=', '0')->where('lms_org_content_library.content_id', $contentId);
            if ($content->count() > 0) {
                $content = $content->first();
                $allVersion[] = $content->content_version;
                $contentTypesId = $content->content_types_id;
                $contentTypesName = $content->content_type;
                $mediaName = $content->media_name;

                $parentContent = OrgContentLibrary::where('is_active', '!=', '0')
                    ->where(function ($query) use ($organizationId, $roleId, $authId) {
                        if ($roleId == 1) {
                            $query->where('org_id', $organizationId);
                            $query->where('created_id', $authId);
                        } else {
                            $query->where('org_id', $organizationId);
                        }
                    })
                    ->where('parent_content_id', $contentId);
                if ($parentContent->count() > 0) {
                    $contentVersions = $parentContent->select('content_version')->get();
                    foreach ($contentVersions as $contentVersion) {
                        $version = $contentVersion->content_version;
                        $allVersion[] = $version;
                    }
                }
            }
            $data = [
                'version' => $allVersion,
                'contentTypesId' => $contentTypesId,
                'contentTypesName' => $contentTypesName,
                'mediaName' => $mediaName,
            ];
            return response()->json(['status' => true, 'code' => 200, 'data' => $data], 200);
        } elseif ($optionType == 2) {
            $contentVersion = '';
            $content = OrgContentLibrary::join('lms_org_content_types', 'lms_org_content_library.content_types_id', '=', 'lms_org_content_types.content_types_id')
                ->where('lms_org_content_library.is_active', '!=', '0')
                ->where(function ($query) use ($organizationId, $roleId, $authId) {
                    if ($roleId == 1) {
                        $query->where('lms_org_content_library.org_id', $organizationId);
                        $query->where('lms_org_content_library.created_id', $authId);
                    } else {
                        $query->where('lms_org_content_library.org_id', $organizationId);
                    }
                })
                ->where('lms_org_content_library.content_id', $contentId);
            if ($content->count() > 0) {

                $content = $content->first();
                $contentTypesId = $content->content_types_id;
                $contentTypesName = $content->content_type;

                $subContent = OrgContentLibrary::where('is_active', '!=', '0')
                    ->where(function ($query) use ($organizationId, $roleId, $authId) {
                        if ($roleId == 1) {
                            $query->where('org_id', $organizationId);
                            $query->where('created_id', $authId);
                        } else {
                            $query->where('org_id', $organizationId);
                        }
                    })
                    ->where('parent_content_id', $contentId)->orderBy('content_id', 'DESC');
                if ($subContent->count() > 0) {
                    $number = $subContent->first()->content_version;
                    $pre_number = strtok($number, ".");
                    $post_number = substr($number, strrpos($number, '.') + 1);
                    if ($post_number != '') {
                        $contentVersion = $pre_number . "." . $post_number + 1;
                    } else {
                        $contentVersion = $number + 0.1;
                    }
                } else {
                    $contentVersion = $content->first()->content_version + 0.1;
                }
            }
            return response()->json([
                'status' => true,
                'code' => 200,
                'data' => [
                    'version' => $contentVersion,
                    'contentTypesId' => $contentTypesId,
                    'contentTypesName' => $contentTypesName,
                ]
            ], 200);

        } elseif ($optionType == 3) {
            $contentVersion = "1.0";
            // $content = ContentLibrary::where('is_active','!=','0')->whereNull('parent_content_id');
            // if($content->count() > 0){
            //     $contentVersion = $content->max('content_version') + 1.0;
            // }
            return response()->json(['status' => true, 'code' => 200, 'data' => $contentVersion], 200);
        } else {
            return response()->json(['status' => false, 'code' => 400, 'message' => 'Content version not found.'], 400);
        }
    }


    public function getOrgMediaOptionList(Request $request)
    {

        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;

        $medias = OrgMedia::where('is_active', '1')
            ->where(function ($query) use ($organizationId, $roleId, $authId) {
                // if ($roleId == 1) {
                //     $query->where('org_id', $organizationId);
                //     $query->where('created_id', $authId);
                // } else {
                //     $query->where('org_id', $organizationId);
                // }
                $query->where('org_id', $organizationId);
            })
            ->select('media_id as mediaId', 'media_name as mediaName', 'media_type as mediaType', 'media_size as mediaSize', 'date_created as dateCreated', 'is_active as isActive')
            ->get();
        return response()->json(['status' => true, 'code' => 200, 'data' => $medias], 200);
    }

    public function deleteOrgMediaCourseLibrary(Request $request)
    {
        try {
            $media = OrgMedia::where('is_active', '!=', '0')->where('media_id', $request->mediaId);
            if ($media->count() < 1) {
                return response()->json(['status' => false, 'code' => 404, 'error' => 'Media is not found.'], 404);
            }
            $media->update([
                'is_active' => 0
            ]);
            return response()->json(['status' => true, 'code' => 200, 'message' => 'Media has been deleted successfully.'], 200);
        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'code' => 501, 'message' => $e->getMessage()], 501);
        }
    }

    public function getOrgMediaCourseLibraryById($mediaId)
    {

        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;

        $media = OrgMedia::where('is_active', '!=', '0')->where('media_id', $mediaId);
        if ($media->count() < 1) {
            return response()->json(['status' => false, 'code' => 404, 'error' => 'Media is not found.'], 404);
        }

        $media = $media->select('media_id as mediaId', 'media_name as mediaName', 'media_type as mediaType', 'media_size as mediaSize', 'date_created as dateCreated', 'is_active as isActive')
            ->first();
        return response()->json(['status' => true, 'code' => 200, 'data' => $media], 200);
    }

}