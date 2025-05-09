<?php

namespace App\Tests\Service;

use App\Service\OpenMeteoService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OpenMeteoServiceTest extends TestCase
{
    private $httpClient;
    private $service;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->service = new OpenMeteoService($this->httpClient);
    }

    public function testGeocodeCitySuccess(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'results' => [
                [
                    'name' => 'London',
                    'latitude' => 51.5074,
                    'longitude' => -0.1278,
                    'country_code' => 'GB'
                ]
            ]
        ]));

        $this->httpClient->setResponseFactory($mockResponse);

        $result = $this->service->geocodeCity('London', 'GB');

        $this->assertIsArray($result);
        $this->assertEquals(51.5074, $result['latitude']);
        $this->assertEquals(-0.1278, $result['longitude']);
        $this->assertEquals('GB', $result['country']);
    }

    public function testGeocodeCityWithCountry(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'results' => [
                [
                    'name' => 'Paris',
                    'latitude' => 48.8566,
                    'longitude' => 2.3522,
                    'country_code' => 'FR'
                ]
            ]
        ]));

        $this->httpClient->setResponseFactory($mockResponse);

        $result = $this->service->geocodeCity('Paris', 'FR');

        $this->assertIsArray($result);
        $this->assertEquals(48.8566, $result['latitude']);
        $this->assertEquals(2.3522, $result['longitude']);
        $this->assertEquals('FR', $result['country']);
    }

    public function testGeocodeCityNoResults(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'results' => []
        ]));

        $this->httpClient->setResponseFactory($mockResponse);

        $result = $this->service->geocodeCity('NonexistentCity', 'XX');

        $this->assertNull($result);
    }

    public function testGeocodeCityInvalidResponse(): void
    {
        $mockResponse = new MockResponse('invalid json');

        $this->httpClient->setResponseFactory($mockResponse);

        $result = $this->service->geocodeCity('London', 'GB');

        $this->assertNull($result);
    }

    public function testFetchWeatherSuccess(): void
    {
        // Mock successful weather response
        $mockResponse = new MockResponse(json_encode([
            'hourly' => [
                'time' => ['2024-01-01T12:00:00'],
                'temperature_2m' => [20.0],
                'precipitation' => [0.0],
                'wind_speed_10m' => [5.0]
            ],
            'current_weather' => [
                'temperature' => 20.0
            ]
        ]));

        $this->httpClient->setResponseFactory($mockResponse);

        $result = $this->service->fetchWeather(51.5074, -0.1278, '2024-01-01');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('hourly', $result);
        $this->assertArrayHasKey('current_weather', $result);
    }

    public function testFetchWeatherError(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 404]);

        $this->httpClient->setResponseFactory($mockResponse);

        $result = $this->service->fetchWeather(51.5074, -0.1278, '2024-01-01');

        $this->assertNull($result);
    }

    public function testFetchWeatherInvalidResponse(): void
    {
        $mockResponse = new MockResponse('invalid json');

        $this->httpClient->setResponseFactory($mockResponse);

        $result = $this->service->fetchWeather(51.5074, -0.1278, '2024-01-01');

        $this->assertNull($result);
    }
}
