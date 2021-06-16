<?php

namespace App\Libraries;

class Sensors {

    protected $_data;
    protected $_source;
    protected $_dataModel;

    protected $dataset = [];
    protected $update;
    protected $period;
    protected $range;
    protected $dewpoint;

    private $_default_start;
    private $_default_end;

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
        $this->dewpoint = (isset($param['dewpoint']['t']) && isset($param['dewpoint']['h'])) ? $param['dewpoint'] : null;

        $this->_default_start = date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s') . '-12 hours'));
        $this->_default_end   = date('Y-m-d H:i:s');

        //$this->range = (isset($param['daterange']) ? $param['daterange'] : null);

        if (isset($param['daterange']) && is_object($param['daterange']))
        {
            $this->_get_range($param['daterange']);
        }

        if ( ! $this->range) $this->range = (object) [
            'start' => $this->_default_start,
            'end'   => $this->_default_end,
        ];
    }

    /**
     * Remove old entites
     * @return mixed
     */
    function clear_old_entries()
    {
        return $this->_dataModel->clear_old_entries();
    }

    /**
     * Set date range for WHERE
     * @param $start
     * @param $end
     */
    function set_range($start, $end)
    {
        $this->_get_range((object) ['start' => $start, 'end' => $end]);
    }

    /**
     * Set dataset (sensor names for SELECT)
     * @param $dataset
     */
    function set_dataset($dataset)
    {
        $this->dataset = (! empty($dataset) && is_array($dataset)) ? $dataset : [];
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
        return $this->_response();
    }

    function statistic(): ?object
    {
        $this->_fetchData();

        if ( ! empty($this->_data)) $this->_make_graph_data();

        return $this->_response();
    }

    function csv(): ?array
    {
        $this->_fetchData();

        return $this->_make_csv_data();
    }
    
    protected function _response(): object
    {
        return (object) [
            'period' => (object) [
                'start' => $this->range->start,
                'end'   => $this->range->end
            ],
            'update' => strtotime($this->update),
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
                $_time_a = new \DateTime($this->update);
                $_time_b = new \DateTime($item->item_timestamp);
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
    
    protected function _get_range($range)
    {
        #todo Пофиксить баг с отсчетом 12 часов от текущего времени
        $this->range = (object) [
            'start' => ($range->start === $range->end) ? date('Y-m-d H:i:s', strtotime($range->start . '-12 hours')) 
                                                       : date('Y-m-d 00:00:00', strtotime($range->start)),
            'end'   => date('Y-m-d ' . ($range->end < date('Y-m-d') ? '23:59:59' : date('H:i:s')), strtotime($range->end)),
        ];
        
        // echo '<pre>';
        // var_dump($range);
        // var_dump($this->range);
        // exit();
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

    protected function _make_csv_data(): array
    {
        $_headers = ['date'];
        $_result  = [];

        foreach ($this->_data as $num => $item)
        {
            $sensorObject = json_decode($item->item_raw_data);
            $_tmp_result  = [$item->item_timestamp];

            foreach ($sensorObject as $name => $sensor) {
                if ($num === 0) $_headers[] = $name;
                $_tmp_result[] = $sensor;
            }

            if ($num === 0)
            {
                $_result[] = $_headers;
                unset($_headers);
            }

            $_result[] = $_tmp_result;

            unset($_tmp_result);
        }

        return $_result;
    }

    /**
     * Make and return graph data array
     * @param $period string (today|yesterday|week|month)
     * @return array|void
     */
    protected function _make_graph_data(): array
    {
        $_counter   = 0; // Iteration counter
        $_prev_time = 0; // First iteration timestamp
        $_result   = [];
        $_temp_val = []; // Array of average values
        $_temp_wd  = [0, 0, 0, 0, 0, 0, 0, 0]; // Array of wind directions (8 bit)
        $_temp_wr  = create_wind_rose_array(); // Wind rose array
        $_temp_wr_total = 0; // Wind rose count items
        
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
                if ( ! empty($this->dataset) && ! in_array($key, $this->dataset)) continue;
                if ( ! isset($_temp_val[$key])) $_temp_val[$key] = 0;
                if ($key === 'uv' && $val <= 0.3) $val = 0;
                if ($key === 'p1' || $key === 'p2' || $key === 'p3') $val = $val / 1000;

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
            $this->range->start = $this->_default_start;
            $this->range->end   = $this->_default_end;
        }
        
        $this->_data = $this->_dataModel->get_period($this->_source, $this->range);
    }
}