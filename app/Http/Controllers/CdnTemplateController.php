<?php

namespace App\Http\Controllers;

use App\Repositories\TemplateRepository;
use Illuminate\Http\Request;

class CdnTemplateController extends Controller
{
    private $templateRepository;


    public function __construct()
    {
        $this->templateRepository = new TemplateRepository();
    }


    public function getAll()
    {
        $response  =  $this->templateRepository->getAll();
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }
}
