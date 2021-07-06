<?php

namespace App\Libraries;

#TODO: DEPRECATED

class WeatherGeneral {

    protected $_data;
    protected $_update;
    protected $_period  = ['today', 'yesterday', 'week', 'month'];
    protected $_dataset = [];

    function get_general($param)
    {
        if ( ! isset($param['dataset']) || empty($param['dataset']) || ! is_array($param['dataset']))
        {
            return null;
        }

        helper(['transform', 'calculate']);

        $period    = ( ! isset($param['period']) || ! in_array($param['period'], $this->_period)) ? $this->_period[0] : $param['period'];
        $dataModel = model('App\Models\SensorData');

        $this->_dataset = $param['dataset'];
        $this->_data    = $dataModel->get_period('meteo', $period);

        if (empty($this->_data))
        {
            return null;
        }
 
        $this->_fetch_data();

        return (object) [
            'period' => $period,
            'update' => $this->_update,
            'data'   => $this->_data
        ];
    }

    /**
     * Returns ready-made sensor data
     * @throws \Exception
     */
    protected function _fetch_data()
    {
        if (empty($this->_data)) return ;

        $count = 0;
        $temp  = [];

        foreach ($this->_data as $key => $item)
        {

            $item->item_raw_data = $this->_insert_additional_data($item->item_raw_data);

            if ($key === 0)
            {
                $this->_updated = $item->item_timestamp;
                $temp = $this->_make_initial_data(json_decode($item->item_raw_data));

                continue;
            }

            $_time_a = new \DateTime($this->_updated);
            $_time_b = new \DateTime($item->item_timestamp);
            $_avg_en = $_time_a->getTimestamp() - $_time_b->getTimestamp() <= 3600;

            if ($_avg_en) $count++;

            foreach (json_decode($item->item_raw_data) as $sensorKey => $sensorVal)
            {
                $temp[$sensorKey]->min = $sensorVal < $temp[$sensorKey]->min ? $sensorVal : $temp[$sensorKey]->min;
                $temp[$sensorKey]->max = $sensorVal > $temp[$sensorKey]->max ? $sensorVal : $temp[$sensorKey]->max;

                if ($_avg_en)
                {
                    $temp[$sensorKey]->trend += $sensorVal;
                }

                if (end($this->_data) === $item)
                {
                    $temp[$sensorKey]->trend = round($temp[$sensorKey]->value - ($temp[$sensorKey]->trend / $count), 1);
                }
            }
        }

        $this->_data = $temp;
    }
}