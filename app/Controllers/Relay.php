<?php namespace App\Controllers;

class Relay extends BaseController
{
    protected $_cmd_set = 5;
    protected $_cmd_get = 10;

    function get_status()
    {
        $client = \Config\Services::curlrequest();
        return $client->get(getenv('app.observatory.url') . '?command=' . $this->_cmd_get);
    }

    function set()
    {
        $device = $this->request->getGet('device');
        $status = $this->request->getGet('status');

        if ( ! $device || !$status)
        {
            log_message('error', '[' .  __METHOD__ . '] Empty $device (' . $device . ') or $status (' . $status . ')');
            return $this->response(['status' => false]);
        }

        log_message('info', '[' .  __METHOD__ . '] Set device (' . $device . ') status (' . $status . ')');

        $client = \Config\Services::curlrequest();
        return $client->get(getenv('app.observatory.url') . '?command=' . $this->_cmd_set . '&pin=' . $device . '&set=' . $status);
    }


    protected function _response($data, $code = 400)
    {
        return $this->response
            ->setStatusCode($code)
            ->setJSON($data)
            ->send();
    }
}
