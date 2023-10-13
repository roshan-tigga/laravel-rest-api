<?php

namespace App\Http\Controllers\API;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;

class AnnouncementController extends BaseController 
{
    public function getAnnouncementList(Request $request){

        $sort = $request->has('sort') ? $request->get('sort') : 'announcement_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if($sort == 'announcementTitle'){
            $sortColumn = 'announcement_title';
        }elseif($sort == 'whereToShow'){
            $sortColumn = 'where_to_show';
        }elseif($sort == 'fromDate'){
            $sortColumn = 'from_date';
        }elseif($sort == 'toDate'){
            $sortColumn = 'to_date';
        }elseif($sort == 'isActive'){
            $sortColumn = 'is_active';
        }
        $announcements = Announcement::where('is_active','!=','0')
        ->where(function($query) use ($search){
            if($search != ''){
                $query->where('announcement_title', 'LIKE', '%'.$search.'%');
                $query->orWhere('where_to_show', 'LIKE', '%'.$search.'%');
                $query->orWhere('from_date', 'LIKE', '%'.$search.'%');
                $query->orWhere('to_date', 'LIKE', '%'.$search.'%');
                if(in_array($search,['active','act','acti','activ'])){
                    $query->orWhere('is_active','1');
                }
                if(in_array($search,['inactive','inact','inacti','inactiv'])){
                    $query->orWhere('is_active','2');
                }
            }
        })
        ->select('announcement_id as announcementId', 'announcement_title as announcementTitle', 'where_to_show as whereToShow',
        \DB::raw('CONCAT(DATE_FORMAT(from_date, "%d-%m-%Y %H:%i")," ",from_time)  as fromDate'), \DB::raw('CONCAT(DATE_FORMAT(to_date, "%d-%m-%Y %H:%i")," ",to_time)  as toDate'), 'is_active as isActive')
        ->orderBy($sortColumn,$order)
        ->get();
        foreach($announcements as $announcement){

            if($announcement->whereToShow == '0'){
                $announcement->whereToShow = 'Login';
            }
            if($announcement->whereToShow == '1'){
                $announcement->whereToShow = 'Header Strip';
            }
            if($announcement->whereToShow == '2'){
                $announcement->whereToShow = 'As Notification';
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$announcements],200);
    }

    public function addNewAnnouncement(Request $request){
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'announcementTitle' => 'required|max:250',
            'whereToShow' => 'nullable|integer',
            'fromDate' => 'nullable',
            //'fromTime' => 'nullable',
            'toDate' => 'nullable',
            //'toTime' => 'nullable',
            'announcementDescription' => 'nullable',
            'isActive' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{

            $fromDate = $fromTime = '';
            if($request->fromDate != ''){
                $fromDate = date('Y-m-d H:i:s',strtotime(substr($request->fromDate, 0, -2)));
                $fromTime = substr($request->fromDate, -2, 2);
            }

            $toDate = $toTime = '';
            if($request->toDate != ''){
                $toDate = date('Y-m-d H:i:s',strtotime(substr($request->toDate, 0, -2)));
                $toTime = substr($request->toDate, -2, 2);
            }


            $announcement = new Announcement;
            $announcement->announcement_title = $request->announcementTitle;
            $announcement->announcement_description = $request->announcementDescription;
            $announcement->where_to_show = $request->whereToShow;
            $announcement->from_date = $fromDate;
            $announcement->from_time = $fromTime;
            $announcement->to_date = $toDate;
            $announcement->to_time = $toTime;
            $announcement->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $announcement->created_id = $authId;
            $announcement->modified_id = $authId;
            $announcement->save();

            return response()->json(['status'=>true,'code'=>200,'message'=>'Announcement has been created successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function getAnnouncementById($announcementId){ 
        $announcementRedis = Redis::get('announcementRedis' . $announcementId);
        if(isset($announcementRedis)){
            $announcementRedis = json_decode($announcementRedis,false);
            
            if($announcementRedis->whereToShow == '0'){
                $announcementRedis->whereToShow = 'Login';
            }
            if($announcementRedis->whereToShow == '1'){
                $announcementRedis->whereToShow = 'Header Strip';
            }
            if($announcementRedis->whereToShow == '2'){
                $announcementRedis->whereToShow = 'As Notification';
            }
            return response()->json(['status'=>true,'code'=>200,'data'=>$announcementRedis],200);
        }else{
            $announcement = Announcement::where('is_active','!=','0')->where('announcement_id',$announcementId);
            if ($announcement->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Announcement is not found.'], 404);
            }
            $announcement =  $announcement->select('announcement_id as announcementId', 'announcement_title as announcementTitle',  'where_to_show as whereToShow',
            \DB::raw('CONCAT(DATE_FORMAT(from_date, "%d-%m-%Y %H:%i")," ",from_time)  as fromDate'), \DB::raw('CONCAT(DATE_FORMAT(to_date, "%d-%m-%Y %H:%i")," ",to_time)  as toDate'), 'announcement_description as announcementDescription', 'is_active as isActive')->first();
            Redis::set('announcementRedis' . $announcementId, $announcement);

            if($announcement->whereToShow == '0'){
                $announcement->whereToShow = 'Login';
            }
            if($announcement->whereToShow == '1'){
                $announcement->whereToShow = 'Header Strip';
            }
            if($announcement->whereToShow == '2'){
                $announcement->whereToShow = 'As Notification';
            }
            return response()->json(['status'=>true,'code'=>200,'data'=>$announcement],200);
        }
    }   

    public function updateAnnouncementById(Request $request){
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'announcementId'=>'required|integer', 
            'announcementTitle' => 'required|max:250',
            'whereToShow' => 'nullable|integer',
            'fromDate' => 'nullable',
            //'fromTime' => 'nullable',
            'toDate' => 'nullable',
            //'toTime' => 'nullable',
            'announcementDescription' => 'nullable',
            'isActive' => 'nullable|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
        
            $announcement = Announcement::where('is_active','!=','0')->where('announcement_id',$request->announcementId);
            if ($announcement->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Announcement is not found.'], 404);
            }


            $fromDate = $fromTime = '';
            if($request->fromDate != ''){
                $fromDate = date('Y-m-d H:i:s',strtotime(substr($request->fromDate, 0, -2)));
                $fromTime = substr($request->fromDate, -2, 2);
            }

            $toDate = $toTime = '';
            if($request->toDate != ''){
                $toDate = date('Y-m-d H:i:s',strtotime(substr($request->toDate, 0, -2)));
                $toTime = substr($request->toDate, -2, 2);
            }

            
            $announcement->update([
                'announcement_title' => $request->announcementTitle,
                'announcement_description' => $request->announcementDescription,
                'where_to_show' => $request->whereToShow,
                'from_date' => $fromDate,
                'from_time' => $fromTime,
                'to_date' => $toDate,
                'to_time' => $toTime,
                'is_active' => $request->isActive == '' ? $announcement->first()->is_active ? $announcement->first()->is_active : '1' : $request->isActive,
                'modified_id' => $authId
            ]);

            $announcement =  $announcement->select('announcement_id as announcementId', 'announcement_title as announcementTitle',  'where_to_show as whereToShow', 
            \DB::raw('CONCAT(DATE_FORMAT(from_date, "%d-%m-%Y %H:%i")," ",from_time)  as fromDate'), \DB::raw('CONCAT(DATE_FORMAT(to_date, "%d-%m-%Y %H:%i")," ",to_time)  as toDate'), 'announcement_description as announcementDescription', 'is_active as isActive')->first();
            Redis::del('announcementRedis' . $request->announcementId);
            Redis::set('announcementRedis' . $request->announcementId, json_encode($announcement,false));

            return response()->json(['status'=>true,'code'=>200,'message'=>'Announcement has been updated successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    

    public function deleteAnnouncement(Request $request){
        $validator = Validator::make($request->all(), [
            'announcementId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
        
            $announcement = Announcement::where('is_active','!=','0')->where('announcement_id',$request->announcementId);
            if ($announcement->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Announcement is not found.'], 404);
            }
            
            $announcement->update([
                'is_active' => '0'
            ]);

            Redis::del('announcementRedis' . $request->announcementId);
            return response()->json(['status'=>true,'code'=>200,'message'=>'Announcement has been deleted successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function getAnnouncementNotificationList(Request $request){

        $nowDate = date('Y-m-d');
        $nowTime = date('H:i');

        $announcements = Announcement::where('is_active','1')->whereDate('from_date','<=',$nowDate)->whereDate('to_date','>=',$nowDate)
        ->whereTime('from_time','<=',$nowTime)->whereTime('to_time','>=',$nowTime)
        ->where('where_to_show',$request->whereToShow)
        ->select('announcement_title as announcementTitle','announcement_description as announcementDescription')
        ->get();

        if($announcements->count() > 0){
            return response()->json(['status'=>true,'code'=>200,'data'=>$announcements],200);
        }else{
            return response()->json(['status'=>true,'code'=>400,'error'=>'Announcement not found.'],400);
        }
    }

}
