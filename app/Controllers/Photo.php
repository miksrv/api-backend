<?php namespace App\Controllers;

use App\Models\PhotoModel;
use App\Libraries\FITLibrary;
use App\Libraries\Photo as libPhoto;

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, OPTIONS");

/**
 * Class Photo
 * @package App\Controllers
 */
class Photo extends BaseController
{
    protected $_libPhoto;

    function __construct()
    {
        $this->_libPhoto = new libPhoto();
    }

    function get($action)
    {
        $PhotoModel = new PhotoModel();
        $FITData = new FITLibrary();
        $request = \Config\Services::request();

        switch ($action)
        {
            case 'list' :
                $this->response->setJSON([
                    'status' => true,
                    'photos' => $this->_libPhoto->get_list()
                ])->send();

                break;
                
            case 'item' :
                $varName  = $request->getVar('name', FILTER_SANITIZE_STRING);
                $varDate  = $request->getVar('date', FILTER_SANITIZE_STRING);
                $objPhoto = $this->_libPhoto->get_item($varName, $varDate);

                if (!$objPhoto) $this->_send_error(__METHOD__, "Empty photo data ($varName)");

                $this->response->setJSON(array_merge(['status' => true], $objPhoto))->send();

                break;
                
                

//            // Summary data on sensors of the observatory
//            case 'list' :
//                $this->response->setJSON( $PhotoModel->get_all() )->send();
//                break;
//
//            // Statistics for graphing by sensors in the observatory
//            case 'item' :
//                $objName   = $request->getVar('name', FILTER_SANITIZE_STRING);
//                $dataPhoto = $PhotoModel->get_by_name($objName);
//
//                if (empty($dataPhoto)) {
//                    log_message('error', '[' . __METHOD__ . '] Empty photo data (' . json_encode($objName) . ')');
//                    $this->response->setJSON(['status' => false])->send();
//                    exit();
//                }
//
//                $dataPhoto[0]->status = true;
//                $dataPhoto[0]->stats  = $FITData->get_fits_stat([], $objName, $dataPhoto[0]->photo_date);
//
//                $this->response->setJSON($dataPhoto[0])->send();
//                break;

            case 'download' :
                $objName  = $request->getVar('name', FILTER_SANITIZE_STRING);
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/public/photo/' . $objName . '.jpg';

                if ( ! file_exists($filePath)) {
                    throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
                }

                header('Content-Type: application/octet-stream');
                header("Content-Transfer-Encoding: Binary"); 
                header("Content-disposition: attachment; filename=\"" . basename($filePath) . "\""); 
                readfile($filePath); 

                break;

            default : throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }

    /**
     * Send JSON error
     * @param string $method
     * @param string $log
     */
    protected function _send_error(string $method, string $log)
    {
        log_message('error', '[' . $method . '] ' . $log);

        $this->response->setJSON(['status' => false])->send();

        exit();
    }
}