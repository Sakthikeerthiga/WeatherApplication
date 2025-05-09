<template>
  <div class="live-weather-bg">
    <div class="live-weather-nav">
      <h2>Weather Entries</h2>
    </div>
    <div class="live-weather-container">
      <form class="search-form" @submit.prevent="onSearch">
        <div class="form-control-wrapper">
          <input
            v-model="search"
            @input="onCityInput"
            @focus="showSuggestions = true"
            @blur="hideSuggestions"
            placeholder="Search city..."
            class="form-control"
            autocomplete="off"
          />
          <div
            v-if="showSuggestions && citySuggestions.length"
            class="suggestion-list"
          >
            <div class="suggestion-header">Suggestions</div>
            <ul class="suggestion-items">
              <li
                v-for="(city, i) in citySuggestions"
                :key="i"
                class="suggestion-item"
                @mousedown.prevent="selectCitySuggestion(city)"
              >
                <span class="city-name">{{ city.name }}</span>
                <span class="city-region">{{ city.admin1 ? city.admin1 + ', ' : '' }}{{ city.country_code }}</span>
              </li>
            </ul>
          </div>
        </div>
        <input type="date" v-model="dateFilter" class="form-control date-input" />
        <button class="btn btn-primary" type="submit" :disabled="!search || !dateFilter">Search</button>
        <button 
          v-show="search" 
          @click="clearSearch" 
          class="btn btn-secondary"
          type="button"
        >
          Clear
        </button>
      </form>

      <div v-if="loading" class="loading-container">
        <div class="spinner"></div>
        <div class="loading-text">Loading weather data...</div>
      </div>

      <div v-else class="table-container">
        <table class="table-blue">
          <thead>
            <tr>
              <th>City</th>
              <th>Temp (°C)</th>
              <th>Precip (mm)</th>
              <th>Wind (km/h)</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!weatherEntries.length">
              <td colspan="6" class="empty-message">No data found</td>
            </tr>
            <tr v-for="entry in weatherEntries" :key="entry.id">
              <td>
                {{ entry.city }}<span v-if="entry.country">, {{ entry.country }}</span>
              </td>
              <td>{{ formatNumber(entry.temperature) }}</td>
              <td>{{ formatNumber(entry.precipitation) }}</td>
              <td>{{ formatNumber(entry.windSpeed) }}</td>
              <td>{{ formatDate(entry.date) }}</td>
              <td class="action-buttons">
                <router-link :to="`/details/${entry.id}`" class="btn btn-info">Details</router-link>
                <router-link :to="`/edit/${entry.id}`" class="btn btn-edit">Edit</router-link>
                <button @click="deleteEntry(entry.id)" class="btn btn-delete">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="total > perPage" class="pagination">
        <button @click="prevPage" :disabled="page === 1" class="btn btn-primary">Prev</button>
        <span class="page-info">Page {{ page }} of {{ totalPages }}</span>
        <button @click="nextPage" :disabled="page === totalPages" class="btn btn-primary">Next</button>
      </div>
    </div>
  </div>
</template>

<script>
import axios from 'axios'
import '@/assets/styles/weather-list.css'

export default {
  data() {
    return {
      weatherEntries: [],
      loading: true,
      search: '',
      dateFilter: '',
      page: 1,
      perPage: 15,
      total: 0,
      citySuggestions: [],
      showSuggestions: false,
      debounceTimeout: null,
      selectedCountry: '',
      selectedCity: ''
    }
  },
  computed: {
    totalPages() {
      return Math.ceil(this.total / this.perPage) || 1
    }
  },
  mounted() {
    this.fetchEntries()
  },
  methods: {
    fetchEntries() {
      this.loading = true
      axios.get('http://localhost:8000/api/weather', {
        params: {
          page: this.page,
          q: this.selectedCity || this.search,
          country: this.selectedCountry,
          date: this.dateFilter,
          sort: 'date',
          order: 'desc'
        }
      }).then(res => {
        this.weatherEntries = res.data.data
        this.total = res.data.total
        this.perPage = res.data.perPage
      }).finally(() => {
        this.loading = false
      })
    },
    deleteEntry(id) {
      if (confirm('Delete this entry?')) {
        axios.delete(`http://localhost:8000/api/weather/${id}`).then(() => {
          this.fetchEntries()
        })
      }
    },
    formatDate(dt) {
      if (!dt) return ''
      const d = new Date(dt)
      return d.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: '2-digit' })
    },
    formatNumber(val) {
      return val !== undefined && val !== null ? Number(val).toFixed(1) : 'N/A'
    },
    onSearch() {
      if (!this.search || !this.dateFilter) return
      this.page = 1
      this.fetchEntries()
    },
    prevPage() {
      if (this.page > 1) {
        this.page--
        this.fetchEntries()
      }
    },
    nextPage() {
      if (this.page < this.totalPages) {
        this.page++
        this.fetchEntries()
      }
    },
    onCityInput() {
      clearTimeout(this.debounceTimeout)
      if (!this.search) {
        this.citySuggestions = []
        this.showSuggestions = false
        this.selectedCountry = ''
        this.selectedCity = ''
        return
      }
      this.selectedCountry = ''
      this.selectedCity = ''
      this.debounceTimeout = setTimeout(async () => {
        try {
          const res = await axios.get(
            `https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(this.search)}&count=5`
          )
          const results = res.data.results || []
          const seen = new Set()
          this.citySuggestions = results.filter(city => {
            const key = `${city.name}|${city.admin1}|${city.country_code}`
            if (seen.has(key)) return false
            seen.add(key)
            return true
          })
          this.showSuggestions = true
        } catch (error) {
          this.citySuggestions = []
          this.showSuggestions = false
        }
      }, 300)
    },
    selectCitySuggestion(city) {
      this.search = `${city.name}`
      this.selectedCity = city.name
      this.selectedCountry = city.country_code
      this.showSuggestions = false
    },
    hideSuggestions() {
      setTimeout(() => { this.showSuggestions = false }, 200)
    },
    clearSearch() {
      this.search = ''
      this.selectedCity = ''
      this.selectedCountry = ''
      this.citySuggestions = []
      this.showSuggestions = false
      this.fetchEntries()
    }
  }
}
</script>

<style scoped>
.form-control-wrapper {
  position: relative;
  flex: 1;
  min-width: 250px;
}

.suggestion-list {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: var(--primary-dark);
  border: 1px solid var(--primary);
  border-radius: 0 0 var(--radius) var(--radius);
  box-shadow: var(--shadow);
  z-index: 1000;
}

.suggestion-header {
  padding: 0.5rem 1rem;
  font-weight: 600;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.suggestion-items {
  list-style: none;
  margin: 0;
  padding: 0;
  max-height: 250px;
  overflow-y: auto;
}

.suggestion-item {
  padding: 0.75rem 1rem;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  align-items: center;
  transition: background-color 0.2s;
}

.suggestion-item:hover {
  background-color: var(--primary);
}

.city-name {
  font-weight: 600;
}

.city-region {
  color: rgba(255, 255, 255, 0.6);
  font-size: 0.9em;
}

.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
}

.loading-text {
  margin-top: 1rem;
  font-size: 1.1rem;
  color: var(--text-light);
}

.empty-message {
  text-align: center;
  padding: 2rem;
  color: rgba(255, 255, 255, 0.6);
}

.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 1rem;
  margin-top: 2rem;
  padding: 1rem;
}

.page-info {
  font-size: 1.1rem;
  color: var(--text-light);
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
  flex-wrap: nowrap;
}

@media (max-width: 768px) {
  .action-buttons {
    flex-direction: column;
    gap: 0.25rem;
  }
  
  .btn {
    width: 100%;
    margin: 0;
  }
}
</style>