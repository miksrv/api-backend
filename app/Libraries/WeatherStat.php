<?php

namespace App\Libraries;

#TODO: DEPRECATED

class WeatherStat {

    protected $_data;
    protected $_update;
    protected $_period  = ['today', 'yesterday', 'week', 'month'];
    protected $_dataset = [];

    public function month_event($month, $year)
    {
        $dataModel = model('App\Models\SensorData');

        return $dataModel->get_month($month, $year);
    }

    function get_statistic($param)
    {
        if ( ! isset($param['dataset']) || empty($param['dataset']) || ! is_array($param['dataset']))
        {
            return null;
        }

        helper(['transform', 'calculate']);

        $dataModel = model('App\Models\SensorData');
        $period    = ( ! isset($param['period']) || ! in_array($param['period'], $this->_period)) ? $this->_period[0] : $param['period'];

        $this->_dataset = $param['dataset'];
        $this->_data    = $dataModel->get_period('meteo', $period);

        if (empty($this->_data))
        {
            return null;
        }

        $this->_make_graph_data($period);

        return (object) [
            'period' => $period,
            'update' => $this->_update,
            'data'   => $this->_data
        ];
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
            $item->item_raw_data = $this->_insert_additional_data($item->item_raw_data);

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
                if ( ! in_array($key, $this->_dataset)) continue;

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

        if (in_array('wd', $this->_dataset))
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