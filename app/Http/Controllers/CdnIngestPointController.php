<?php

namespace App\Http\Controllers;

use App\Repositories\IngestPointRepository;
use Illuminate\Http\Request;

class CdnIngestPointController extends Controller
{

    private $ingestPointRepository;


   public function __construct()
   {
    $this->ingestPointRepository = new IngestPointRepository();
   }

   public function getAll()
    {
        $response  = $this->ingestPointRepository->getAll();
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function create(Request $request)
    {

        $response  = $this->ingestPointRepository->create($request->all());
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }

    public function delete(Request $request)
    {
        $response  = $this->ingestPointRepository->delete($request->all());
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }
}
