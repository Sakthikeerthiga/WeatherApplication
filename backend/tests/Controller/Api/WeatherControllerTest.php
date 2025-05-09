<?php

namespace App\Tests\Controller\Api;

use App\Entity\WeatherEntry;
use App\Repository\WeatherEntryRepository;
use App\Service\OpenMeteoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class WeatherControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private $weatherEntryRepository;
    private $openMeteoService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->client = static::createClient(['environment' => 'test']);
        
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->weatherEntryRepository = $container->get(WeatherEntryRepository::class);
        
        $this->openMeteoService = $this->createMock(OpenMeteoService::class);
        
        $this->openMeteoService->method('geocodeCity')
            ->willReturn([
                'latitude' => 0.0,
                'longitude' => 0.0,
                'country' => 'US'
            ]);
            
        $this->openMeteoService->method('fetchWeather')
            ->willReturn([
                'temperature' => 20.0,
                'precipitation' => 0.0,
                'windSpeed' => 5.0,
                'sunrise' => '2025-05-09T07:00:00',
                'sunset' => '2025-05-09T17:00:00'
            ]);
            
        $container->set(OpenMeteoService::class, $this->openMeteoService);

        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }
        
        parent::tearDown();
    }

    private function createTestWeatherEntry(string $city, string $country, float $lat, float $lon): WeatherEntry
    {
        $now = new \DateTime();
        $entry = new WeatherEntry();
        $entry->setCity($city);
        $entry->setCountry($country);
        $entry->setLatitude($lat);
        $entry->setLongitude($lon);
        $entry->setDate($now);
        $entry->setTemperature(20.0);
        $entry->setPrecipitation(0.0);
        $entry->setWindSpeed(5.0);
        $entry->setUpdatedAt($now);
        $entry->setSunrise(new \DateTime('2025-05-09 07:00:00'));
        $entry->setSunset(new \DateTime('2025-05-09 17:00:00'));

        $this->entityManager->persist($entry);
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $entry;
    }

    public function testIndex(): void
    {
        $testDate = new \DateTime('2025-05-09 12:00:00');
        
        $entries = [
            ['London', 'GB', 51.5074, -0.1278, 18.5, 0.2, 4.5],
            ['Paris', 'FR', 48.8566, 2.3522, 20.0, 0.0, 3.2],
            ['Berlin', 'DE', 52.5200, 13.4050, 19.0, 0.1, 5.0],
            ['Madrid', 'ES', 40.4168, -3.7038, 25.0, 0.0, 2.8],
            ['Rome', 'IT', 41.9028, 12.4964, 22.0, 0.0, 3.5],
            ['Amsterdam', 'NL', 52.3676, 4.9041, 17.0, 0.3, 6.0],
            ['Vienna', 'AT', 48.2082, 16.3738, 21.0, 0.0, 2.5],
            ['Brussels', 'BE', 50.8503, 4.3517, 19.5, 0.1, 4.0]
        ];

        foreach ($entries as $entry) {
            $weatherEntry = new WeatherEntry();
            $weatherEntry->setCity($entry[0]);
            $weatherEntry->setCountry($entry[1]);
            $weatherEntry->setLatitude($entry[2]);
            $weatherEntry->setLongitude($entry[3]);
            $weatherEntry->setDate($testDate);
            $weatherEntry->setTemperature($entry[4]);
            $weatherEntry->setPrecipitation($entry[5]);
            $weatherEntry->setWindSpeed($entry[6]);
            $weatherEntry->setUpdatedAt(new \DateTime());
            $weatherEntry->setSunrise(new \DateTime('2025-05-09 07:00:00'));
            $weatherEntry->setSunset(new \DateTime('2025-05-09 17:00:00'));

            $this->entityManager->persist($weatherEntry);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $allEntries = $this->weatherEntryRepository->findAll();
        $this->assertCount(8, $allEntries, 'Should have 8 entries in the database');

        $this->client->request('GET', '/api/weather');
        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('total', $response);
        $this->assertArrayHasKey('page', $response);
        $this->assertArrayHasKey('perPage', $response);
        $this->assertNotEmpty($response['data'], 'Response data should not be empty');
        $this->assertEquals(8, $response['total'], 'Should have 8 total entries');

        $dateParam = $testDate->format('Y-m-d');
        $this->client->request('GET', '/api/weather?date=' . $dateParam);
        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
        
        if (empty($response['data'])) {
            $this->fail(sprintf(
                'Date filtered response is empty. Date param: %s, Full response: %s',
                $dateParam,
                json_encode($response, JSON_PRETTY_PRINT)
            ));
        }
        
        $this->assertNotEmpty($response['data'], 'Date filtered response should not be empty');
        $this->assertEquals(8, $response['total'], 'Should have 8 entries for the test date');

        $this->client->request('GET', '/api/weather?q=paris');
        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('data', $response);
        
        if (empty($response['data'])) {
            $this->fail(sprintf(
                'City filtered response is empty. Full response: %s',
                json_encode($response, JSON_PRETTY_PRINT)
            ));
        }
        
        $this->assertNotEmpty($response['data'], 'Filtered response data should not be empty');
        $this->assertEquals('London', $response['data'][0]['city']);

        $this->client->request('GET', '/api/weather?country=GB');
        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertNotEmpty($response['data'], 'Country filtered response should not be empty');
        $this->assertEquals('GB', $response['data'][0]['country']);
    }

    public function testShow(): void
    {
        $entry = $this->createTestWeatherEntry('Paris', 'FR', 48.8566, 2.3522);

        $this->client->request('GET', '/api/weather/' . $entry->getId());
        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('Paris', $response['city']);
        $this->assertEquals('FR', $response['country']);
        $this->assertEquals(20.0, $response['temperature']);
    }

    public function testCreate(): void
    {
        $now = new \DateTime();
        $data = [
            'city' => 'Berlin',
            'country' => 'DE',
            'latitude' => 52.5200,
            'longitude' => 13.4050,
            'date' => $now->format('Y-m-d\TH:i:s'),
            'temperature' => 18.0,
            'precipitation' => 0.0,
            'windSpeed' => 4.0,
            'sunrise' => (new \DateTime('2025-05-09 07:00:00'))->format('Y-m-d\TH:i:s'),
            'sunset' => (new \DateTime('2025-05-09 17:00:00'))->format('Y-m-d\TH:i:s')
        ];

        $this->client->request('POST', '/api/weather', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data));
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('id', $response);
        $this->assertEquals('created', $response['status']);
    }

    public function testUpdate(): void
    {
        $entry = $this->createTestWeatherEntry('Madrid', 'ES', 40.4168, -3.7038);

        $now = new \DateTime();
        $data = [
            'city' => 'Madrid',
            'country' => 'ES',
            'latitude' => 40.4168,
            'longitude' => -3.7038,
            'date' => $now->format('Y-m-d\TH:i:s'),
            'temperature' => 26.0,
            'precipitation' => 0.0,
            'windSpeed' => 3.0,
            'sunrise' => (new \DateTime('2025-05-09 07:00:00'))->format('Y-m-d\TH:i:s'),
            'sunset' => (new \DateTime('2025-05-09 17:00:00'))->format('Y-m-d\TH:i:s')
        ];

        $this->client->request('PUT', '/api/weather/' . $entry->getId(), [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data));
        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertEquals('updated', $response['status']);
    }

    public function testDelete(): void
    {
        $entry = $this->createTestWeatherEntry('Rome', 'IT', 41.9028, 12.4964);

        $this->client->request('DELETE', '/api/weather/' . $entry->getId());
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testFetchWeather(): void
    {
        $date = new \DateTime();
        
        $this->openMeteoService = $this->createMock(OpenMeteoService::class);
        static::getContainer()->set(OpenMeteoService::class, $this->openMeteoService);
        
        $this->openMeteoService->expects($this->once())
            ->method('geocodeCity')
            ->with('Tokyo', 'JP')
            ->willReturn([
                'latitude' => 35.6762,
                'longitude' => 139.6503,
                'country' => 'JP'
            ]);

        $this->openMeteoService->expects($this->once())
            ->method('fetchWeather')
            ->with(35.6762, 139.6503, $date)
            ->willReturn([
                'temperature' => 20.0,
                'precipitation' => 0.0,
                'windSpeed' => 5.0,
                'sunrise' => '2025-05-09T07:00:00',
                'sunset' => '2025-05-09T17:00:00'
            ]);

        $data = [
            'city' => 'Tokyo',
            'country' => 'JP',
            'date' => $date->format('Y-m-d')
        ];

        $this->client->request('POST', '/api/weather/fetch', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($data));
        
        // Assert response
        $this->assertResponseIsSuccessful();
        
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($response);
        $this->assertArrayHasKey('city', $response);
        $this->assertArrayHasKey('temperature', $response);
        $this->assertArrayHasKey('precipitation', $response);
        $this->assertArrayHasKey('windSpeed', $response);
        
        if (!isset($response['temperature'])) {
            $this->fail(sprintf(
                'Weather data not found in response. Full response: %s',
                json_encode($response, JSON_PRETTY_PRINT)
            ));
        }
    }

    // public function testLiveFetch(): void
    // {
    //     // Reset the container to avoid "already initialized" service error
    //     self::ensureKernelShutdown();
    //     $client = static::createClient();

    //     // Create and configure mock service
    //     $mockService = $this->createMock(OpenMeteoService::class);

    //     $mockService->expects($this->once())
    //         ->method('geocodeCity')
    //         ->with('New York', 'US')
    //         ->willReturn([
    //             'latitude' => 40.7128,
    //             'longitude' => -74.0060,
    //             'country' => 'US'
    //         ]);

    //     $mockService->expects($this->once())
    //         ->method('fetchWeather')
    //         ->with(40.7128, -74.0060, $this->isInstanceOf(\DateTimeInterface::class))
    //         ->willReturn([
    //             'temperature' => 22.0,
    //             'precipitation' => 0.1,
    //             'windSpeed' => 3.5,
    //             'sunrise' => '2025-05-09T06:00:00',
    //             'sunset' => '2025-05-09T20:00:00'
    //         ]);

    //     // Inject the mock into the container before service is used
    //     static::getContainer()->set(OpenMeteoService::class, $mockService);

    //     // Perform the request
    //     $client->request('GET', '/api/weather/live-fetch', [
    //         'query' => [
    //             'city' => 'New York',
    //             'country' => 'US',
    //             'date' => '2025-05-09'
    //         ]
    //     ]);

    //     // Assert response status and content
    //     $this->assertResponseIsSuccessful();

    //     $response = json_decode($client->getResponse()->getContent(), true);

    //     $this->assertEquals('New York', $response['city']);
    //     $this->assertEquals('US', $response['country']);
    //     $this->assertEquals(22.0, $response['temperature']);
    //     $this->assertEquals(0.1, $response['precipitation']);
    //     $this->assertEquals(3.5, $response['windSpeed']);
    //     $this->assertEquals('2025-05-09T06:00:00', $response['sunrise']);
    //     $this->assertEquals('2025-05-09T20:00:00', $response['sunset']);
    // }

    public function testReverseGeocodeProxy(): void
    {
        $this->markTestSkipped('Reverse geocode proxy route is not implemented yet');
    }
} 