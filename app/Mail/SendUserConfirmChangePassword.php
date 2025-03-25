<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendUserConfirmChangePassword extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */


    private $email;
    private $name;
    private $user_name;
    private $password;
    private $lang;
    private $validation_code;

    public function __construct($email, $name, $user_name, $lang, $validation_code)
    {
        $this->email = $email;
        $this->name = $name;
        $this->user_name = $user_name;
        $this->lang = $lang;
        $this->validation_code = $validation_code;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $app_env = strtolower(env('APP_ENV'));
        $app_url = 'https://portal.vcdn.net.br/recover-password';

        if (isset($app_env)){
            if (($app_env == 'dev')  || ($app_env == 'sandbox') || ($app_env == 'qa')){
                $app_url = 'https://portal.' . $app_env . '.vcdn.net.br/recover-password';
            }else{
                $app_env = null;
            }
        }

        switch ($this->lang) {
            case 'en':
                $bladeLang = 'emails.UserConfirmChangePasswordEN';
                $subjectMessage = 'Confirmation of password change to access the VCDN Portal';
                $environmentMessage = isset($app_env) ? ' - Email sent environment ' . strtoupper($app_env): '';
                break;
            case 'es':
                $bladeLang = 'emails.UserConfirmChangePasswordES';
                $subjectMessage = 'Confirmación alteración contraseña acceso al Portal VCDN';
                $environmentMessage = isset($app_env) ? ' - Email enviado ambiente ' . strtoupper($app_env): '';
                break;
            case 'ptbr':
                $bladeLang = 'emails.UserConfirmChangePasswordPTBR';
                $subjectMessage = 'Confirmação alteração senha acesso ao Portal VCDN';
                $environmentMessage = isset($app_env) ? ' - Email enviado ambiente ' . strtoupper($app_env): '';
                break;
            default:
                $bladeLang = 'emails.UserConfirmChangePasswordPTBR';
                $subjectMessage = 'Confirmação alteração senha acesso ao Portal VCDN';
                $environmentMessage = isset($app_env) ? ' - Email enviado ambiente ' . strtoupper($app_env): '';
                break;
        }

        return $this->from(env('MAIL_FROM_ADDRESS'))->markdown($bladeLang)
                     ->subject($subjectMessage . $environmentMessage)
                     ->with([
                        'email' => $this->email,
                        'user_name' => $this->user_name,
                        'name' => $this->name,
                        'link' => $app_url,
                        'validation_code' => $this->validation_code
                    ]);
    }
}
