<template>
  <div class="live-weather-bg">
    <nav class="live-weather-nav">
      <span class="back-link" @click="$router.push('/list')">
        <span class="arrow">&#8592;</span>
        <span class="back-text">Back</span>
      </span>
    </nav>
    <div class="live-weather-container">
      <div v-if="!entry.city" class="live-weather-loading">
        <div class="spinner"></div>
        <div>Loading weather details...</div>
      </div>
      <div v-else class="live-weather-main">
        <div class="live-weather-header">
          <div class="location-select">
            <div class="city-country">
              <span class="location-pin">  <img :src="locationeIcon" alt="Sunrise" style="width:18px;vertical-align:middle;margin-right:2px;" /></span>
              {{ entry.city }}, {{ entry.country }}
            </div>
          </div>
          <div class="live-weather-date">{{ formatDayTime(entry.date) }}</div>
        </div>
        <div class="live-weather-status-card">
          <div class="status-left">
            <div class="main-temp">{{ entry.temperature?.toFixed(1) }}°C</div>
            <div class="main-desc">
              <img :src="weatherIcon" class="main-icon" />
              <span>{{ weatherDescription }}</span>
            </div>
            <div class="feels-like">Feels Like {{ entry.temperature?.toFixed(1) }}°C</div>
          </div>
          <div class="status-right">
            <div class="highlight-grid">
              <div class="highlight-item">
                <div class="highlight-label">Precipitation</div>
                <div class="highlight-value">{{ entry.precipitation?.toFixed(1) }} mm</div>
              </div>
              <div class="highlight-item">
                <div class="highlight-label">Wind</div>
                <div class="highlight-value">{{ entry.windSpeed?.toFixed(1) }} km/h</div>
              </div>
              <div class="highlight-item">
                <div class="highlight-label">Humidity</div>
                <div class="highlight-value">{{ formatHumidity(entry.humidity) }}</div>
              </div>
              <div class="highlight-item">
                <div class="highlight-label">Day/Night</div>
                <div class="highlight-value">
                  <span v-if="isNight"><img :src="sunriseIcon" alt="Sunrise" style="width:18px;vertical-align:middle;margin-right:2px;" />Night</span>
                  <span v-else><img :src="nightIcon" alt="night" style="width:18px;vertical-align:middle;margin-right:2px;" /> Day</span>
                </div>
              </div>
              <div class="highlight-item">
                <div class="highlight-label">Sunrise</div>
                <div class="highlight-value">{{ formatSun(entry.sunrise) }}</div>
              </div>
              <div class="highlight-item">
                <div class="highlight-label">Sunset</div>
                <div class="highlight-value">{{ formatSun(entry.sunset) }}</div>
              </div>
            </div>
          </div>
        </div>
        <div class="live-weather-hourly-section">
          <div class="hourly-title">Hourly Forecast</div>
          <div class="hourly-row-scroll">
            <div class="hour-card-custom" v-for="(hour, i) in hourly" :key="i">
              <img :src="getWeatherIcon(hour.code, hour.time)" class="hour-icon" />
              <div class="hour-time">{{ hour.time }}</div>
              <div class="hour-temp">{{ hour.temp }}°</div>
              <div class="hour-precip">{{ hour.precip }} mm</div>
            </div>
          </div>
        </div>
        <div class="live-weather-hourly-section">
          <div class="hourly-title">7-Day Forecast</div>
          <div class="hourly-row-scroll">
            <div class="hour-card-custom" v-for="(day, i) in daily" :key="i">
              <div class="hour-time">{{ formatDay(day.date) }}</div>
              <div class="hour-temp">{{ day.max }}° / {{ day.min }}°</div>
              <div class="hour-precip">{{ day.precip }} mm</div>
              <div class="hour-precip">Wind: {{ day.wind }} km/h</div>
              <div class="hour-precip">
                <img :src="sunriseIcon" alt="Sunrise" style="width:18px;vertical-align:middle;margin-right:2px;" />
                {{ day.sunrise }}
                |
                <img :src="sunsetIcon" alt="Sunset" style="width:18px;vertical-align:middle;margin-right:2px;" />
                {{ day.sunset }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import sunriseIcon from '@/assets/sunrise.png'
import sunsetIcon from '@/assets/sunset.png'
import locationeIcon from '@/assets/location.png'
import nightIcon from '@/assets/night.png'
import clearNightIcon from '@/assets/clear-night.png'
import rainyNightIcon from '@/assets/rainy-night.png'
import cloudyIcon from '@/assets/cloudy.png'
import rainyIcon from '@/assets/rainy.png'

const entry = ref({})
const hourly = ref([])
const daily = ref([])

const route = useRoute()
const id = route.params.id

onMounted(async () => {
  const res = await axios.get(`http://localhost:8000/api/weather/${id}`)
  entry.value = res.data

  // Hourly
  if (entry.value.hourly_data && Array.isArray(entry.value.hourly_data.time)) {
    const hd = entry.value.hourly_data;
    const recordDate = new Date(entry.value.date);
    hourly.value = hd.time.map((t, i) => {
      const hourDate = new Date(t);
      if (
        hourDate.getFullYear() !== recordDate.getFullYear() ||
        hourDate.getMonth() !== recordDate.getMonth() ||
        hourDate.getDate() !== recordDate.getDate()
      ) return null;
      return {
        time: t?.slice(11, 16) ?? '--:--',
        temp: hd.temperature_2m?.[i] ?? 'N/A',
        precip: hd.precipitation?.[i] ?? 'N/A',
        code: hd.weathercode?.[i] ?? 0
      }
    }).filter(Boolean);
  }

  // Daily
  if (entry.value.daily_forecast && Array.isArray(entry.value.daily_forecast.time)) {
    const dd = entry.value.daily_forecast
    daily.value = dd.time.map((d, i) => ({
      date: d ?? '',
      max: dd.temperature_2m_max?.[i] ?? 'N/A',
      min: dd.temperature_2m_min?.[i] ?? 'N/A',
      precip: dd.precipitation_sum?.[i] ?? 'N/A',
      wind: dd.wind_speed_10m_max?.[i] ?? 'N/A',
      sunrise: dd.sunrise?.[i]?.slice(11, 16) ?? '--:--',
      sunset: dd.sunset?.[i]?.slice(11, 16) ?? '--:--'
    }))
  }
})

function formatDay(date) {
  return new Date(date).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
}
function getWeatherIcon(code, hourTime) {
  const night = isNightHour(hourTime, entry.value.sunrise, entry.value.sunset);
  if (night) {
    if ([0, 1].includes(code)) return nightIcon;
    if ([2, 3].includes(code)) return cloudyIcon;
    if ([61, 63, 65, 80, 81, 82].includes(code)) return rainyIcon;
    return nightIcon;
  } else {
    if ([0, 1].includes(code)) return cloudyIcon;
    if ([2, 3].includes(code)) return cloudyIcon;
    if ([61, 63, 65, 80, 81, 82].includes(code)) return rainyIcon;
    return cloudyIcon;
  }
}
const weatherIcon = computed(() => getWeatherIcon(entry.value?.weathercode, entry.value?.hourly_data?.time?.[0]?.slice(11, 16) ?? '--:--'))
const weatherDescription = computed(() => {
  if (!entry.value) return ''
  if ([0, 1].includes(entry.value.weathercode)) return 'Sunny'
  if ([2, 3].includes(entry.value.weathercode)) return 'Cloudy'
  if ([61, 63, 65, 80, 81, 82].includes(entry.value.weathercode)) return 'Rainy'
  return 'Clear'
})
const isNight = computed(() => {
  if (!entry.value.sunrise || !entry.value.sunset) return false
  const now = new Date()
  const sunrise = new Date(entry.value.sunrise)
  const sunset = new Date(entry.value.sunset)
  return now < sunrise || now > sunset
})
function formatDayTime(dt) {
  const d = new Date(dt)
  return d.toLocaleDateString(undefined, {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}
function formatHumidity(h) {
  return h !== undefined && h !== null ? Number(h).toFixed(1) + '%' : 'N/A'
}
function formatSun(dt) {
  if (!dt) return '--:--'
  const d = new Date(dt)
  return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
}
function isNightHour(hourString, sunrise, sunset) {
  if (!sunrise || !sunset) return false;
  const [h, m] = hourString.split(':').map(Number);
  const hourDate = new Date();
  hourDate.setHours(h, m, 0, 0);
  const sunriseDate = new Date(sunrise);
  const sunsetDate = new Date(sunset);
  return hourDate < sunriseDate || hourDate > sunsetDate;
}
</script>


