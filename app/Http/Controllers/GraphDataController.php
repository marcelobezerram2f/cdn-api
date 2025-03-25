<?php

namespace App\Http\Controllers;

use App\Repositories\GraphDataRepository;
use Illuminate\Http\Request;

class GraphDataController extends Controller
{
    private $graphDataRepository;


    public function __construct()
    {
        $this->graphDataRepository = new GraphDataRepository();
    }

    public function fiveMinutesAverage(Request $request)
    {
        $response = $this->graphDataRepository->fiveMinutesAverage($request->all());
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function everyMinutes(Request $request)
    {
        $response = $this->graphDataRepository->everyMinutes($request->all());
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }


    public function daily(Request $request)
    {
        $response = $this->graphDataRepository->daily($request->all());
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function monthly(Request $request)
    {
        $response = $this->graphDataRepository->monthly($request->all());
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }



}
