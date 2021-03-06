<?php

namespace App\Controllers;

use App\Libraries\FITLibrary;
use App\Libraries\Sensors;

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, OPTIONS");
header('Access-Control-Allow-Headers: Accept, AuthToken, Content-Type');

/**
 * Class Astro
 * @package App\Controllers
 */
class Astro extends BaseController
{

    function set($action)
    {
        $FITData = new FITLibrary();

        switch ($action)
        {
            case 'fit_object':
                $request = \Config\Services::request();
                $RAWData = $request->getJSON();

                if ( ! is_object($RAWData) || ! isset($RAWData->OBJECT))
                {
                    log_message('error', '[' . __METHOD__ . '] Empty RAW data (' . json_encode($RAWData) . ')');
                    return $this->response->setStatusCode(400)->setJSON(['status' => false])->send();
                }

                $FITData->create_fit_array($RAWData);
                $FITData->save_fit();

                $this->response->setJSON(['status' => true])->send();

                break;

            default : throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }

    public function clear()
    {
        $Sensors = new Sensors(['source' => 'astro']);
    }

    public function delete($action)
    {
        $token = $this->request->getHeaderLine('authtoken');

        if (empty($token))
        {
            log_message('error', '[' .  __METHOD__ . '] Empty or wrong AuthToken');
            $this->response->setStatusCode(200)->setJSON(['status' => false])->send();
            exit();
        }

        $FITData = new FITLibrary();

        switch ($action) {
            case 'fit' :
                $fileID = $this->request->getGet('id');
                $result = $FITData->delete($fileID);

                if ($result)
                    log_message('info', '[' .  __METHOD__ . '] FIT file (' . $fileID . ') deleted');

                $this->response->setStatusCode(200)->setJSON(['status' => $result])->send();
                break;
        }
    }

    function get($action)
    {
        $request = \Config\Services::request();
        $Sensors = new Sensors(['source' => 'astro']);
        $FITData = new FITLibrary();

        switch ($action)
        {
            // Summary data on sensors of the observatory
            case 'summary' :
                $this->response->setJSON( $Sensors->summary() )->send();
                break;

            // Statistics for graphing by sensors in the observatory
            case 'statistic' :

                if ($this->request->getGet('dataset'))
                {
                    $dataset = explode(',', $this->request->getGet('dataset'));

                    if (is_array($dataset)) $Sensors->set_dataset($dataset);
                }

                $this->response->setJSON( $Sensors->statistic() )->send();
                break;

            // FIT file data
            case 'fit_stats' :
                $this->response->setJSON( $FITData->statistics() )->send();
                break;

            // FIT file data
            case 'archive' :
                $month = date('m');
                $year  = date('Y');
                $date  = $this->request->getGet('date');
                if ( ! empty($date))
                {
                    $date = strtotime($date);
                    if (checkdate(date('m', $date), date('d', $date), date('Y', $date)))
                    {
                        $month = date('m', $date);
                        $year  = date('Y', $date);
                    }
                }
                $this->response->setJSON( $FITData->archive($month, $year) )->send();
                break;

            // FIT file data for object by name
            case 'fit_object_stats' :
                $objName = $request->getVar('name', FILTER_SANITIZE_STRING);

                $this->response->setJSON( $FITData->statistics_object($objName) )->send();
                break;

            // FIT file data for object by name
            case 'day_object_stats' :
                $objDate = $request->getVar('date', FILTER_SANITIZE_STRING);

                $this->response->setJSON( $FITData->statistics_day($objDate) )->send();
                break;

            // FIT file data
            case 'webcam' :
                if ( ! $photo = cache('webcam_photo'))
                {
                    $photo = file_get_contents(getenv('app.observatory.webcam'));

                    cache()->save('webcam_photo', $photo, 30);
                }

                $this->response->setHeader('Content-Type', 'image/pjpeg')->setBody($photo)->send();
                break;

            default : throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }
}