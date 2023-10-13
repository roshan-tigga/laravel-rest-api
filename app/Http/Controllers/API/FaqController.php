<?php

namespace App\Http\Controllers\API;

use App\Models\Faq;
use App\Models\CategoryMaster;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;


class FaqController extends BaseController
{
    public function getFaqList(Request $request){
        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        if($roleId == 1){
            $faqs = Faq::where('is_active','!=','0')
            ->orderBy('order','ASC')
            ->select('faq_id as faqId','faq_title as faqTitle','faq_description as faqDescription','category','is_publish as isPublish','is_active as isActive')
            ->get();
            if($faqs->count() > 0 ){
                foreach($faqs as $faq){
                    if($faq->category){
                        $faq->category = CategoryMaster::where('is_active','1')->whereIn('category_id',explode(',',$faq->category))->pluck('category_name');
                    }
                }
            }
        }else{
            $faqs = Faq::where('is_active','!=','0')
            ->orderBy('order','ASC')
            ->select('faq_id as faqId','faq_title as faqTitle','category','is_active as isActive')
            ->where('is_publish','1')
            ->get();
            if($faqs->count() > 0 ){
                foreach($faqs as $faq){
                    if($faq->category){
                        $faq->category = CategoryMaster::where('is_active','1')->whereIn('category_id',explode(',',$faq->category))->pluck('category_name');
                    }
                }
            }
        }
        
        return response()->json(['status'=>true,'code'=>200,'data'=>$faqs],200);
    }

    public function getOrgFaqList(Request $request){
        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        $faqs = Faq::where('is_active','!=','0')
        ->orderBy('order','ASC')
        ->select('faq_id as faqId','faq_title as faqTitle','category','is_active as isActive')
        ->where('is_publish','1')
        ->get();
        if($faqs->count() > 0 ){
            foreach($faqs as $faq){
                if($faq->category){
                    $faq->category = CategoryMaster::where('is_active','1')->whereIn('category_id',explode(',',$faq->category))->pluck('category_name');
                }
            }
        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$faqs],200);
    }

    public function addFaq(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'faqTitle' => 'required',
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $categoryId = '';
        if(!empty($request->category)){
            $explodeCategory = explode(',',$request->category);
            $categoryId = implode(',',$explodeCategory);
        }

        $userDocument = new Faq;
        $userDocument->faq_title = $request->faqTitle; 
        $userDocument->category = $categoryId; 
        $userDocument->faq_description = $request->faqDescription; 
        $userDocument->role_id = $request->roleId; 
        $userDocument->is_publish = $request->isPublish ? $request->isPublish : 0; 
        $userDocument->created_id = $authId;
        $userDocument->modified_id = $authId;
        $userDocument->save(); 

        return response()->json(['status'=>true,'code'=>201,'message'=>'Faq has been created successfully.'],201);
    }

    public function getFaqById($id){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $userDocument = Faq::where('is_active','!=','0')
        ->select('faq_id as faqId','faq_title as faqTitle','faq_description as faqDescription','role_id as roleId','category','is_publish as isPublish','order','is_active as isActive')
        ->find($id);
        if(is_null($userDocument)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Faq not found.'],400);
        }

        if($userDocument->count() > 0 ){
            if($userDocument->category){
                $userDocument->category = CategoryMaster::where('is_active','1')->whereIn('category_id',explode(',',$userDocument->category))->select('category_id as categoryId','category_name as categoryName')->get();
            }
        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$userDocument],200);
    }

    public function updateFaqById(Request $request,$id){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'faqTitle' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $userDocument = Faq::where('is_active','!=','0')->find($id);
        if(is_null($userDocument)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Faq not found.'],400);
        }
        $categoryId = '';
        if(!empty($request->category)){
            $explodeCategory = explode(',',$request->category);
            $categoryId = implode(',',$explodeCategory);
        }

        $userDocument->faq_title = $request->faqTitle; 
        $userDocument->category = $categoryId; 
        $userDocument->faq_description = $request->faqDescription; 
        $userDocument->is_publish = $request->isPublish ? $request->isPublish : 0; 
        $userDocument->role_id = $request->roleId; 
        $userDocument->modified_id = $authId;
        $userDocument->save(); 

        return response()->json(['status'=>true,'code'=>200,'message'=>'Faq has been updated successfully.'],200);
    }

    public function deleteFaqById($id){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $userDocument = Faq::where('is_active','!=','0')->find($id);
        if(is_null($userDocument)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Faq not found.'],400);
        }

        $userDocument->is_active = 0; 
        $userDocument->modified_id = $authId;
        $userDocument->save(); 

        return response()->json(['status'=>true,'code'=>200,'message'=>'Faq has been deleted successfully.'],200);
    }

    public function faqOrder(Request $request){

        $validator = Validator::make($request->all(), [
            'orders' => 'required|array'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

       foreach($request->orders as $k => $order){
            $faq = Faq::where('faq_id',$k);
            if($faq->count() > 0){
                $faq->update([
                    'order' => $order
                ]);
            }
       }

       return response()->json(['status'=>true,'code'=>200,'message'=>'Faq has been updated successfully.'],200);

    }

}
