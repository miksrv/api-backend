<?php namespace App\Controllers;

use FITLibrary;

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, OPTIONS");


/**
 * Class Astro
 * @package App\Controllers
 */
class Astro extends BaseController
{
    protected $_webcam_url = 'http://astro.myftp.org:8002/jpg/1/image.jpg';

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

    function get($action)
    {
        $Sensors = new \Sensors(['source' => 'astro']);
        $FITData = new FITLibrary();

        switch ($action)
        {
            // Summary data on sensors of the observatory
            case 'summary' :
                $this->response->setJSON( $Sensors->summary() )->send();
                break;

            // Statistics for graphing by sensors in the observatory
            case 'statistic' :
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
                $request = \Config\Services::request();
                $objName = $request->getVar('name', FILTER_SANITIZE_STRING);

                $this->response->setJSON( $FITData->statistics_object($objName) )->send();
                break;

            // FIT file data
            case 'webcam' :
                if ( ! $photo = cache('webcam_photo'))
                {
                    $photo = file_get_contents($this->_webcam_url);

                    cache()->save('webcam_photo', $photo, 30);
                }

                $this->response->setHeader('Content-Type', 'image/pjpeg')->setBody($photo)->send();
                break;

            default : throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }
}