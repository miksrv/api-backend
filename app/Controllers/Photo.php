<?php namespace App\Controllers;

use App\Models\PhotoModel;
use FITLibrary;

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, OPTIONS");

/**
 * Class Photo
 * @package App\Controllers
 */
class Photo extends BaseController
{

    function get($action)
    {
        $PhotoModel = new PhotoModel();
        $FITData = new FITLibrary();

        switch ($action)
        {
            // Summary data on sensors of the observatory
            case 'list' :
                $this->response->setJSON( $PhotoModel->get_all() )->send();
                break;

            // Statistics for graphing by sensors in the observatory
            case 'item' :
                $request = \Config\Services::request();
                $objName = $request->getVar('name', FILTER_SANITIZE_STRING);
                
                $dataPhoto = $PhotoModel->get_by_name($objName);

                if (empty($dataPhoto)) {
                    log_message('error', '[' . __METHOD__ . '] Empty photo data (' . json_encode($objName) . ')');
                    return $this->response->setStatusCode(400)->setJSON(['status' => false])->send();
                }

                $dataPhoto[0]->status    = true;
                $dataPhoto[0]->statistic = $FITData->full_stat_item($objName, $dataPhoto[0]->photo_date);

                $this->response->setJSON($dataPhoto[0])->send();
                break;

            default : throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }
}