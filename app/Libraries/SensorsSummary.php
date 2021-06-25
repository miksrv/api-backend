<?php

namespace App\Libraries;

set_time_limit(0);

class SensorsSummary {

    protected $_source;
    protected $_dataModel;
    protected $counter = 0;

    function __construct($param = [])
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');

        helper(['transform', 'calculate']);

        $this->_dataModel = model('App\Models\SensorData');
        $this->_source    = isset($param['source']) ? $param['source'] : 'meteo';
    }

    /**
     * @throws \Exception
     */
    function run()
    {
        $this->counter++;

        $lastTotalData = $this->_dataModel->get_last(true);

        if (empty($lastTotalData) || ! is_array($lastTotalData))
            $lastTotalData = $this->_dataModel->get_last();

        $sensors = $this->_get_last_sensor_data($lastTotalData[0]->item_timestamp);
        $summary = $this->_create_data_summary($sensors->data);
        $dateSum = date('Y-m-d H:00:00', $sensors->time);

        $this->_get_date_diff_hours($dateSum);
        $this->_set_total($summary, $dateSum);

        if ($this->counter >= 100)
        {
            echo 'Max iteration (' . $this->counter . ') complete';
            exit();
        }

        $this->run();
    }

    protected function _get_date_diff_hours($date)
    {
        $date1 = new \DateTime($date);
        $date2 = new \DateTime();
        $diff  = $date1->diff($date2);
        $hours = $diff->h;
        $hours = $hours + ($diff->days*24);

        if ($hours <= 1)
        {
            echo 'The difference between dates is less than 1 hour';
            exit();
        }
    }

    protected function _get_last_sensor_data($time)
    {
        $time = strtotime($time . ' +1 hours');
        $data = $this->_dataModel->get_sensor_by_hour(
            date('Y', $time),
            date('m', $time),
            date('d', $time),
            date('H', $time)
        );

        if (empty($data) || ! is_array($data))
        {
            $time = date('Y-m-d H:i:s', $time);
            $data = $this->_dataModel->get_sensor_by_min_date($time);

            $time = strtotime($data[0]->item_timestamp);
            $data = $this->_dataModel->get_sensor_by_hour(
                date('Y', $time),
                date('m', $time),
                date('d', $time),
                date('H', $time)
            );
        }

        return (object) [
            'data' => $data,
            'time' => $time
        ];
    }

    protected function _create_data_summary($data): array
    {
        $count  = 0;
        $result = [];

        if ( ! empty($data))
        {
            foreach ($data as $item)
            {
                $count++;
                $sensorData = json_decode($item->item_raw_data);
                foreach ($sensorData as $sensor => $value)
                {
                    if ( ! isset($result[$sensor]))
                        $result[$sensor] = $value;
                    else
                        $result[$sensor] += $value;
                }
            }

            foreach ($result as $sensor => $value) {
                $result[$sensor] = round($value / $count, 1);
            }
        }

        return $result;
    }

    protected function _set_total($data, $time)
    {
        $data = json_encode($data);

        return $this->_dataModel->set_total($data, $time);
    }











    function get_last_hour() {
        return $this->_dataModel->get_day_order();
        //return $this->_dataModel->get_day();
    }

    function get_day($year, $month, $day, $hour)
    {
        return $this->_dataModel->get_hour($year, $month, $day, $hour);
    }

    function get_last_total()
    {
        return $this->_dataModel->get_last_total();
    }

    function set_total($data, $time)
    {
        return $this->_dataModel->set_total($data, $time);
    }
}