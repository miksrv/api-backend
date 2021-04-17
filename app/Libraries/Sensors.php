<?php

class Sensors {

    protected $_data;
    protected $_source;
    protected $_periods = ['today', 'yesterday', 'week', 'month'];
    protected $_dataModel;

    protected $dataset = [];
    protected $update;
    protected $period;
    protected $date;
    protected $range;
    protected $dewpoint;

    /**
     * Sensors constructor.
     * param['dataset'] => ['t', 'h', 'p' ...]
     * param['period'] => ['today', 'yesterday', 'week', 'month']
     * param['dewpoint'] => ['t' => ..., 'h' => ...]
     * @param array $param
     */
    function __construct($param = [])
    {
        helper(['transform', 'calculate']);

        $this->_dataModel = model('App\Models\SensorData');
        $this->_source    = isset($param['source']) ? $param['source'] : 'meteo';

        $this->dataset  = (isset($param['dataset']) && is_array($param['dataset'])) ? $param['dataset'] : [];
        $this->period   = (isset($param['period']) && in_array($param['period'], $this->_periods)) ? $param['period'] : $this->_periods[0];
        $this->dewpoint = (isset($param['dewpoint']['t']) && isset($param['dewpoint']['h'])) ? $param['dewpoint'] : null;

        // NEW period
        $this->date  = (isset($param['date']) ? $param['date'] : null);
        $this->range = (isset($param['daterange']) ? $param['daterange'] : null);
        
        if ( ! $this->range) $this->range = (object) [
            'start' => date('Y-m-d', strtotime(date('Y-m-d') . '-1 days')),
            'end'   => date('Y-m-d')
        ];
    }

    function set_range($start, $end)
    {
        $this->range = (object) ['start' => $start, 'end' => $end];
    }

    function set_date($date)
    {
        $this->date = $date;
    }

    function archive(): object
    {
        $this->_fetchData();

        if (empty($this->_data)) return (object) [];

        $_dataTmp = $result = [];

        foreach ($this->_data as $row)
        {
            $_date   = date('d.m.Y', strtotime($row->item_timestamp));
            $_sensor = json_decode($row->item_raw_data);

            if (! isset($_dataTmp[$_date]['count'])) {
                $_dataTmp[$_date]['count'] = 1;
            } else {
                $_dataTmp[$_date]['count'] += 1;
            }

            foreach ($_sensor as $key => $val)
            {
                if (! isset($_dataTmp[$_date][$key]))
                {
                    $_dataTmp[$_date][$key] = $val;
                }
                else
                {
                    $_dataTmp[$_date][$key] += $val;
                }
            }
        }

        foreach ($_dataTmp as $key => $sensors)
        {
            foreach ($sensors as $id => $val)
            {
                if ($key == 'count') continue;

                $result[$key][$id] = round($val / $_dataTmp[$key]['count'], 1);
            }

            unset($result[$key]['count']);
        }

        unset($_dataTmp);

        return (object) [
            'date_start' => (isset($this->range->start) ? $this->range->start : null),
            'date_end'   => (isset($this->range->end) ? $this->range->end : null),
            'data'       => $result
        ];
    }

    /**
     * Array of objects for each sensor in the table
     * Current value, change per hour, max and minimum value for 24 hours
     * @return object|null
     */
    function summary(): ?object
    {
        $this->_fetchData();

        if (empty($this->_data)) return (object) [];
 
        $this->_make_summary_data();

        return (object) [
            'period' => $this->period,
            'update' => strtotime($this->update),
            'date'   => $this->date,
            'data'   => $this->_data
        ];
    }

    function statistic(): ?object
    {
        $this->_fetchData();

        if (empty($this->_data)) return (object) [];

        $this->_make_graph_data($this->period); // DEPRECATED PERIOD

        return (object) [
            'period' => (object) [
                'start' => $this->range->start,
                'end'   => $this->range->end
            ],
            'update' => strtotime($this->update),
            'date'   => $this->date,
            'data'   => $this->_data
        ];
    }

    protected function _make_summary_data()
    {
        if (empty($this->_data)) return ;

        $count = 0;
        $temp  = [];

        foreach ($this->_data as $key => $item)
        {
            $trendTime = 60 * 60; // Calculation of average indicators for this time
            $item->item_raw_data = $this->_insert_additional_data($item->item_raw_data);

            if ($key === 0)
            {
                $this->update = $item->item_timestamp;
                $temp = $this->_make_initial_data($item->item_raw_data);

                continue;
            }

            try
            {
                $_time_a = new DateTime($this->update);
                $_time_b = new DateTime($item->item_timestamp);
            } catch (Exception $e) {
                $_time_a = $_time_b = null;
            }

            $_averageCalc = $_time_a->getTimestamp() - $_time_b->getTimestamp() <= $trendTime;

            if ($_averageCalc) $count++;

            foreach ($item->item_raw_data as $sensorKey => $sensorVal)
            {
                if ($sensorVal < $temp[$sensorKey]->min) $temp[$sensorKey]->min = $sensorVal;
                if ($sensorVal > $temp[$sensorKey]->max) $temp[$sensorKey]->max = $sensorVal;
                if ($_averageCalc) $temp[$sensorKey]->trend += $sensorVal;

                if (end($this->_data) === $item)
                    $temp[$sensorKey]->trend = round($temp[$sensorKey]->value - ($temp[$sensorKey]->trend / $count), 1);
            }
        }

        $this->_data = $temp;
    }
    
    
    /**
     *
// '%y Year %m Month %d Day %h Hours %i Minute %s Seconds'        =>  1 Year 3 Month 14 Day 11 Hours 49 Minute 36 Seconds
// '%y Year %m Month %d Day'                                    =>  1 Year 3 Month 14 Days
// '%m Month %d Day'                                            =>  3 Month 14 Day
// '%d Day %h Hours'                                            =>  14 Day 11 Hours
// '%d Day'                                                        =>  14 Days
// '%h Hours %i Minute %s Seconds'                                =>  11 Hours 49 Minute 36 Seconds
// '%i Minute %s Seconds'                                        =>  49 Minute 36 Seconds
// '%h Hours                                                    =>  11 Hours
// '%a Days                                                        =>  468 Days
     */
    private function _get_period($differenceFormat = '%a') {
        $datetime1 = date_create($this->range->start);
        $datetime2 = date_create($this->range->end);
       
        $interval = date_diff($datetime1, $datetime2);
       
        return (int) $interval->format($differenceFormat);
    }
    
    private function _get_period_сoefficient() {
        $period = $this->_get_period();

        if ($period === 0) return 8*60;
        if ($period >= 6 && $period <= 7) return 60*60;
        if ($period >= 8) return 300*60;
        
        return 10*60;
    } 

    /**
     * Make and return graph data array
     * @param $period string (today|yesterday|week|month)
     * @return array|void
     */
    protected function _make_graph_data(string $period): array
    {
        $_counter   = 0; // Iteration counter
        $_prev_time = 0; // First iteration timestamp
        $_result   = [];
        $_temp_val = []; // Array of average values
        $_temp_wd  = [0, 0, 0, 0, 0, 0, 0, 0]; // Array of wind directions (8 bit)
        $_temp_wr  = create_wind_rose_array(); // Wind rose array
        $_temp_wr_total = 0; // Wind rose count items

        // DEPRECATED
        // switch ($period) {
        //     case 'today'     :
        //     case 'yesterday' : $period = '600'; break;
        //     case 'week'      : $period = '3600'; break;
        //     case 'month'     : $period = '18000'; break;
        // }
        
        $period = $this->_get_period_сoefficient();

        foreach ($this->_data as $num => $item)
        {
            $item->item_raw_data = $this->_insert_additional_data($item->item_raw_data);

            if ($num === 0) $this->update = $item->item_timestamp;

            // Calculate average values, reset timer and counter for next iteration
            if ($_prev_time - strtotime($item->item_timestamp) >= $period)
            {
                foreach ($_temp_val as $_key => $_val)
                {
                    // Average timestamp not use; for chart 'wind direct' timestamp is not use
                    if ($_key === 'timestamp') continue;
                    if ($_key === 'wd') continue;

                    $_result[$_key][] = [
                        round($_temp_val['timestamp'] / $_counter) * 1000,
                        round($_val / $_counter, 1)];
                }

                $_counter   = 0;
                $_prev_time = 0;
                $_temp_val  = [];
            }

            if ($_counter == 0) $_prev_time = strtotime($item->item_timestamp);

            foreach ($item->item_raw_data as $key => $val)
            {
                // if key not in request data set array, then continue
                //if ( ! in_array($key, $this->dataset)) continue;

                if ( ! isset($_temp_val[$key])) $_temp_val[$key] = 0;

                // if key is wind direction
                if ($key === 'wd')
                {
                    $_tmp_wind_position = convert_degree_to_direct($val);

                    // if current wind speed > 0
                    if ($item->item_raw_data->ws > 0)
                    {
                        $_temp_wd[$_tmp_wind_position]++;
                        $_temp_wr[convert_wind_speed($item->item_raw_data->ws)][$_tmp_wind_position]++;
                        $_temp_wr_total++;
                    }
                }

                $_temp_val[$key] += $val;
            }

            if ( ! isset($_temp_val['timestamp'])) $_temp_val['timestamp'] = 0;

            $_temp_val['timestamp'] += strtotime($item->item_timestamp);
            $_counter++;
        }

        if (in_array('wd', $this->dataset))
            $_result = $this->_insert_wind_direction($_temp_wd, $_temp_wr, $_temp_wr_total, $_result);

        return $this->_data = $_result;
    }

    /**
     * Creates an initial array of sensor data
     * @param $sensor_array
     * @return array
     */
    protected function _make_initial_data($sensor_array): array
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
                $_tmp[$key]->info = convert_degree_to_name($val);
        }

        return $_tmp;
    }

    /**
     * JSON decode, return object
     * Inserted some data in first sensors array elements
     * @param $raw_input string
     * @return object
     */
    protected function _insert_additional_data(string $raw_input): object
    {
        $_tmp = json_decode($raw_input);

        if ($this->dewpoint)
            $_tmp->dp = calculate_dew_point($_tmp->{$this->dewpoint['h']}, $_tmp->{$this->dewpoint['t']});

        if (isset($_tmp->wd))
            $_tmp->wd = convert_anemometr_data((int) $_tmp->wd);

        return $_tmp;
    }

    // Определяем частоту, с какого направления дует ветер
    protected function _insert_wind_direction($_temp_wd, $_temp_wr, $_temp_wr_total, $_result)
    {
        $_tmp = $_temp_wd;

        sort($_tmp);

        foreach ($_temp_wd as $key => $val)
            $_result['wd'][$key] = array_search($val, $_tmp);

        $_result['wr'] = calculate_wind_rose($_temp_wr, $_temp_wr_total);

        return $_result;
    }

    private function _fetchData()
    {
        if ($this->_get_period() > 30) {
            $this->range->start = date('Y-m-d', strtotime(date('Y-m-d') . '-1 days'));
            $this->range->end   = date('Y-m-d');
        }
        
        $this->_data = $this->_dataModel->get_period($this->_source, $this->period, $this->date, $this->range);
    }
}