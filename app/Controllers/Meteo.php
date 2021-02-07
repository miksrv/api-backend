<?php namespace App\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use Sensors;

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, OPTIONS");

/**
 * Class Meteo
 * @package App\Controllers
 */
class Meteo extends BaseController
{
    function set($action)
    {
        switch ($action)
        {
            default : throw PageNotFoundException::forPageNotFound();
        }
    }

    function get($action)
    {
        $Sensors = new Sensors([
            'source'   => 'meteo',
            'dataset'  => ['t2','h','p','dp','uv','lux','ws','wd'],
            'dewpoint' => ['t' => 't2', 'h' => 'h']
        ]);

        switch ($action)
        {
            case 'summary' :
                $this->response->setJSON( $Sensors->summary() )->send();
                break;

            case 'statistic' :
                $this->response->setJSON( $Sensors->statistic() )->send();
                break;

            case 'forecast' :
                $OpenWeather = new \OpenWeather();
                $this->response->setJSON( $OpenWeather->get_forecast() )->send();
                break;

            default : throw PageNotFoundException::forPageNotFound();
        }
    }
}