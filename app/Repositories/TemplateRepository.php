<?php

namespace App\Repositories;
use App\Models\CdnOriginGroup;
use App\Models\CdnTemplate;



class TemplateRepository
{

    private $cdnTemplate;

    public function __construct()
    {
        $this->cdnTemplate = new CdnTemplate();
    }


    public function getAll()
    {
        $templates = $this->cdnTemplate->all();

        $response = [];
        foreach($templates as $template) {
            $array =  [
                'template_name' =>$template->template_name,
                'label' => $template->label,
                'id' => $template->id
            ];
            array_push($response, $array);
            unset($array);
        }

        return $response;

    }
    public function getTemplateId($templateName)
    {
        if ($templateName == null) {
            $templateName = 'default';
        }
        $template = $this->cdnTemplate->where('template_name', $templateName)->first();
        return $template->id;
    }


    public function getTemplateName($templateId)
    {
        $template = $this->cdnTemplate->find($templateId);
        return $template->template_name;
    }



}
