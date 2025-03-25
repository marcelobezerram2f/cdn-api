<?php

namespace App\Repositories\Salesforce\Lib;



class SalesforceAPIRepository
{

    public function Token($endpoint, $client_id, $client_secret, $username, $password)
    {
        $curl = curl_init($endpoint);
        curl_setopt($curl, CURLOPT_URL, $endpoint);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, "grant_type=password&client_id=" . $client_id . "&client_secret=" . $client_secret . "&username=" . $username . "&password=" . $password);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded"));
        $resp = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($status != 200) {
            return "erro";
        }
        $retorno = json_decode($resp);
        return $retorno->access_token;
    }

    public function Patch($endpoint, $token, $dados)
    {

        $curl = curl_init($endpoint);
        curl_setopt($curl, CURLOPT_URL, $endpoint);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer $token",
            "Content-type: application/json"
        )
        );

        curl_setopt($curl, CURLOPT_POSTFIELDS, $dados);

        $resp = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($status != 200 && $status != 201) {
            return "erro";
        }
        return json_decode($resp);
    }

    public function Put($endpoint, $token, $dados)
    {

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $endpoint);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer $token",
            "Content-type: text/csv",
            "Accept: application/json"
        )
        );

        curl_setopt($curl, CURLOPT_POSTFIELDS, $dados);

        $resp = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($status != 200 && $status != 201) {
            return "erro";
        }
        return json_decode($resp);
    }

    public function Get($endpoint, $token, $query)
    {

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $endpoint . $query);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer $token",
            "Content-type: application/json; charset=UTF-8",
            "Accept: application/json"
        )
        );

        $resp = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($status != 200 && $status != 201) {
            return "erro";
        }
        //echo $resp;
        return json_decode($resp);
    }

    public function Post($endpoint, $token, $dados)
    {

        $curl = curl_init($endpoint);
        curl_setopt($curl, CURLOPT_URL, $endpoint);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Authorization: Bearer $token",
            "Content-type: application/json",
            "Accept: application/json"
        )
        );

        curl_setopt($curl, CURLOPT_POSTFIELDS, $dados);

        $resp = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($status != 200 && $status != 201) {
            return "erro";
        }
        return json_decode($resp);
    }

}

