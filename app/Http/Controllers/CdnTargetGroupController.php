<?php

namespace App\Http\Controllers;

use App\Repositories\TargetGroupRepository;
use Illuminate\Http\Request;

class CdnTargetGroupController extends Controller
{
    private $targetGroupRepository;


    public function __construct()
    {
     $this->targetGroupRepository = new TargetGroupRepository();
    }

    public function getAll()
     {
         $response  = $this->targetGroupRepository->getAll();
         if(isset($response['code'])) {
             $code = $response['code'];
         } else {
             $code = 200;
         }
         return response()->json($response, $code);
     }

     public function create(Request $request)
     {

         $response  = $this->targetGroupRepository->create($request->all());
         if(isset($response['code'])) {
             $code = $response['code'];
         } else {
             $code = 200;
         }
         return response()->json($response, $code);

     }

     public function delete(Request $request)
     {
         $response  = $this->targetGroupRepository->delete($request->all());
         if(isset($response['code'])) {
             $code = $response['code'];
         } else {
             $code = 200;
         }
         return response()->json($response, $code);

     }
}
