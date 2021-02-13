<?php namespace App\Controllers;

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, OPTIONS");

class Get extends BaseController
{

    protected $_data;
    protected $_updated;
    protected $_period = ['today', 'yesterday', 'week', 'month'];

    /**
     * DEPRECATED
     * Receives data from a weather station, checks a token, enters data into a storage
     */
    public function general()
    {
        helper(['transform', 'calculate']);

        $dataModel = model('App\Models\SensorData');

        $this->_data = $dataModel->get_period();
        $this->_fetch_data();

        $this->response
            ->setJSON([
                'update'  => strtotime($this->_updated),
                'moon'    => $this->_moon(),
                'sun'     => $this->_sun(),
                'sensors' => $this->_data
            ])->send();

        exit();
    }

    /**
     * Return current meteo params + trend
     */
    public function meteo_general()
    {
        helper(['transform', 'calculate']);

        $param = [
            'dataset' => ( ! empty($this->request->getGet('dataset')) ? explode(',', $this->request->getGet('dataset')) : ['t1']),
        ];

        $WeatherGeneral = new \WeatherGeneral();
        $generalData    = $WeatherGeneral->get_general($param);

        $this->response
            ->setJSON([
                'period'  => $generalData->period,
                'update'  => strtotime($generalData->update),
                'sensors' => $generalData->data
            ])->send();

        exit();
    }

    public function month_event() {
        $month = date('m');
        $year  = date('Y');
        $date  = $this->request->getGet('date');
        if ( ! empty($date))
        {
            $date  = strtotime($date);
            if (checkdate(date('m', $date), date('d', $date), date('Y', $date)))
            {
                $month = date('m', $date);
                $year  = date('Y', $date);
            }
        }

        $_cache_name = 'month_event_' . $month . '.' . $year;

        if ( ! $result = cache($_cache_name))
        {
            $WeatherStat = new \WeatherStat();
            $statistic   = $WeatherStat->month_event($month, $year);

            if (empty($statistic))
            {
                $this->response->setJSON(['status'  => false])->send();
                exit();
            }

            $_dataTmp = $result = [];

            foreach ($statistic as $key => $row)
            {
                $_date = date('d', strtotime($row->item_timestamp));
                $_tmp  = json_decode($row->item_raw_data);

                if (! isset($_dataTmp[$_date]))
                {
                    $_dataTmp[$_date] = [
                        't' => $_tmp->t2,
                        'h' => $_tmp->h,
                        'p' => $_tmp->p,
                        'w' => $_tmp->ws,
                        'count' => 1
                    ];
                }
                else
                {
                    $_dataTmp[$_date]['t'] += $_tmp->t2;
                    $_dataTmp[$_date]['h'] += $_tmp->h;
                    $_dataTmp[$_date]['p'] += $_tmp->p;
                    $_dataTmp[$_date]['w'] += $_tmp->ws;
                    $_dataTmp[$_date]['count'] += 1;
                }
            }

            foreach ($_dataTmp as $day => $row)
            {
                $_t = round($row['t'] / $row['count'], 1);
                $_h = round($row['h'] / $row['count'], 1);
                $_p = round($row['p'] / $row['count'], 0);
                $_w = round($row['w'] / $row['count'], 1);

                $date     = $day . '.' . $month . '.' . $year;
                $result[] = [
                    'id'    => 100 + $day,
                    'title' => 'Т: ' . $_t . ', H: ' . $_h . ', P: ' . $_p . ', W: ' . $_w,
                    'start' => $date,
                    'end'   => $date,
                    'type'  => 'meteo'
                ];
            }

            $result = json_encode($result);

            cache()->save($_cache_name, $result, 600);
        }

        $this->response
            ->setJSON([
                'status' => true,
                'data'   => json_decode($result)
            ])->send();

        exit();
    }

    /**
     * Weather forecast from OpenWeatherMap service
     */
    public function statistic()
    {
        $param = [
            'dataset' => ( ! empty($this->request->getGet('dataset')) ? explode(',', $this->request->getGet('dataset')) : ['t1']),
            'period'  => $this->request->getGet('period')
        ];

        $WeatherStat = new \WeatherStat();
        $statistic   = $WeatherStat->get_statistic($param);

        $this->response
            ->setJSON([
                'period'  => $statistic->period,
                'update'  => strtotime($statistic->update),
                'sensors' => $statistic->data
            ])->send();

        exit();
    }

    /**
     * Weather forecast from OpenWeatherMap service
     */
    public function forecast()
    {
        $OpenWeather = new \OpenWeather();
        $foreacst    = $OpenWeather->get_forecast();

        if ($foreacst->status === true)
        {
            $this->response
                ->setJSON([
                    'data' => $foreacst->data,
                ])->send();
        }

        exit();
    }


    /**
     * Getting data from the NASA NOAA
     */
    public function kindex()
    {
        $NooaData = new \NooaData();
        $kindex   = $NooaData->get_kindex();

        if ($kindex->status === true)
        {
            $this->response
                ->setJSON([
                    'data' => $kindex->data,
                ])->send();
        }

        exit();
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

    /**
     * Returns the calculated time of sunset and sunrise
     * @return object
     */
     protected function _sun() {
        return (object) [
            'rise' => date_sunrise(
                time(),
                SUNFUNCS_RET_TIMESTAMP,
                getenv('app.latitude'),
                getenv('app.longitude'),
                90,
                getenv('app.timezone')
            ),
            'set' => date_sunset(
                time(),
                SUNFUNCS_RET_TIMESTAMP,
                getenv('app.latitude'),
                getenv('app.longitude'),
                90,
                getenv('app.timezone')
            ),
            'info' => date_sun_info(
                time(),
                getenv('app.latitude'),
                getenv('app.longitude')
            )
        ];
    }

    /**
     * Returns lunar data - sunset, dawn, age, phase, illumination, distance
     * @return object
     */
    protected function _moon()
    {
        $MoonCalc = new \MoonCalc();
        $MoonTime = \MoonTime::calculateMoonTimes(
            date("m"), date("d"), date("Y"),
            getenv('app.latitude'),
            getenv('app.longitude')
        );

        return (object) [
            'rise'         => $MoonTime->moonrise,
            'set'          => $MoonTime->moonset,
            'phrase'       => $MoonCalc->phase(),
            'age'          => $MoonCalc->age(),
            'diameter'     => $MoonCalc->diameter(),
            'distance'     => $MoonCalc->distance(),
            'illumination' => $MoonCalc->illumination(),
            'phase_name'   => $MoonCalc->phase_name(),
            'phase_icon'   => $MoonCalc->phase_name_icon(),
            'phase_new'    => round($MoonCalc->next_new_moon(), 0),
            'phase_full'   => round($MoonCalc->next_full_moon(), 0)
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
                if ($sensorKey == 'ma' || $sensorKey == 'mo') continue;

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
     * Creates an initial array of sensor data
     * @param $sensor_array
     * @return array|void
     */
    protected function _make_initial_data($sensor_array)
    {
        if (empty($sensor_array) || ! is_object($sensor_array)) return ;

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
}
