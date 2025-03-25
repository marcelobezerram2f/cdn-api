<?php

namespace App\Http\Controllers;

use App\Repositories\Salesforce\SalesforceRepository;
use Illuminate\Http\Request;

class SalesforceController extends Controller
{

    private $salesforce;


    public function __construct()
    {
        $this->salesforce = new SalesforceRepository();
    }

    public function openCase(Request $request){
        $data = $request->all();
        $data['token'] = $request->bearerToken();
        $response  =  $this->salesforce->salesforceOpenCase($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }


    public function infoCases(Request $request){
        $data = $request->all();
        $data['token'] = $request->bearerToken();
        $response  =  $this->salesforce->salesforceInfoCases($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function addComments(Request $request){
        $data = $request->all();
        $data['token'] = $request->bearerToken();
        $response  =  $this->salesforce->salesforceAddCommentOfCase($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function getCases(Request $request){
        $data = $request->all();
        $data['token'] = $request->bearerToken();
        $response  =  $this->salesforce->salesforceGetCases($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function getClosedCases(Request $request){
        $data = $request->all();
        $data['token'] = $request->bearerToken();
        $response  =  $this->salesforce->salesforceGetClosedCases($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

}

