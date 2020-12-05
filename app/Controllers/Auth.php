<?php namespace App\Controllers;

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, OPTIONS");


/**
 * @package App\Controllers
 */
class Auth extends BaseController
{

    function login()
    {
        $request = \Config\Services::request();

        $userLogin = $request->getPost('login', FILTER_SANITIZE_STRING);
        $userPassw = $request->getPost('passw', FILTER_SANITIZE_STRING);

        if (empty($userLogin) || empty($userPassw))
        {
            log_message('error', '[' . __METHOD__ . '] Empty auth data (' . $userLogin . ')');
            $this->response->setStatusCode(400)->setJSON(['status' => false])->send();
            exit();
        }

        if ($userLogin != getenv('app.user_login') ||
            $userPassw != getenv('app.user_passw'))
        {
            log_message('error', '[' . __METHOD__ . '] Wrong login or password (' . $userLogin . ':' . $userPassw . ')');
            $this->response->setStatusCode(400)->setJSON(['status' => false])->send();
            exit();
        }

        $UserAuth = new \UserAuth();

        $token = $UserAuth->do_login($userLogin);

        log_message('info', '[' . __METHOD__ . '] New session (' . $userLogin . ':' . $token . ')');
        $this->response->setStatusCode(200)->setJSON(['status' => true, 'token' => $token])->send();
        exit();
    }

    function logout()
    {
        $request  = \Config\Services::request();
        $UserAuth = new \UserAuth();

        $UserAuth->do_logout($request->getCookie('token'));
    }

    function check()
    {
        $request  = \Config\Services::request();
        $UserAuth = new \UserAuth();

        $token = $request->getCookie('token');

        if (empty($token))
        {
            $this->response->setStatusCode(200)->setJSON(['status' => false])->send();
            exit();
        }

        if ( ! $UserAuth->do_check_token($token))
        {
            $this->response->setStatusCode(200)->setJSON(['status' => false])->send();
            exit();
        }

        $this->response->setStatusCode(200)->setJSON(['status' => true])->send();
    }
}