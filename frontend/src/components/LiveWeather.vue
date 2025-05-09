<template>
 <div class="live-weather-bg">
  
    <div class="live-weather-container">
      <div v-if="loading" class="live-weather-loading">
        <div class="spinner"></div>
        <div>Loading live weather data...</div>
      </div>
      <div v-else-if="error" class="live-weather-error">
        <div class="error-icon">⚠️</div>
        <div>{{ error }}</div>
        <button
          v-if="error === 'City not found'"
          class="btn btn-info"
          style="margin-top: 1rem; display: inline-block;"
          @click="reloadPage"
        >
          ← Back to Live Weather Data
        </button>
      </div>
      <div v-else class="live-weather-main">
        <div class="live-weather-header">
          <div class="location-select">
            <input
              v-model="cityInput"
              @keyup.enter="fetchWeather"
              @input="onCityInput"
              @focus="showSuggestions = true"
              @blur="hideSuggestions"
              placeholder="Enter city name"
              class="city-input"
              :disabled="loading"
            />
            <button @click="fetchWeather" :disabled="loading || !cityInput" class="search-btn">Search</button>
            <button @click="useMyLocation" :disabled="loading" class="location-btn">Use My Location</button>
            <ul v-if="showSuggestions && suggestions.length" class="suggestion-list">
              <li
                v-for="s in suggestions"
                :key="s.id"
                @mousedown.prevent="selectSuggestion(s)"
                class="suggestion-item"
              >
                <span class="fw-bold">{{ s.name }}</span>
                <span class="text-muted small ms-2">({{ s.country_code }})</span>
                <span v-if="s.admin1" class="text-muted small ms-2">{{ s.admin1 }}</span>
              </li>
            </ul>
          </div>
          <div class="live-weather-date">{{ formatDate(weatherData.date) }}</div>
        </div>
        <div class="live-weather-status-card">
          <div class="status-left">
            <div class="location">
              <span class="location-pin">📍</span>
              <span class="city-country">{{ weatherData.city }}, {{ weatherData.country }}</span>
            </div>
            <div class="main-temp">{{ weatherData.temperature?.toFixed(1) }}°C</div>
            <div class="main-desc">
              <img :src="getWeatherIcon(weatherData.weathercode, isNight)" class="main-icon" />
              <span>{{ getWeatherDesc(weatherData.weathercode) }}</span>
            </div>
            <div class="feels-like">Feels Like {{ weatherData.temperature?.toFixed(1) }}°C</div>
            <div class="minmax">High: {{ weatherData.maxTemp?.toFixed(1) }}° Low: {{ weatherData.minTemp?.toFixed(1) }}°</div>
          </div>
          <div class="status-right">
            <div class="highlight-grid">
              <div class="highlight-item">
                <div class="highlight-label">Precipitation</div>
                <div class="highlight-value">{{ weatherData.precipitation?.toFixed(1) }} mm</div>
              </div>
              <div class="highlight-item">
                <div class="highlight-label">Wind</div>
                <div class="highlight-value">{{ weatherData.windSpeed?.toFixed(1) }} km/h</div>
              </div>
              <div class="highlight-item">
                <div class="highlight-label">Humidity</div>
                <div class="highlight-value">{{ weatherData.humidity?.toFixed(1) }}%</div>
              </div>
              <div class="highlight-item">
                <div class="highlight-label">Day/Night</div>
                <div class="highlight-value">
                  <span v-if="isNight">🌙 Night</span>
                  <span v-else>☀️ Day</span>
                </div>
              </div>
              <div class="highlight-item">
                <div class="highlight-label">Sunrise</div>
                <div class="highlight-value">{{ formatTime(weatherData.sunrise) }}</div>
              </div>
              <div class="highlight-item">
                <div class="highlight-label">Sunset</div>
                <div class="highlight-value">{{ formatTime(weatherData.sunset) }}</div>
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

        <div v-if="daily.length" class="live-weather-hourly-section">
          <div class="hourly-title">7-Day Forecast</div>
          <div class="hourly-row-scroll">
            <div class="hour-card-custom" v-for="(day, i) in daily" :key="i">
              <div class="hour-time">{{ formatDayDate(day.date) }}</div>
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

        <div v-if="weatherData.city && weatherData.date && weatherData.city !== 'Current Location'" class="save-live-weather-section" style="margin-top:2rem; text-align:center;">
          <button
            class="btn btn-primary"
            @click="saveLiveWeather"
            :disabled="saving"
            style="min-width:220px; font-size:1.1rem;"
          >
            {{ saving ? 'Saving...' : 'Save to My Weather List' }}
          </button>
          <div v-if="saveMessage" :class="['alert', saveSuccess ? 'alert-success' : 'alert-danger']" style="margin-top:1rem; max-width:400px; margin-left:auto; margin-right:auto;">
            {{ saveMessage }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import cloudyIcon from '@/assets/cloudy.png'
import nightIcon from '@/assets/night.png'
import rainyNightIcon from '@/assets/rainy-night.png'
import rainyIcon from '@/assets/rainy.png'
import sunriseIcon from '@/assets/sunrise.png'
import sunsetIcon from '@/assets/sunset.png'
import { computed, onMounted, ref, watch } from 'vue'

const cityInput = ref('')
const weatherData = ref({})
const hourly = ref([])
const daily = ref([])
const loading = ref(true)
const error = ref('')
const lat = ref(null)
const lon = ref(null)
const suggestions = ref([])
const showSuggestions = ref(false)
const selectedSuggestion = ref(null)
let debounceTimeout = null
let justSelected = false
const searching = ref(false)
const saving = ref(false)
const saveMessage = ref('')
const saveSuccess = ref(false)

const isNight = computed(() => {
  if (!weatherData.value.sunrise || !weatherData.value.sunset) return false
  const now = new Date()
  const sunrise = new Date(weatherData.value.sunrise)
  const sunset = new Date(weatherData.value.sunset)
  return now < sunrise || now > sunset
})


function getWeatherIcon(code, night) {
  if (night) {
    if ([0, 1].includes(code)) return sunriseIcon;
    if ([2, 3].includes(code)) return nightIcon;
    if ([61, 63, 65, 80, 81, 82].includes(code)) return rainyNightIcon;
    return nightIcon;
  } else {
    if ([0, 1].includes(code)) return sunriseIcon;
    if ([2, 3].includes(code)) return cloudyIcon;
    if ([61, 63, 65, 80, 81, 82].includes(code)) return rainyIcon;
    return sunriseIcon;
  }
}

function getWeatherDesc(code) {
  if ([0, 1].includes(code)) return 'Sunny'
  if ([2, 3].includes(code)) return 'Cloudy'
  if ([61, 63, 65, 80, 81, 82].includes(code)) return 'Rainy'
  return 'Clear'
}
function formatDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return d.toLocaleDateString(undefined, { weekday: 'long', day: '2-digit', month: 'short', year: 'numeric' })
}
function formatTime(dt) {
  if (!dt) return '--:--'
  const d = new Date(dt)
  return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
}

async function fetchWeather() {
  if (!cityInput.value) return;
  searching.value = true;
  loading.value = true;
  error.value = '';
  hourly.value = [];
  daily.value = [];
  try {
    let latitude, longitude, name, country_code;
    if (selectedSuggestion.value) {
      ({ latitude, longitude, name, country_code } = selectedSuggestion.value);
    } else {
      const geoRes = await fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(cityInput.value)}&count=5&language=en&format=json`);
      if (!geoRes.ok) throw new Error('Failed to fetch city data');
      const geoData = await geoRes.json();
      if (!geoData.results || !geoData.results.length) throw new Error('City not found');
      const exactMatch = geoData.results.find(
        r => r.name.toLowerCase() === cityInput.value.trim().toLowerCase()
      );
      if (geoData.results.length === 1 || exactMatch) {
        const match = exactMatch || geoData.results[0];
        ({ latitude, longitude, name, country_code } = match);
      } else {
        suggestions.value = geoData.results;
        showSuggestions.value = true;
        loading.value = false;
        searching.value = false;
        return;
      }
    }
    const today = new Date();
    const endDate = new Date(today);
    endDate.setDate(today.getDate() + 6);
    const dateStr = today.toISOString().slice(0, 10);
    const endDateStr = endDate.toISOString().slice(0, 10);
    const weatherRes = await fetch(`https://api.open-meteo.com/v1/forecast?latitude=${latitude}&longitude=${longitude}&hourly=temperature_2m,precipitation,weathercode,wind_speed_10m,relative_humidity_2m&daily=temperature_2m_max,temperature_2m_min,sunrise,sunset,precipitation_sum,wind_speed_10m_max&current_weather=true&timezone=auto&start_date=${dateStr}&end_date=${endDateStr}`);
    if (!weatherRes.ok) throw new Error('Failed to fetch weather data');
    const weather = await weatherRes.json();
    if (
      !weather.daily || !weather.daily.temperature_2m_max || !weather.daily.temperature_2m_max.length ||
      !weather.hourly || !weather.hourly.time || !weather.hourly.time.length
    ) {
      throw new Error('Weather data not available for this location.');
    }
    const now = new Date();
    const hourIdx = weather.hourly.time.findIndex(t => new Date(t).getHours() === now.getHours());
    weatherData.value = {
      city: name,
      country: country_code,
      date: weather.hourly?.time?.[hourIdx] || weather.hourly?.time?.[0] || '',
      temperature: weather.hourly?.temperature_2m?.[hourIdx] ?? weather.hourly?.temperature_2m?.[0] ?? null,
      maxTemp: weather.daily?.temperature_2m_max?.[0] ?? null,
      minTemp: weather.daily?.temperature_2m_min?.[0] ?? null,
      precipitation: weather.hourly?.precipitation?.[hourIdx] ?? weather.hourly?.precipitation?.[0] ?? null,
      windSpeed: weather.hourly?.wind_speed_10m?.[hourIdx] ?? weather.hourly?.wind_speed_10m?.[0] ?? null,
      humidity: weather.hourly?.relative_humidity_2m?.[hourIdx] ?? weather.hourly?.relative_humidity_2m?.[0] ?? null,
      weathercode: weather.hourly?.weathercode?.[hourIdx] ?? weather.hourly?.weathercode?.[0] ?? null,
      sunrise: weather.daily?.sunrise?.[0] ?? '',
      sunset: weather.daily?.sunset?.[0] ?? '',
      hourly_data: {
        time: weather.hourly.time,
        temperature_2m: weather.hourly.temperature_2m,
        precipitation: weather.hourly.precipitation,
        weathercode: weather.hourly.weathercode,
        wind_speed_10m: weather.hourly.wind_speed_10m,
        relative_humidity_2m: weather.hourly.relative_humidity_2m
      },
      daily_forecast: {
        time: weather.daily.time,
        temperature_2m_max: weather.daily.temperature_2m_max,
        temperature_2m_min: weather.daily.temperature_2m_min,
        precipitation_sum: weather.daily.precipitation_sum,
        wind_speed_10m_max: weather.daily.wind_speed_10m_max,
        sunrise: weather.daily.sunrise,
        sunset: weather.daily.sunset
      }
    };
    hourly.value = weather.hourly.time.map((t, i) => {
      const hourDate = new Date(t);
      const isNight = hourDate < new Date(weather.daily.sunrise[0]) || hourDate > new Date(weather.daily.sunset[0]);
      return {
        time: t.slice(11, 16),
        temp: weather.hourly.temperature_2m[i],
        precip: weather.hourly.precipitation[i],
        code: weather.hourly.weathercode[i],
        isNight
      };
    }).filter((hour, i) => {
      const today = new Date();
      const hourDate = new Date(weather.hourly.time[i]);
      return hourDate.getDate() === today.getDate() &&
             hourDate.getMonth() === today.getMonth() &&
             hourDate.getFullYear() === today.getFullYear();
    });
    daily.value = weather.daily.time.map((t, i) => ({
      date: t,
      max: weather.daily.temperature_2m_max[i],
      min: weather.daily.temperature_2m_min[i],
      precip: weather.daily.precipitation_sum[i],
      wind: weather.daily.wind_speed_10m_max[i],
      sunrise: weather.daily.sunrise[i].slice(11, 16),
      sunset: weather.daily.sunset[i].slice(11, 16)
    }));
    selectedSuggestion.value = null;
    showSuggestions.value = false;
    suggestions.value = [];
  } catch (e) {
    error.value = e.message || 'Failed to fetch weather data.';
    weatherData.value = {};
    hourly.value = [];
    daily.value = [];
  } finally {
    loading.value = false;
    searching.value = false;
  }
}

async function useMyLocation() {
  loading.value = true
  error.value = ''
  if (!navigator.geolocation) {
    error.value = 'Geolocation is not supported by your browser.'
    loading.value = false
    return
  }
  navigator.geolocation.getCurrentPosition(async (pos) => {
    lat.value = pos.coords.latitude
    lon.value = pos.coords.longitude
    cityInput.value = ''
    await fetchWeatherByCoords(lat.value, lon.value)
    loading.value = false
  }, (err) => {
    error.value = 'Could not get your location.'
    loading.value = false
  })
}

async function fetchWeatherByCoords(latitude, longitude) {
  let cityName = 'Current Location';  // Default value
  let countryCode = '';
  
  try {
    const geoRes = await fetch(`http://localhost:8000/api/proxy/reverse-geocode?latitude=${latitude}&longitude=${longitude}&language=en`);
    if (!geoRes.ok) throw new Error('Failed to get location name');
    
    const geoData = await geoRes.json();
    if (geoData && geoData.results && geoData.results.length > 0) {
      const location = geoData.results[0];
      if (location.name) {
        cityName = location.name;
        countryCode = location.country_code || '';
      }
    }
  } catch (e) {
    console.warn('Could not get city name:', e);
    // Keep using default values
  }

  try {
    const today = new Date()
    const endDate = new Date(today)
    endDate.setDate(today.getDate() + 6)
    const dateStr = today.toISOString().slice(0, 10)
    const endDateStr = endDate.toISOString().slice(0, 10)
    const weatherRes = await fetch(`https://api.open-meteo.com/v1/forecast?latitude=${latitude}&longitude=${longitude}&hourly=temperature_2m,precipitation,weathercode,wind_speed_10m,relative_humidity_2m&daily=temperature_2m_max,temperature_2m_min,sunrise,sunset,precipitation_sum,wind_speed_10m_max&current_weather=true&timezone=auto&start_date=${dateStr}&end_date=${endDateStr}`)
    if (!weatherRes.ok) throw new Error('Failed to fetch weather data');
    
    const weather = await weatherRes.json()
    if (!weather.hourly || !weather.hourly.time) throw new Error('Weather data not found')
    
    // Find current hour index
    const now = new Date()
    const hourIdx = weather.hourly.time.findIndex(t => new Date(t).getHours() === now.getHours())
    
    weatherData.value = {
      city: cityName,
      country: countryCode,
      date: weather.hourly.time[hourIdx] || weather.hourly.time[0],
      temperature: weather.hourly.temperature_2m[hourIdx],
      maxTemp: weather.daily.temperature_2m_max[0],
      minTemp: weather.daily.temperature_2m_min[0],
      precipitation: weather.hourly.precipitation[hourIdx],
      windSpeed: weather.hourly.wind_speed_10m[hourIdx],
      humidity: weather.hourly.relative_humidity_2m[hourIdx],
      weathercode: weather.hourly.weathercode[hourIdx],
      sunrise: weather.daily.sunrise[0],
      sunset: weather.daily.sunset[0],
      hourly_data: {
        time: weather.hourly.time,
        temperature_2m: weather.hourly.temperature_2m,
        precipitation: weather.hourly.precipitation,
        weathercode: weather.hourly.weathercode,
        wind_speed_10m: weather.hourly.wind_speed_10m,
        relative_humidity_2m: weather.hourly.relative_humidity_2m
      },
      daily_forecast: {
        time: weather.daily.time,
        temperature_2m_max: weather.daily.temperature_2m_max,
        temperature_2m_min: weather.daily.temperature_2m_min,
        precipitation_sum: weather.daily.precipitation_sum,
        wind_speed_10m_max: weather.daily.wind_speed_10m_max,
        sunrise: weather.daily.sunrise,
        sunset: weather.daily.sunset
      }
    }
    
    hourly.value = weather.hourly.time.map((t, i) => {
      const hourDate = new Date(t)
      const isNight = hourDate < new Date(weather.daily.sunrise[0]) || hourDate > new Date(weather.daily.sunset[0])
      return {
        time: t.slice(11, 16),
        temp: weather.hourly.temperature_2m[i],
        precip: weather.hourly.precipitation[i],
        code: weather.hourly.weathercode[i],
        isNight
      }
    }).filter((hour, i) => {
      const today = new Date();
      const hourDate = new Date(weather.hourly.time[i]);
      return hourDate.getDate() === today.getDate() &&
             hourDate.getMonth() === today.getMonth() &&
             hourDate.getFullYear() === today.getFullYear();
    });

    daily.value = weather.daily.time.map((t, i) => ({
      date: t,
      max: weather.daily.temperature_2m_max[i],
      min: weather.daily.temperature_2m_min[i],
      precip: weather.daily.precipitation_sum[i],
      wind: weather.daily.wind_speed_10m_max[i],
      sunrise: weather.daily.sunrise[i].slice(11, 16),
      sunset: weather.daily.sunset[i].slice(11, 16)
    }));

  } catch (e) {
    error.value = e.message || 'Failed to fetch weather data.'
    weatherData.value = {};
    hourly.value = [];
    daily.value = [];
  }
}

function selectSuggestion(s) {
  cityInput.value = s.name;
  selectedSuggestion.value = s;
  showSuggestions.value = false;
  suggestions.value = [];
  justSelected = true;
}

watch(cityInput, (val) => {
  selectedSuggestion.value = null;
  clearTimeout(debounceTimeout);
  if (!val || searching.value) {
    suggestions.value = [];
    showSuggestions.value = false;
    searching.value = false; // reset after search
    return;
  }
  debounceTimeout = setTimeout(async () => {
    if (justSelected) return;
    try {
      const res = await fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(val)}&count=5&language=en&format=json`);
      const data = await res.json();
      if (data.results) {
        suggestions.value = data.results;
        showSuggestions.value = true;
      } else {
        suggestions.value = [];
        showSuggestions.value = false;
      }
    } catch {
      suggestions.value = [];
      showSuggestions.value = false;
    }
  }, 300);
});

onMounted(() => {
  useMyLocation()
})

function hideSuggestions() {
  setTimeout(() => {
    if (justSelected) {
      justSelected = false;
      return; // Don't re-hide, already handled
    }
    showSuggestions.value = false;
  }, 80);
}

async function saveLiveWeather() {
  if (!weatherData.value.city || !weatherData.value.country || !weatherData.value.date) {
    saveMessage.value = "No weather data to save."
    saveSuccess.value = false
    setTimeout(() => { saveMessage.value = '' }, 3000)
    return
  }
  saving.value = true
  saveMessage.value = ''
  try {
    const res = await fetch('http://localhost:8000/api/weather/live-fetch', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        city: weatherData.value.city,
        country: weatherData.value.country,
        date: weatherData.value.date.slice(0, 10)
      })
    })
    const data = await res.json()
    if (res.ok && data.status === 'created') {
      saveMessage.value = "Weather data saved successfully!"
      saveSuccess.value = true
    } else if (data.status === 'exists') {
      saveMessage.value = "Weather data already exists for this city and date."
      saveSuccess.value = false
    } else {
      saveMessage.value = data.error || "Failed to save weather data."
      saveSuccess.value = false
    }
  } catch (e) {
    saveMessage.value = "Failed to save weather data."
    saveSuccess.value = false
  } finally {
    saving.value = false
    setTimeout(() => { saveMessage.value = '' }, 3000)
  }
}

function formatDayDate(dt) {
  if (!dt) return ''
  const d = new Date(dt)
  return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })
}

function formatNumber(val) {
  return val !== undefined && val !== null ? Number(val).toFixed(1) : 'N/A'
}

function reloadPage() {
  window.location.reload();
}

</script>

<style scoped>
</style>


