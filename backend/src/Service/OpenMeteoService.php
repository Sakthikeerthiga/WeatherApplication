<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenMeteoService
{
    private $client;
    public function __construct(HttpClientInterface $client) { $this->client = $client; }

    public function fetchWeather(float $lat, float $lon, string $date): ?array
    {
        $url = "https://api.open-meteo.com/v1/forecast?latitude=$lat&longitude=$lon&hourly=temperature_2m,precipitation,wind_speed_10m&start_date=$date&end_date=$date&timezone=auto&current_weather=true";
        $response = $this->client->request('GET', $url);
        if ($response->getStatusCode() !== 200) return null;
        return $response->toArray();
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

        $response = file_get_contents($url);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['results'][0])) {
            return null;
        }

        $result = $data['results'][0];
        return [
            'latitude' => $result['latitude'],
            'longitude' => $result['longitude'],
            'country' => $result['country_code']
        ];
    }
}