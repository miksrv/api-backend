<?php

namespace App\Libraries;

/**
 * Class OpenWeather
 */

class OpenWeather {
    // OpenWeather API URL
    const API_URL = 'http://api.openweathermap.org/data/2.5/forecast';

    // OpenWeatherMap result cache time in sec
    const CACHE_TIME = 600;

    /**
     * Weather forecast from OpenWeatherMap service
     * @return object
     */
    public function get_forecast(): object
    {
        if ( ! $foreacst = cache('forecast'))
        {
            $client   = \Config\Services::curlrequest();
            $api_url  = self::API_URL . '?id=' . getenv('app.openweather.city') . '&appid=' . getenv('app.openweather.key') . '&units=metric&lang=ru';
            $response = $client->get($api_url);

            if ($response->getStatusCode() !== 200)
            {
                log_message('error', '[' .  __METHOD__ . '] Data error: ' . $response->getBody());

                return (object) [
                    'status' => false,
                    'code'   => $response->getStatusCode(),
                    'data'   => $response->getBody()
                ];
            }

            $foreacst = $response->getBody();

            cache()->save('foreacst', $foreacst, self::CACHE_TIME);
        }

        return (object) [
            'status' => true,
            'data'   => json_decode($foreacst)->list
        ];
    }
}