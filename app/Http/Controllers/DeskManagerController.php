<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\DeskManager\DeskManagerRepository;


class DeskManagerController extends Controller
{

    private $deskmanager;


    public function __construct()
    {
        $this->deskmanager = new DeskManagerRepository();
    }

    public function openCase(Request $request)
    {
        $data = $request->all();
        $data['token'] = $request->bearerToken();
        $response = $this->deskmanager->DeskManagerOpenCase($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }


    public function infoCases(Request $request)
    {
        $data = $request->all();
        $data['token'] = $request->bearerToken();
        $response = $this->deskmanager->deskmanagerInfoCases($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function addComments(Request $request)
    {
        $data = $request->all();
        $data['token'] = $request->bearerToken();
        $response = $this->deskmanager->deskmanagerAddCommentOfCase($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function getCases(Request $request)
    {
        $data = $request->all();
        $data['token'] = $request->bearerToken();
        $response = $this->deskmanager->deskManagerGetCases($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function getClosedCases(Request $request)
    {
        $data = $request->all();
        $data['token'] = $request->bearerToken();
        $response = $this->deskmanager->deskManagerClosedCases($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

}
