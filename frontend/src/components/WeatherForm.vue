<template>
  <div class="live-weather-bg">
    <nav class="live-weather-nav">
    <button class="back-link mb-3" @click="$router.push('/list')">
      <span class="arrow">&#8592;</span> <span class="back-text">Back</span>
    </button>
  </nav>
  <br>
  <h2><center>{{ isEdit ? 'Edit' : 'Add' }} Weather Entry</center></h2>
  <div class="live-weather-main">
    <form @submit.prevent="handleSubmit" class="live-weather-form">
      <div v-if="fetchError" class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ fetchError }}
        <button type="button" class="btn-close" @click="fetchError = ''" aria-label="Close"></button>
      </div>
      <div class="mb-2 position-relative">
        <label class="form-label">City</label>
        <input v-model="form.city" class="form-control" required
               @input="onCityInput" @focus="showSuggestions = true" @blur="hideSuggestions"
               autocomplete="off" />
        <ul v-if="showSuggestions && citySuggestions.length" class="list-group position-absolute w-100 z-3" style="max-height: 200px; overflow-y: auto;">
          <li v-for="(city, i) in citySuggestions" :key="i" class="list-group-item list-group-item-action"
              @mousedown.prevent="selectCitySuggestion(city)">
            <div class="fw-bold">{{ city.name }}</div>
            <div class="small text-muted">
              <span class="badge bg-secondary me-1">{{ city.country_code }}</span>
              {{ city.admin1 ? city.admin1 + ', ' : '' }}
            </div>
          </li>
        </ul>
        <ul v-if="showSuggestions && form.city && !citySuggestions.length" class="list-group position-absolute w-100 z-3">
          <li class="list-group-item text-muted">No results</li>
        </ul>
        <div v-if="cityError" class="text-danger mt-1">Please select a valid city from the list. Make sure to choose the correct city and country combination.</div>
      </div>
      <div class="mb-2">
        <label class="form-label">Date</label>
        <input
          type="date"
          v-model="form.date"
          class="form-control"
          required
        />
      </div>
      <div class="mb-2">
        <button type="button" class="btn btn-warning" @click="fetchFromApi" :disabled="loading">
          <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
          {{ loading ? 'Fetching...' : 'Fetch from Open Meteo' }}
        </button>
      </div>
      <div class="mb-2">
        <label class="form-label">Latitude</label>
        <input v-model="form.latitude" class="form-control" readonly />
      </div>
      <div class="mb-2">
        <label class="form-label">Longitude</label>
        <input v-model="form.longitude" class="form-control" readonly />
      </div>
      <div class="mb-2">
        <label class="form-label">Temperature (°C)</label>
        <input v-model="form.temperature" type="number" step="any" class="form-control" required />
      </div>
      <div class="mb-2">
        <label class="form-label">Precipitation (mm)</label>
        <input v-model="form.precipitation" type="number" step="any" class="form-control" required />
      </div>
      <div class="mb-2">
        <label class="form-label">Wind Speed (km/h)</label>
        <input v-model="form.windSpeed" type="number" step="any" class="form-control" required />
      </div>
      <br>
      <div class="d-flex justify-content-center"> <button class="btn btn-info weather-form" type="submit" :disabled="submitting || loading">
        <span v-if="submitting" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        {{ submitting ? 'Saving...' : (isEdit ? 'Update' : 'Create') }}
      </button></div>
    </form>
  </div>
  </div>
</template>

<script>
import '@/assets/styles/weather-form.css'
import axios from 'axios'

export default {
  props: ['id'],
  data() {
    return {
      form: {
        city: '',
        country: '',
        latitude: '',
        longitude: '',
        date: '',
        temperature: '',
        precipitation: '',
        windSpeed: '',
        hourly_data: null
      },
      citySuggestions: [],
      showSuggestions: false,
      debounceTimeout: null,
      cityError: false,
      fetchError: '',
      submitting: false,
      loading: false,
      originalCity: '',
      originalCountry: '',
      originalDate: ''
    }
  },
  computed: {
    isEdit() { return !!this.id }
  },
  watch: {
    'form.temperature': {
      handler(newTemp) {
        if (this.isEdit && newTemp !== '') {
          this.updateHourlyForecast(newTemp)
        }
      }
    }
  },
  mounted() {
    if (this.isEdit) {
      this.loading = true
      axios.get(`http://localhost:8000/api/weather/${this.id}`)
        .then(res => {
          this.form = res.data
          this.form.city = res.data.city
          this.form.country = res.data.country
          this.form.date = res.data.date ? res.data.date.slice(0, 10) : ''
          this.form.latitude = res.data.latitude
          this.form.longitude = res.data.longitude
          
          this.originalCity = res.data.city
          this.originalCountry = res.data.country
          this.originalDate = res.data.date ? res.data.date.slice(0, 10) : ''
        })
        .catch(err => {
          this.fetchError = err.response?.data?.error || 'Could not load entry'
        })
        .finally(() => {
          this.loading = false
        })
    }
  },
  methods: {
    handleSubmit() {
      if (this.submitting || this.loading) return
      this.submitting = true
      
      let valid = true
      if (!this.isEdit || this.showSuggestions) {
        valid = this.citySuggestions.some(
          c => c.name.toLowerCase() === this.form.city.trim().toLowerCase()
        )
      }
      if (!valid) {
        this.cityError = true
        this.submitting = false
        return
      }
      
      this.cityError = false
      
      this.checkForDuplicates().then(isDuplicate => {
        if (isDuplicate) {
          this.fetchError = 'A weather record already exists for this city, country, and date combination.'
          this.submitting = false
          return
        }
        
        const url = this.isEdit
          ? `http://localhost:8000/api/weather/${this.id}`
          : 'http://localhost:8000/api/weather'
        const method = this.isEdit ? 'put' : 'post'
        
        const formData = {
          ...this.form,
          city_identifier: `${this.form.city}, ${this.form.country}`
        }
        
        axios[method](url, formData)
          .then(res => {
            let id = res.data.id
            if (!id && this.isEdit) {
              id = this.id
            }
            if (id) {
              this.$router.push(`/details/${id}`)
            } else {
              this.$router.push('/')
            }
          })
          .catch(err => {
            this.fetchError = err.response?.data?.error || 'Could not save entry'
          })
          .finally(() => {
            this.submitting = false
          })
      })
    },
    checkForDuplicates() {
      return new Promise((resolve) => {
        if (this.isEdit && 
            this.form.city === this.originalCity && 
            this.form.country === this.originalCountry && 
            this.form.date === this.originalDate) {
          resolve(false)
          return
        }

        axios.get('http://localhost:8000/api/weather/check-duplicate', {
          params: {
            city: this.form.city,
            country: this.form.country,
            date: this.form.date,
            exclude_id: this.isEdit ? this.id : null
          }
        })
        .then(res => {
          resolve(res.data.isDuplicate)
        })
        .catch(() => {
          resolve(false)
        })
      })
    },
    fetchFromApi() {
      if (!this.form.city || !this.form.date) {
        this.fetchError = 'Please enter city and date first.'
        return
      }
      this.loading = true
      this.fetchError = ''
      axios.post('http://localhost:8000/api/weather/fetch', {
        city: this.form.city,
        date: this.form.date
      })
        .then(res => {
          const { daily, ...rest } = res.data;
          this.form = {
            ...this.form,
            ...rest,
            daily_forecast: daily
          }
        })
        .catch(err => {
          this.fetchError = err.response?.data?.error || 'Could not fetch weather data.'
        })
        .finally(() => {
          this.loading = false
        })
    },
    onCityInput() {
      clearTimeout(this.debounceTimeout)
      if (!this.form.city) {
        this.citySuggestions = []
        return
      }
      
      this.debounceTimeout = setTimeout(async () => {
        try {
          const res = await axios.get(
            `https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(this.form.city)}&count=5`
          )
          const results = res.data.results || []
          const seen = new Set()
          this.citySuggestions = results.filter(city => {
            const key = `${city.name}|${city.admin1}|${city.country_code}`
            if (seen.has(key)) return false
            seen.add(key)
            return true
          })
        } catch (error) {
          console.error('Error fetching city suggestions:', error)
        }
      }, 300)
    },
    selectCitySuggestion(city) {
      this.form.city = city.name
      this.form.country = city.country_code
      this.showSuggestions = false
      this.cityError = false
    },
    hideSuggestions() {
      setTimeout(() => { this.showSuggestions = false }, 200)
    },
    updateHourlyForecast(newTemp) {
      if (
        this.form.hourly_data &&
        Array.isArray(this.form.hourly_data.temperature_2m) &&
        Array.isArray(this.form.hourly_data.weathercode)
      ) {
        const baseTemp = parseFloat(newTemp);

        // Update temperature_2m array with some variation
        this.form.hourly_data.temperature_2m = this.form.hourly_data.temperature_2m.map(() =>
          (baseTemp + (Math.random() * 4 - 2)).toFixed(1)
        );

        // Update weathercode array based on new temperature
        this.form.hourly_data.weathercode = this.form.hourly_data.temperature_2m.map(temp =>
          this.getWeatherCode(parseFloat(temp))
        );
      }
    },
    getWeatherCode(temperature) {
      // Example mapping: adjust as needed for your app
      if (temperature <= 0) return 71; // Snow
      if (temperature <= 10) return 3; // Cloudy
      if (temperature <= 20) return 2; // Partly Cloudy
      if (temperature <= 30) return 1; // Sunny
      return 0; // Clear
    }
  }
}
</script>

<style scoped>
</style>