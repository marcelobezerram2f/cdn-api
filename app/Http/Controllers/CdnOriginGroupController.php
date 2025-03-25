<?php

namespace App\Http\Controllers;

use App\Repositories\CdnOriginServerGroupRepository;
use Illuminate\Http\Request;

class CdnOriginGroupController extends Controller
{
    private $cdnOriginGroup;


    public function __construct()
    {
        $this->cdnOriginGroup = new CdnOriginServerGroupRepository();
    }

    public function getByTenant(Request $request)
    {
        $response = $this->cdnOriginGroup->getByTenant($request->all());
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function create(Request $request)
    {
        $response = $this->cdnOriginGroup->create($request->all());
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }
    public function getById(Request $request)
    {
        $response = $this->cdnOriginGroup->getById($request->all());
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function getOriginServers(Request $request)
    {
        $response = $this->cdnOriginGroup->getOriginServers($request->all());
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }
    public function updateGroup(Request $request)
    {
        $response = $this->cdnOriginGroup->updateGroup($request->all());
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function updateOriginServer(Request $request)
    {
        $response = $this->cdnOriginGroup->updateOriginServer($request->all());
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function deleteGroup(Request $request)
    {
        $response = $this->cdnOriginGroup->deleteGroup($request->all());
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }


    public function deleteOriginServer(Request $request)
    {
        $response = $this->cdnOriginGroup->deleteOriginServer($request->all());
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }
}
