import { createRouter, createWebHistory } from 'vue-router'
import LiveWeather from '../components/LiveWeather.vue'
import WeatherDetails from '../components/WeatherDetails.vue'
import WeatherForm from '../components/WeatherForm.vue'
import WeatherList from '../components/WeatherList.vue'

const routes = [
  { path: '/', component: LiveWeather, props: true },
  { path: '/list', component: WeatherList },
  { path: '/add', component: WeatherForm },
  { path: '/edit/:id', component: WeatherForm, props: true },
  { path: '/details/:id', component: WeatherDetails, props: true },
  { path: '/live-weather', component:LiveWeather,props: true }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

export default router