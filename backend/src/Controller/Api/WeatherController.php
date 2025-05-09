<?php

namespace App\Controller\Api;

use App\Entity\WeatherEntry;
use App\Repository\WeatherEntryRepository;
use App\Service\OpenMeteoService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/weather')]
class WeatherController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(Request $request, WeatherEntryRepository $repo): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;
        $q = $request->query->get('q');
        $date = $request->query->get('date');
        $country = $request->query->get('country');
        $order = $request->query->get('order');

        $qb = $repo->createQueryBuilder('w');
        if ($q) {
            $qb->andWhere('LOWER(w.city) LIKE :q')
               ->setParameter('q', '%' . strtolower($q) . '%');
        }
        if ($date) {
            $start = new \DateTime($date);
            $end = (clone $start)->modify('+1 day');
            $qb->andWhere('w.date >= :start AND w.date < :end')
               ->setParameter('start', $start->format('Y-m-d 00:00:00'))
               ->setParameter('end', $end->format('Y-m-d 00:00:00'));
        }
        if ($country) {
            $qb->andWhere('w.country = :country')
               ->setParameter('country', $country);
        }
        if ($order === 'updated_desc') {
            $qb->orderBy('w.updatedAt', 'DESC');
        } else {
            $qb->orderBy('w.updatedAt', 'DESC');
        }
        $qb->setFirstResult($offset)
           ->setMaxResults($limit);

        $entries = $qb->getQuery()->getResult();

        $countQb = $repo->createQueryBuilder('w');
        if ($q) {
            $countQb->andWhere('LOWER(w.city) LIKE :q')
                    ->setParameter('q', '%' . strtolower($q) . '%');
        }
        if ($date) {
            $start = new \DateTime($date);
            $end = (clone $start)->modify('+1 day');
            $countQb->andWhere('w.date >= :start AND w.date < :end')
                    ->setParameter('start', $start->format('Y-m-d 00:00:00'))
                    ->setParameter('end', $end->format('Y-m-d 00:00:00'));
        }
        $total = (int)$countQb->select('COUNT(w.id)')->getQuery()->getSingleScalarResult();

        $data = array_map(fn($e) => [
            'id' => $e->getId(),
            'city' => $e->getCity(),
            'country' => $e->getCountry(),
            'date' => $e->getDate()->format('Y-m-d\TH:i:s'),
            'temperature' => $e->getTemperature(),
            'precipitation' => $e->getPrecipitation(),
            'windSpeed' => $e->getWindSpeed(),
        ], $entries);

        return $this->json([
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'perPage' => $limit,
        ]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(WeatherEntry $entry): JsonResponse
    {
        return $this->json([
            'id' => $entry->getId(),
            'city' => $entry->getCity(),
            'country' => $entry->getCountry(),
            'latitude' => $entry->getLatitude(),
            'longitude' => $entry->getLongitude(),
            'date' => $entry->getDate()->format('Y-m-d\TH:i:s'),
            'temperature' => $entry->getTemperature(),
            'precipitation' => $entry->getPrecipitation(),
            'windSpeed' => $entry->getWindSpeed(),
            'weathercode' => $entry->getWeathercode(),
            'sunrise' => $entry->getSunrise() ? $entry->getSunrise()->format('Y-m-d\TH:i:s') : null,
            'sunset' => $entry->getSunset() ? $entry->getSunset()->format('Y-m-d\TH:i:s') : null,
            'hourly_data' => $entry->getHourlyData(),
            'humidity' => $entry->getHumidity(),
            'daily_forecast' => $entry->getDailyForecast(),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $existing = $em->getRepository(WeatherEntry::class)->findOneBy([
            'city' => $data['city'],
            'country' => $data['country'],
            'date' => (new \DateTime($data['date']))->setTime(0, 0, 0)
        ]);
        if ($existing) {
            return $this->json(['error' => 'Entry for this city and date already exists.'], 400);
        }
        $entry = new WeatherEntry();
        $entry->setCity($data['city']);
        $entry->setCountry($data['country']);
        $entry->setLatitude($data['latitude']);
        $entry->setLongitude($data['longitude']);
        $entry->setDate(new \DateTime($data['date']));
        $entry->setTemperature($data['temperature']);
        $entry->setPrecipitation($data['precipitation']);
        $entry->setWindSpeed($data['windSpeed']);
        $entry->setWeathercode($data['weathercode'] ?? null);
        $entry->setSunrise($data['sunrise'] ? new \DateTime($data['sunrise']) : null);
        $entry->setSunset($data['sunset'] ? new \DateTime($data['sunset']) : null);
        if (isset($data['hourly_data'])) {
            $entry->setHourlyData($data['hourly_data']);
        }
        $humidity = null;
        if (isset($data['hourly_data']['relative_humidity_2m'])) {
            $arr = $data['hourly_data']['relative_humidity_2m'];
            $humidity = count($arr) ? array_sum($arr) / count($arr) : null;
        }
        $entry->setHumidity($humidity);
        $entry->setDailyForecast($data['daily_forecast'] ?? null);
        $entry->setUpdatedAt(new \DateTime());
        $em->persist($entry);
        $em->flush();
        return $this->json(['status' => 'created', 'id' => $entry->getId()], 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(Request $request, WeatherEntry $entry, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $repo = $em->getRepository(WeatherEntry::class);
        $duplicate = $repo->findOneBy([
            'city' => $data['city'],
            'country' => $data['country'],
            'date' => (new \DateTime($data['date']))->setTime(0, 0, 0)
        ]);
        if ($duplicate && $duplicate->getId() !== $entry->getId()) {
            return $this->json(['error' => 'Entry for this city and date already exists.'], 400);
        }

        $entry->setCity($data['city']);
        $entry->setCountry($data['country']);
        $entry->setLatitude($data['latitude']);
        $entry->setLongitude($data['longitude']);
        $entry->setDate(new \DateTime($data['date']));
        $entry->setTemperature($data['temperature']);
        $entry->setPrecipitation($data['precipitation']);
        $entry->setWindSpeed($data['windSpeed']);
        $entry->setWeathercode($data['weathercode'] ?? null);
        $entry->setSunrise($data['sunrise'] ? new \DateTime($data['sunrise']) : null);
        $entry->setSunset($data['sunset'] ? new \DateTime($data['sunset']) : null);
        $entry->setDailyForecast($data['daily_forecast'] ?? null);
        $entry->setUpdatedAt(new \DateTime());
        $em->flush();
        return $this->json(['status' => 'updated']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(WeatherEntry $entry, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($entry);
        $em->flush();
        return $this->json(null, 204);
    }

    #[Route('/fetch', methods: ['POST'])]
    public function fetchWeather(Request $request, OpenMeteoService $service): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $city = $data['city'];
        $country = $data['country'] ?? null;
        $date = $data['date'];
        $coords = $service->geocodeCity($city, $country);
        if (!$coords) return $this->json(['error' => 'City not found'], 404);

        $weather = $this->fetchWeatherData($coords['latitude'], $coords['longitude'], $date);
        if (!$weather || !isset($weather['hourly_data'])) return $this->json(['error' => 'Weather data not found'], 404);

        $isToday = (new \DateTime($date))->format('Y-m-d') === (new \DateTime())->format('Y-m-d');
        if ($isToday && isset($weather['temperature'])) {
            $temperature = $weather['temperature'];
            $temperatureType = 'current';
        } else {
            $temps = $weather['hourly_data']['temperature_2m'] ?? [];
            $avg = fn($arr) => count($arr) ? array_sum($arr) / count($arr) : 0;
            $temperature = $avg($temps);
            $temperatureType = 'average';
        }

        $precips = $weather['hourly_data']['precipitation'] ?? [];
        $winds = $weather['hourly_data']['wind_speed_10m'] ?? [];
        $avg = fn($arr) => count($arr) ? array_sum($arr) / count($arr) : 0;

        $hourlyData = $weather['hourly_data'] ?? null;
        return $this->json([
            'city' => $city,
            'country' => $coords['country'] ?? $country,
            'latitude' => $coords['latitude'],
            'longitude' => $coords['longitude'],
            'date' => $date,
            'temperature' => $temperature,
            'temperatureType' => $temperatureType,
            'precipitation' => $avg($precips),
            'windSpeed' => $avg($winds),
            'weathercode' => $weather['weathercode'],
            'sunrise' => $weather['sunrise'],
            'sunset' => $weather['sunset'],
            'hourly_data' => $hourlyData,
            'daily' => $weather['daily'] ?? null,
        ]);
    }

    private function fetchWeatherData($lat, $lon, $date): array
    {
        $url = "https://api.open-meteo.com/v1/forecast?" . http_build_query([
            'latitude' => $lat,
            'longitude' => $lon,
            'hourly' => 'temperature_2m,precipitation,weathercode,wind_speed_10m,relative_humidity_2m',
            'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum,weathercode,wind_speed_10m_max,uv_index_max,sunrise,sunset',
            'timezone' => 'auto',
            'current_weather' => 'true',
            'start_date' => $date,
            'end_date' => (new \DateTime($date))->modify('+6 days')->format('Y-m-d')
        ]);

        $response = file_get_contents($url);
        if ($response === false) {
            throw new \Exception('Failed to fetch weather data');
        }

        $data = json_decode($response, true);
        if (!$data) {
            throw new \Exception('Invalid weather data received');
        }

        $currentHour = (int)date('G');

        $currentTemp = $data['current_weather']['temperature'] ?? null;
        if ($currentTemp === null) {
            $currentTemp = $data['hourly']['temperature_2m'][$currentHour] ?? null;
        }
        if ($currentTemp === null) {
            $temps = array_slice($data['hourly']['temperature_2m'], 0, 24);
            $avg = fn($arr) => count($arr) ? array_sum($arr) / count($arr) : null;
            $currentTemp = $avg($temps);
        }

        $precipitation = $data['hourly']['precipitation'][$currentHour] ?? 0;

        $windSpeed = $data['hourly']['wind_speed_10m'][$currentHour] ?? 0;

        $weathercode = $data['hourly']['weathercode'][$currentHour] ?? 0;

        $sunrise = $data['daily']['sunrise'][0] ?? null;
        $sunset = $data['daily']['sunset'][0] ?? null;

        return [
            'temperature' => $currentTemp,
            'precipitation' => $precipitation,
            'windSpeed' => $windSpeed,
            'weathercode' => $weathercode,
            'sunrise' => $sunrise,
            'sunset' => $sunset,
            'hourly_data' => [
                'time' => $data['hourly']['time'],
                'temperature_2m' => $data['hourly']['temperature_2m'],
                'precipitation' => $data['hourly']['precipitation'],
                'weathercode' => $data['hourly']['weathercode'],
                'wind_speed_10m' => $data['hourly']['wind_speed_10m'],
                'relative_humidity_2m' => $data['hourly']['relative_humidity_2m']
            ],
            'daily' => $data['daily'] ?? null,
        ];
    }

    #[Route('/live-fetch', name: 'api_weather_live_fetch', methods: ['POST'])]
    public function liveFetch(Request $request, EntityManagerInterface $em, WeatherEntryRepository $repo): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $city = $data['city'] ?? null;
        $country = $data['country'] ?? null;
        $date = $data['date'] ?? null;

        if (!$city || !$country || !$date) {
            return $this->json(['error' => 'Missing parameters'], 400);
        }

        $existing = $repo->findOneBy([
            'city' => $city,
            'country' => $country,
            'date' => new \DateTime($date)
        ]);
        if ($existing) {
            return $this->json(['status' => 'exists', 'weather' => $existing->toArray()], 200);
        }

        $geoRes = @file_get_contents("https://geocoding-api.open-meteo.com/v1/search?name=" . urlencode($city) . "&count=1&language=en&format=json");
        $geoData = json_decode($geoRes, true);
        if (!$geoData || !isset($geoData['results'][0])) {
            return $this->json(['error' => 'City not found'], 404);
        }
        $lat = $geoData['results'][0]['latitude'];
        $lon = $geoData['results'][0]['longitude'];

        $endDate = (new \DateTime($date))->modify('+6 days')->format('Y-m-d');
        $weatherUrl = "https://api.open-meteo.com/v1/forecast?latitude=$lat&longitude=$lon&hourly=temperature_2m,precipitation,weathercode,wind_speed_10m,relative_humidity_2m&daily=temperature_2m_max,temperature_2m_min,sunrise,sunset,precipitation_sum,wind_speed_10m_max&current_weather=true&timezone=auto&start_date=$date&end_date=$endDate";
        $weatherRes = @file_get_contents($weatherUrl);
        $weather = json_decode($weatherRes, true);

        if (!$weather || !isset($weather['hourly']['time'][0])) {
            return $this->json(['error' => 'Weather data not found'], 404);
        }

        $hourIdx = 0;
        $entity = new WeatherEntry();
        $entity->setCity($city);
        $entity->setCountry($country);
        $entity->setDate(new \DateTime($date));
        $entity->setLatitude($lat);
        $entity->setLongitude($lon);
        $entity->setTemperature($weather['hourly']['temperature_2m'][$hourIdx] ?? null);
        $entity->setPrecipitation($weather['hourly']['precipitation'][$hourIdx] ?? null);
        $entity->setWindSpeed($weather['hourly']['wind_speed_10m'][$hourIdx] ?? null);
        $entity->setHumidity($weather['hourly']['relative_humidity_2m'][$hourIdx] ?? null);
        $entity->setSunrise(
            isset($weather['daily']['sunrise'][0]) ? new \DateTime($weather['daily']['sunrise'][0]) : null
        );
        $entity->setSunset(
            isset($weather['daily']['sunset'][0]) ? new \DateTime($weather['daily']['sunset'][0]) : null
        );
        $entity->setHourlyData($weather['hourly']);
        $entity->setDailyForecast($weather['daily']);
        $entity->setUpdatedAt(new \DateTime());

        $em->persist($entity);
        $em->flush();

        return $this->json(['status' => 'created', 'weather' => $entity->toArray()], 201);
    }

    #[Route('/api/proxy/reverse-geocode', methods: ['GET'])]
    public function reverseGeocodeProxy(Request $request): JsonResponse
    {
        $lat = $request->query->get('latitude');
        $lon = $request->query->get('longitude');
        $lang = $request->query->get('language', 'en');
        if (!$lat || !$lon) {
            return $this->json(['error' => 'Missing coordinates'], 400);
        }
        $url = "https://geocoding-api.open-meteo.com/v1/reverse?latitude=$lat&longitude=$lon&language=$lang&format=json";
        $data = @file_get_contents($url);
        if ($data === false) {
            return $this->json(['error' => 'Failed to fetch from Open-Meteo'], 502);
        }
        return new JsonResponse(json_decode($data, true));
    }
}