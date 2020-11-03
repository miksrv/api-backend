<?php

class Sensors {

    protected $_data;
    protected $_source;
    protected $_periods = ['today', 'yesterday', 'week', 'month'];
    protected $_dataModel;

    protected $dataset = [];
    protected $update;
    protected $period;

    function __construct($param = [])
    {
        helper(['transform', 'calculate']);

        $this->_dataModel = $dataModel = model('App\Models\SensorData');
        $this->_source    = isset($param['source']) ? $param['source'] : 'meteo';

        $this->dataset = (isset($param['dataset']) && is_array($param['dataset'])) ? $param['dataset'] : [];
        $this->period  = ( ! isset($param['period']) || ! in_array($param['period'], $this->_periods)) ? $this->_periods[0] : $param['period'];

        $this->_data = $this->_dataModel->get_period($this->_source, $this->period);
    }

    function summary()
    {
        if (empty($this->_data))
        {
            return null;
        }
 
        $this->_fetch_data();

        return (object) [
            'period' => $this->period,
            'update' => $this->update,
            'data'   => $this->_data
        ];
    }

    function statistic()
    {
        if (empty($this->_data))
        {
            return null;
        }

        $this->_make_graph_data($this->period);

        return (object) [
            'period' => $this->period,
            'update' => $this->update,
            'data'   => $this->_data
        ];
    }

    protected function _fetch_data()
    {
        if (empty($this->_data)) return ;

        $count = 0;
        $temp  = [];

        foreach ($this->_data as $key => $item)
        {

            //$item->item_raw_data = $this->_insert_additional_data($item->item_raw_data);

            if ($key === 0)
            {
                $this->update = $item->item_timestamp;
                $temp = $this->_make_initial_data(json_decode($item->item_raw_data));

                continue;
            }

            $_time_a = new \DateTime($this->update);
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

    /**
     * Make and return graph data array
     * @return array|void
     */
    protected function _make_graph_data($period)
    {
        $_result = [];

        $_counter   = 0; // Iteration counter
        $_prev_time = 0; // First iteration timestamp
        $_temp_val  = []; // Array of average values
        $_temp_wd   = [0, 0, 0, 0, 0, 0, 0, 0]; // Array of wind directions (8 bit)

        $_temp_wr       = []; // Wind rose array
        $_temp_wr_total = 0; // Wind rose count items

        // Заполняем пустыми значениями
        // Скорость
        for ($i = 0; $i <= 6; $i++)
        {
            // Направление
            for ($k = 0; $k <= 7; $k++)
            {
                $_temp_wr[$i][$k] = 0;
            }
        }

        switch ($period) {
            case 'today'     :
            case 'yesterday' : $period = '600'; break;
            case 'week'      : $period = '3600'; break;
            case 'month'     : $period = '18000'; break;
        }

        foreach ($this->_data as $num => $item)
        {
            //$item->item_raw_data = $this->_insert_additional_data($item->item_raw_data);

            if ($num === 0) $this->_update = $item->item_timestamp;

            // Calculate average values, reset timer and counter for next iteration
            if ($_prev_time - strtotime($item->item_timestamp) >= $period)
            {
                foreach ($_temp_val as $_key => $_val)
                {
                    // Average timestamp not use; for chart 'wind direct' timestamp is not use
                    if ($_key === 'timestamp') continue;
                    if ($_key === 'wd') continue;

                    $_result[$_key][] = [
                        round($_temp_val['timestamp'] / $_counter, 0) * 1000,
                        round($_val / $_counter, 1)];
                }

                $_counter   = 0;
                $_prev_time = 0;
                $_temp_val  = [];
            }

            if ($_counter == 0) $_prev_time = strtotime($item->item_timestamp);

            $_json_data = json_decode($item->item_raw_data);

            foreach ($_json_data as $key => $val)
            {
                // if key not in request data set array, then continue
                //if ( ! in_array($key, $this->dataset)) continue;

                // if key value is not exist - create it
                if ( ! isset($_temp_val[$key])) $_temp_val[$key] = 0;

                // if key is wind direction
                if ($key === 'wd')
                {
                    $_tmp_wind_position = convert_degree_to_direct($val);

                    // if current wind speed > 0
                    if ($_json_data->ws > 0) {
                        $_temp_wd[$_tmp_wind_position]++;

                        $_temp_wr[convert_wind_speed($_json_data->ws)][$_tmp_wind_position]++;
                        $_temp_wr_total++;
                    }

                    continue;
                }

                $_temp_val[$key] += $val;
            }

            if ( ! isset($_temp_val['timestamp'])) $_temp_val['timestamp'] = 0;

            $_temp_val['timestamp'] += strtotime($item->item_timestamp);
            $_counter++;
        }

        if (in_array('wd', $this->dataset))
        {
            $tmp = $_temp_wd;
            $wind_dir = [];

            sort($tmp);

            foreach ($_temp_wd as $key => $val)
            {
                $wind_dir[$key] = array_search($val, $tmp);
            }

            $_result['wd'] = $wind_dir;
            $_result['wr'] = calculate_wind_rose($_temp_wr, $_temp_wr_total);
        }

        return $this->_data = $_result;
    }

    /**
     * Creates an initial array of sensor data
     * @param $sensor_array
     * @return array|void
     */
    protected function _make_initial_data($sensor_array)
    {
        $_tmp = [];

        foreach ($sensor_array as $key => $val)
        {
            $_tmp[$key] = (object) [
                'value' => $val,
                'trend' => 0,
                'max'   => $val,
                'min'   => $val
            ];

            if ($key == 'wd')
            {
                $_tmp[$key]->info = convert_degree_to_name($val);
            }
        }

        return $_tmp;
    }

    /**
     * Inserted some data in first sensors array elements
     * @param $raw_input
     * @return false|string
     */
    protected function _insert_additional_data($raw_input)
    {
        $_tmp = json_decode($raw_input);
        $_tmp->dp = calculate_dew_point($_tmp->h, $_tmp->t2);
        $_tmp->wd = convert_anemometr_data((int) $_tmp->wd);
        $_tmp->uv = $_tmp->uv < 0 ? 0 : $_tmp->uv;

        return json_encode($_tmp);
    }
}