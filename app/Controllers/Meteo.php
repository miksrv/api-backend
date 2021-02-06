<?php namespace App\Controllers;

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, OPTIONS");


/**
 * Class Astro
 * @package App\Controllers
 */
class Meteo extends BaseController
{
    protected $_webcam_url = 'http://astro.myftp.org:8002/jpg/1/image.jpg';

    function set($action)
    {
        switch ($action)
        {
            default : throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }

    function get($action)
    {
        switch ($action)
        {
            case 'events' :
                break;

            case 'forecast' :
                break;

            case 'summary' :
                break;

            case 'statistic' :
                break;

            default : throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
    }
}