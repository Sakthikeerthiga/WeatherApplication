<template>
  <nav class="sidebar-nav">
    <div class="nav-logo">WeatherApp</div>
    <router-link to="/" class="nav-item" exact-active-class="active">Live Weather Data</router-link>
    <router-link to="/list" class="nav-item" exact-active-class="active">Weather List</router-link>
    <router-link to="/add" class="nav-item" exact-active-class="active">Add Weather Data</router-link>
  </nav>
</template>