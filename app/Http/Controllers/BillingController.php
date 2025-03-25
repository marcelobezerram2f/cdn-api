<?php

namespace App\Http\Controllers;

use App\Repositories\BillingRepository;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    private $billingRepository;


    public function __construct()
    {
        $this->billingRepository = new BillingRepository();
    }

    public function getSummarized(Request $request)
    {
        $response = $this->billingRepository->getSummarized($request->all());
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function getStatement(Request $request)
    {
        $response = $this->billingRepository->getStatement($request->all());
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }


    public function getSummariesHour(Request $request)
    {
        $response = $this->billingRepository->getSummariesHour($request->all());
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function getRawData(Request $request)
    {
        $response = $this->billingRepository->getRawData($request->all());
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }



}
