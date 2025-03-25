<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;



function formOriginServer($form)
{

    $validated = array_map('OriginServerValidate', $form);
    $errors = [];

    foreach ($validated as $validationResult) {
        foreach ($validationResult as $result) {
            if ($result !== 1) {
                $errors[] = $result;
            }
        }
    }

    return empty($errors) ? [] : $errors;


}

function OriginServerValidate($form)
{

    return [
        is_null($form['cdn_origin_hostname']) ? 'Origin server hostname must be informed .' : 1,
        is_null($form['cdn_origin_protocol']) ? 'Origin server protocol must be informed .' : 1,
        is_null($form['cdn_origin_server_port']) ? 'Origin server port must be informed .' : 1,
        domainValidade($form['cdn_origin_hostname']) == false ? 'Primary origin server hostname is in an invalid format, or has not been entered.' : 1,
    ];

}


/**
 * função que retorna as mensagnes do(s) campos inválidos
 *
 * @param array $form
 *
 */

function formRequest($form)
{
    $validate = formValidade($form);
    $length = count($validate);
    $trues = repeatValues($validate, 1);
    if ($length > $trues) {
        return array_filter($validate, function ($valor) {
            return $valor !== 1;
        });

    } else {
        return [];
    }

}


function formRequestResource($form)
{
    $validate = formValidadeResource($form);
    $length = count($validate);
    $trues = repeatValues($validate, 1);
    if ($length > $trues) {
        return array_filter($validate, function ($valor) {
            return $valor !== 1;
        });
    } else {
        return [];
    }
}


function formRequestCname($form)
{
    $validate = formCnameValidade($form);
    $length = count($validate);
    $trues = repeatValues($validate, 1);
    if ($length > $trues) {
        return array_filter($validate, function ($valor) {
            return $valor !== 1;
        });

    } else {
        return [];
    }

}

function formCnameValidade($form)
{
    return [
        is_null($form['cdn_resource_hostname']) || empty($form['cdn_resource_hostname']) || !isset($form['cdn_resource_hostname']) ? 'cdn_resource_hostname must be informed.' : 1,
        is_null($form['cname']) || empty($form['cname']) || !isset($form['cname']) ? 'CNAME must be informed.' : 1,
    ];
}

/**
 * Função que retorna a validação dos campos de provisinamento de CDN
 *
 */

function formValidade($form)
{
    return [
        is_null($form['external_id']) || empty($form['external_id']) || !isset($form['external_id']) ? 'external_id identification not provided.' : 1,
        is_null($form['client_id']) || empty($form['client_id']) || !isset($form['client_id']) ? 'client_id identification not provided.' : 1,
        is_null($form['name_base']) ? 'name base is required.' : 1,
        targetGroupValidade($form['cdn_target_group']) == false ? 'Traffic type does not exist, Global or Local must be entered, or is_null.' : 1,
    ];
}


/**
 * Função que retorna a validação dos campos de provisinamento de CDN
 *
 *
 */
function formValidadeResource($form)
{
    if (is_null($form['cdn_target_group'])) {
        $targetGroup = 1;
    } else {
        $targetGroup = targetGroupValidade($form['cdn_target_group']) == false ? 'Traffic type does not exist, Global or Local must be entered, or has not been entered.' : 1;
    }

    if (is_null($form['cdn_template_name']) || empty($form['cdn_template_name'])) {
        $templateName = 'Template name is required.';

    } else {
        $differentTemplateFields = templateValFields($form['cdn_template_name'], $form);
        $templateName = !is_null($differentTemplateFields) ? $differentTemplateFields : 1;
    }
    return [
        is_null($form['tenant']) || empty($form['tenant']) || !isset($form['tenant']) ? 'tenant must be informed .' : 1,
        checkTenant($form['tenant']) == false ? 'tenant has not been provisioned, wait for the provisioning to finish' : 1,
        domainValidade($form['cdn_resource_hostname']) == false ? 'Resource hostname is in an invalid format, or has not been entered.' : 1,
        ingestPointRequest($form['cdn_ingest_point']) == false ? 'Ingest point location does not exist, or was not entered.' : 1,
        $targetGroup,
        $templateName,
        is_null($form['cdn_template_name']) ? 'Type of content must be informed.' : 1,
        hasResource($form['cdn_resource_hostname']) == false ? 'Existing cdn resource hostname, enter another hostname.' : 1,
    ];
}


/**
 * Valida um cliente.
 *
 * @param array $form O formuário com as chaves external_id ou client_id ou ambas.
 * @return bool True se o uma das das chaves existir e conter informações será válido, False caso contrário.
 */

function validateClient($form)
{
    $client_id = 0;
    $external_id = 0;
    if (isset($form['external_id']) && !is_null($form['external_id']) && !empty($form['external_id'])) {
        $external_id = 1;
    }
    if (isset($form['client_id']) && !is_null($form['client_id']) && !empty($form['client_id'])) {
        $client_id = 1;
    }
    return ($external_id + $client_id) > 0;
}

function accountValidade($accountName)
{
    return preg_match('/^[a-z0-9]+$/i', $accountName) === 1;
}

/**
 * verifica se o tenant fo provisionado
 * @param  string $tenant
 *
 * @return boolean
 */


function checkTenant($tenant)
{
    $tenant = DB::table('cdn_tenants')->where('tenant', $tenant)->first();

    if (is_null($tenant->api_key)) {
        return false;
    } else {
        return true;
    }

}



/**
 * Valida um domínio.
 *
 * @param string $domain O domínio a ser validado.
 * @return bool True se o domínio for válido, False caso contrário.
 */
function domainValidade($domain)
{
    if (!$domain) {
        return false;
    } else {
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return false;
        }
    }
    // Verifica se o domínio está em um formato válido
    return true;
}



function targetGroupValidade($form)
{

    $targetGroup = DB::table('cdn_target_groups')->where('plan', strtoupper($form))->count();
    return $targetGroup > 0 ? true : false;


}

/**
 * Conta quantas vezes um valor específico se repete em um array.
 *
 * @param array $array O array no qual contar os valores.
 * @param mixed $valor O valor a ser contado no array.
 * @return int O número de vezes que o valor se repete no array.
 */
function repeatValues($array, $value)
{
    // Conta os valores no array
    $count = array_count_values($array);

    // Retorna a contagem do valor específico, ou 0 se o valor não estiver presente
    return isset($count[$value]) ? $count[$value] : 0;
}


function ingestPointValidade($name)
{

    $ingestPointId = DB::table('cdn_ingest_points')->where('name', $name)->first();
    if (is_null($ingestPointId)) {
        return false;
    } else {
        return true;
    }

}

function ingestPointLocation($location)
{

    $ingestPointId = DB::table('cdn_ingest_points')->where('location', $location)->first();
    if (is_null($ingestPointId)) {
        return false;
    } else {
        return true;
    }

}

function templateValidade($name)
{
    if ($name == null) {
        $name = 'default';
    }
    $ingestPointId = DB::table('cdn_templates')->where('template_name', $name)->first();
    if (is_null($ingestPointId)) {
        return false;
    } else {
        return true;
    }

}

function hasResource($name)
{
    if ($name == null) {
        $name = 'default';
    }
    $ingestPointId = DB::table('cdn_resources')->where('cdn_resource_hostname', $name)->first();
    if (is_null($ingestPointId)) {
        return true;
    } else {
        return false;
    }

}


function ingestPointRequest($form)
{
    $ingestPoint = DB::table('cdn_ingest_points')->where('pop_prefix', strtoupper($form))->count();
    return $ingestPoint > 0 ? true : false;
}


function hasTemplate($form)
{
    if (!is_null($form)) {
        $template = DB::table('cdn_templates')->where('template_name', strtoupper($form))->count();
        return $template > 0 ? true : false;
    } else {
        return true;
    }
}

function templateValFields($template, $form)
{
    if (!is_null($form)) {
        $hastemplate = DB::table('cdn_templates')->where('template_name', $template)->first();
        if (!$hastemplate) {
            return 'Template name not found';
        }
        else {
            return null;

        }
    }
}


/**
 * Valida os dados para alteração password conta do usuario master.
 *
 * @param array $form.
 * @param bool $verifyPassword True tem que verificar o password enviado - false password não enviado
 * @return bool True dados validados, False caso contrário.
 */
function validateDataUser($form, $verifyEmail = false, $verifyPassword = false, $verifyCode = false, $verifyUserName = false, $verifyCurrentPassword = false)
{

    $validate = formUserDataValidade($form, $verifyEmail, $verifyPassword, $verifyCode, $verifyUserName, $verifyCurrentPassword);
    $length = count($validate);
    $trues = repeatValues($validate, 1);
    if ($length > $trues) {
        return array_filter($validate, function ($valor) {
            return $valor !== 1;
        });

    } else {
        return [];
    }
}

function formUserDataValidade($form, $verifyEmail, $verifyPassword, $verifyCode, $verifyUserName, $verifyCurrentPassword)
{

    $messageEmail = null;
    $messagePass = null;
    $messageCode = null;
    $messageUserName = null;
    $messageCurrentPassword = null;

    if ($verifyEmail) {
        if (!isset($form['email']) || empty($form['email']) || $form['email'] == '') {
            $messageEmail = 'Email must be informed.';
        } else {
            if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $form['email']))
                $messageEmail = 'Email provided with incorrect format';
        }
    }

    if ($verifyPassword) {
        if (!isset($form['new_password']) || is_null($form['new_password']) || empty($form['new_password'])) {
            $messagePass = 'New password must be informed.';
        } else {
            $messagePass = validatePassword($form['new_password']);
        }
    }

    if ($verifyCode) {
        if (!isset($form['validation_code']) || is_null($form['validation_code']) || empty($form['validation_code']))
            $messageCode = 'Validation code must be informed.';
    }

    if ($verifyUserName) {
        if (!isset($form['user_name']) || is_null($form['user_name']) || empty($form['user_name']))
            $messageUserName = 'User name must be informed.';
    }

    if ($verifyCurrentPassword) {
        if (!isset($form['current_password']) || is_null($form['current_password']) || empty($form['current_password']))
            $messageUserName = 'Current password must be informed.';
    }

    return [
        is_null($messageEmail) ? 1 : $messageEmail,
        is_null($messagePass) ? 1 : $messagePass,
        is_null($messageCode) ? 1 : $messageCode,
        is_null($messageUserName) ? 1 : $messageUserName,
        is_null($messageCurrentPassword) ? 1 : $messageCurrentPassword
    ];
}

function validatePassword($password)
{
    $message = null;

    // Longitude mínima de 8 caracteres
    if (strlen($password) < 8) {
        $message .= is_null($message) ? "Password in incorrect format. Must contain at least 8 characters" : ", at least 8 characters";
    }

    // Ao menos menos um número
    if (!preg_match('/\d/', $password)) {
        $message .= is_null($message) ? "Password in incorrect format. Must contain at least one number" : ", at least one number";
    }

    // Ao menos um carácter especial
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $message .= is_null($message) ? "Password in incorrect format. Must contain at least one special character" : ", at least one special character";
    }

    // Ao menos una letra minúscula
    if (!preg_match('/[a-z]/', $password)) {
        $message .= is_null($message) ? "Password in incorrect format. Must contain at least one lowercase letter" : ", at least one lowercase letter";
    }

    // Ao menos una letra maiúscula
    if (!preg_match('/[A-Z]/', $password)) {
        $message .= is_null($message) ? "Password in incorrect format. Must contain at least one uppercase letters" : ", at least one uppercase letter";
    }

    return $message;
}

function validateEmailUser($data)
{

    $messageEmail = null;

    if (!isset($data['email']) || empty($data['email']) || $data['email'] == '') {
        $messageEmail = 'To create a new account you need to enter a valid e-mail address.';
    } else {
        if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $data['email']))
            $messageEmail = 'Incorrect format of email provided for new account.';
    }

    return $messageEmail;
}

function generateValidationCode($user_name, $password)
{
    // Combina o nome do usuário o hash do password atual e a data/hora
    // Código válido por 1 hora
    $date = date('Y-m-d H');
    $input = $user_name . $password . $date;

    // Gere um hash SHA-256 para o input combinado
    $hash = hash('sha256', $input);

    // Converta uma parte do hash em um número e reduza para 6 dígitos
    $numeric_code = substr(abs(hexdec(substr($hash, 0, 10))), 0, 6);

    return str_pad($numeric_code, 6, '0', STR_PAD_LEFT); // Garante que tenha 6 dígitos
}


