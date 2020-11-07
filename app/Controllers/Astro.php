<?php namespace App\Controllers;

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, OPTIONS");


/**
 * Class Astro
 * Working with astronomical images (FITS)
 * @package App\Controllers
 */
class Astro extends BaseController
{
    /**
     * Designed for the parser of FIT files, the file headers are transferred in the request,
     * which are saved to the database
     * @return \CodeIgniter\HTTP\Response
     */
    function set_fit()
    {
        $request = \Config\Services::request();
        $RAWData = $request->getJSON();

        if (!is_object($RAWData) || !isset($RAWData->OBJECT)) {
            log_message('error', '[' . __METHOD__ . '] Empty RAW data (' . json_encode($RAWData) . ')');

            return $this->response->setStatusCode(400)->setJSON(['status' => false])->send();
        }

        $FITLibrary = new \FITLibrary();
        $FITLibrary->create_fit_array($RAWData);
        $FITLibrary->save_fit();

        $this->response->setJSON(['status' => true])->send();
    }

    function get($action)
    {
        $Sensors = new \Sensors(['source' => 'astro']);

        switch ($action)
        {
            case 'summary'   : $summary = $Sensors->summary(); break;
            case 'statistic' : $summary = $Sensors->statistic(); break;

            default : throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $this->response
            ->setJSON([
                'period'  => $summary->period,
                'update'  => strtotime($summary->update),
                'sensors' => $summary->data
            ])->send();
    }
}
