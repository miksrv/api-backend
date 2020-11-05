<?php namespace App\Controllers;

class Relay extends BaseController
{
    protected $_cmd_set = 5;
    protected $_cmd_get = 10;

    /**
     * Get current controller status
     */
    function get()
    {
        $client = \Config\Services::curlrequest();
        $response = $client->get(getenv('app.observatory.url') . '?command=' . $this->_cmd_get);

        $this->_response($response->getBody(), $response->getStatusCode(), __METHOD__);
    }

    /**
     * Set relay pin status
     */
    function set()
    {
        $device = (int) $this->request->getGet('device');
        $status = (int) $this->request->getGet('status');

        if ($device === '' || $status === '')
        {
            log_message('error', '[' .  __METHOD__ . '] Empty $device (' . $device . ') or $status (' . $status . ')');
            $this->_response('', 400, __METHOD__);
        }

        log_message('info', '[' .  __METHOD__ . '] Set device (' . $device . ') status (' . $status . ')');

        $client = \Config\Services::curlrequest();
        $response = $client->get(getenv('app.observatory.url') . '?command=' . $this->_cmd_set . '&pin=' . $device . '&set=' . $status);

        $this->_response($response->getBody(), $response->getStatusCode(), __METHOD__);
    }

    /**
     * JSON response
     * @param $data string
     * @param int $code HTTP string code
     * @param $method class method
     * @return \CodeIgniter\HTTP\Response
     */
    protected function _response($data, $code = 400, $method)
    {
        if ($code !== 200)
        {
            $response = ['status' => false, 'data' => json_decode($data)];

            log_message('error', '[' .  $method . '] Data error: ' . $data);
        }
        else
        {
            $response = ['status' => true, 'data' => json_decode($data)];
        }

        $this->response
            ->setStatusCode($code)
            ->setJSON($response)
            ->send();

        exit();
    }
}
