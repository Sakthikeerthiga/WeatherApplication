<template>
  <div class="app-bg">
    <WeatherNavBar @navigate="handleNav"/>
    <div class="main-content">
      <transition name="fade-slide" mode="out-in">
        <router-view />
      </transition>
    </div>
    <footer class="app-footer">
      <span>WeatherApp @2025,May</span>
    </footer>
  </div>
</template>

<script>
import WeatherNavBar from './components/WeatherNavBar.vue';

export default {
  components: { WeatherNavBar },
  methods: {
    handleNav(route) {
      this.$router.push(route)
    }
  }
}
</script>

<style>
@import '@/assets/common.css';
</style>