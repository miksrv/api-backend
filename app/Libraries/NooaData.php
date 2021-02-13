<?php

/**
 * nooa.gov library
 */

class NooaData {
    // OpenWeather API URL
    const API_URL = 'https://services.swpc.noaa.gov/json/planetary_k_index_1m.json';

    // OpenWeatherMap result cache time in sec
    const CACHE_TIME = 600;

    /**
     * Weather forecast from OpenWeatherMap service
     */
    public function get_kindex()
    {

//        if ( ! $kindex = cache('kindex'))
//        {
            $client   = \Config\Services::curlrequest();
            $request = $client->get(self::API_URL);

            if ($request->getStatusCode() !== 200)
            {
                log_message('error', '[' .  __METHOD__ . '] Data error: ' . $request->data);

                return (object) [
                    'status' => false,
                    'code'   => $request->getStatusCode(),
                    'data'   => $request->getBody()
                ];
            }

            $kindex = $request->getBody();

            echo '<pre>';
            var_dump($request);
            exit();

//            cache()->save('kindex', $kindex, self::CACHE_TIME);
//        }

        return (object) [
            'status' => true,
            'data'   => json_decode($kindex)
        ];
    }
}