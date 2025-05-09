<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Exception\JsonException;

class OpenMeteoService
{
    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function fetchWeather(float $lat, float $lon, string $date): ?array
    {
        $url = "https://api.open-meteo.com/v1/forecast?latitude=$lat&longitude=$lon&hourly=temperature_2m,precipitation,wind_speed_10m&start_date=$date&end_date=$date&timezone=auto&current_weather=true";
        
        try {
            $response = $this->client->request('GET', $url);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            return $response->toArray();
        } catch (JsonException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function geocodeCity(string $city, ?string $country = null): ?array
    {
        $query = $city;
        if ($country) {
            $query .= ', ' . $country;
        }
        
        $url = "https://geocoding-api.open-meteo.com/v1/search?" . http_build_query([
            'name' => $query,
            'count' => 1,
            'language' => 'en',
            'format' => 'json'
        ]);

        try {
            $response = $this->client->request('GET', $url);
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            
            $data = $response->toArray();
            if (!isset($data['results'][0])) {
                return null;
            }

            $result = $data['results'][0];
            return [
                'latitude' => $result['latitude'],
                'longitude' => $result['longitude'],
                'country' => $result['country_code']
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}