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
    const CACHE_TIME = 60 * 60 * 12; // 12h

    function set($action)
    {
        switch ($action)
        {
            default : throw PageNotFoundException::forPageNotFound();
        }
    }

    function get($action)
    {
        $period  = $this->_get_period();
        $Sensors = new Sensors([
            'source'    => 'meteo',
            'dataset'   => ['t2','h','p','dp','uv','lux','ws','wd'],
            'dewpoint'  => ['t' => 't2', 'h' => 'h']
        ]);

        switch ($action)
        {
            case 'summary' :
                $this->response->setJSON( $Sensors->summary() )->send();
                break;

            case 'statistic' :
                $_cache_name = "statistic_{$period->start}_{$period->end}";
                $_cache_time = $period->end < date('Y-m-d') ? 2592000 : 60*5; // 1 month or 5 min

                if ( ! $_archive_data = json_decode(cache($_cache_name)))
                {
                    $Sensors->set_range($period->start, $period->end);
                    $_archive_data = $Sensors->statistic();
                    cache()->save($_cache_name, json_encode($_archive_data), $_cache_time);
                }

                $this->response->setJSON( $_archive_data )->send();
                break;

            case 'forecast' :
                $OpenWeather = new \OpenWeather();
                $this->response->setJSON( $OpenWeather->get_forecast() )->send();
                break;

            case 'archive' :
                $_cache_name = "archive_{$period->start}_{$period->end}";

                $Sensors->set_range($period->start, $period->end);

                if ( ! $_archive_data = json_decode(cache($_cache_name))) {
                    $_archive_data = $Sensors->archive();
                    cache()->save($_cache_name, json_encode($_archive_data), self::CACHE_TIME);
                }

                $this->response->setJSON( $_archive_data )->send();
                break;

            case 'kindex' :
                $NooaData = new \NooaData();
                $this->response->setJSON( $NooaData->get_kindex() )->send();
                break;

            default : throw PageNotFoundException::forPageNotFound();
        }
    }

    protected function _get_date($param_name) {
        $date = $this->request->getGet($param_name);

        if ( ! $date) return null;

        $date = strtotime($date);

        if ( ! checkdate(date('m', $date), date('d', $date), date('Y', $date)))
            return null;

        return date('Y-m-d', $date);
    }

    protected function _get_period() {
        $date_start = $this->_get_date('date_start');
        $date_end   = $this->_get_date('date_end');

        if ( ! $date_start || ! $date_end) return (object) [
            'start' => date('Y-m-d'),// date('Y-m-d', strtotime(date('Y-m-d') . '-12 hours')), // Y-m-01
            'end'   => date('Y-m-d')
        ];

        return (object) [
            'start' => $date_start,
            'end'   => $date_end
        ];
    }
}