<?php

namespace App\Entity;

use App\Repository\WeatherEntryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WeatherEntryRepository::class)]
#[ORM\UniqueConstraint(name: "city_country_date_unique", columns: ["city", "country", "date"])]
class WeatherEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $city;

    #[ORM\Column(type: 'string', length: 2)]
    private $country;

    #[ORM\Column(type: 'float')]
    private $latitude;

    #[ORM\Column(type: 'float')]
    private $longitude;

    #[ORM\Column(type: 'datetime')]
    private $date;

    #[ORM\Column(type: 'float')]
    private $temperature;

    #[ORM\Column(type: 'float')]
    private $precipitation;

    #[ORM\Column(type: 'float')]
    private $windSpeed;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $weathercode;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $sunrise;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $sunset;

    #[ORM\Column(type: 'json', nullable: true)]
    private $hourlyData;

    #[ORM\Column(type: 'float', nullable: true)]
    private $humidity;

    #[ORM\Column(type: 'json', nullable: true)]
    private $dailyForecast;

    #[ORM\Column(type: 'datetime', nullable: false)]
    private $updatedAt;

    public function getId(): ?int { return $this->id; }
    public function getCity(): ?string { return $this->city; }
    public function setCity(string $city): self { $this->city = $city; return $this; }
    public function getCountry(): ?string { return $this->country; }
    public function setCountry(string $country): self { $this->country = $country; return $this; }
    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(float $latitude): self { $this->latitude = $latitude; return $this; }
    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(float $longitude): self { $this->longitude = $longitude; return $this; }
    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): self { $this->date = $date; return $this; }
    public function getTemperature(): ?float { return $this->temperature; }
    public function setTemperature(float $temperature): self { $this->temperature = $temperature; return $this; }
    public function getPrecipitation(): ?float { return $this->precipitation; }
    public function setPrecipitation(float $precipitation): self { $this->precipitation = $precipitation; return $this; }
    public function getWindSpeed(): ?float { return $this->windSpeed; }
    public function setWindSpeed(float $windSpeed): self { $this->windSpeed = $windSpeed; return $this; }
    public function getWeathercode(): ?int { return $this->weathercode; }
    public function setWeathercode(?int $weathercode): self { $this->weathercode = $weathercode; return $this; }
    public function getSunrise(): ?\DateTimeInterface { return $this->sunrise; }
    public function setSunrise(?\DateTimeInterface $sunrise): self { $this->sunrise = $sunrise; return $this; }
    public function getSunset(): ?\DateTimeInterface { return $this->sunset; }
    public function setSunset(?\DateTimeInterface $sunset): self { $this->sunset = $sunset; return $this; }
    public function getHourlyData(): ?array { return $this->hourlyData; }
    public function setHourlyData(?array $hourlyData): self { $this->hourlyData = $hourlyData; return $this; }
    public function getHumidity(): ?float { return $this->humidity; }
    public function setHumidity(?float $humidity): self { $this->humidity = $humidity; return $this; }
    public function getDailyForecast(): ?array { return $this->dailyForecast; }
    public function setDailyForecast(?array $dailyForecast): self { $this->dailyForecast = $dailyForecast; return $this; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeInterface $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

   
    public function onPrePersist(): void
    {
        $this->updatedAt = new \DateTime();
    }

  
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'city' => $this->getCity(),
            'country' => $this->getCountry(),
            'latitude' => $this->getLatitude(),
            'longitude' => $this->getLongitude(),
            'date' => $this->getDate()->format('Y-m-d\TH:i:s'),
            'temperature' => $this->getTemperature(),
            'precipitation' => $this->getPrecipitation(),
            'windSpeed' => $this->getWindSpeed(),
            'weathercode' => $this->getWeathercode(),
            'sunrise' => $this->getSunrise() ? $this->getSunrise()->format('Y-m-d\TH:i:s') : null,
            'sunset' => $this->getSunset() ? $this->getSunset()->format('Y-m-d\TH:i:s') : null,
            'hourly_data' => $this->getHourlyData(),
            'humidity' => $this->getHumidity(),
            'daily_forecast' => $this->getDailyForecast(),
        ];
    }
}