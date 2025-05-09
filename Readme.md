# Weather Application

A full-stack weather application that provides real-time weather data and historical weather tracking capabilities.

## Overview

This application allows users to:

* View live weather data for any location
* Save weather entries for future reference
* Track historical weather data
* Get detailed weather forecasts including hourly and 7-day predictions
* Search and filter weather entries by city and date

## Tech Stack

### Frontend

* Vue.js 3
* Vue Router
* Axios for API calls
* Bootstrap for UI components
* Custom CSS for styling

### Backend

* Symfony 6.x
* Doctrine ORM
* SQLite
* Open-Meteo API for weather data

## Features

### Live Weather

* Real-time weather data fetching
* City search with autocomplete
* Current conditions display
* Hourly forecast
* 7-day forecast
* Sunrise/sunset times
* Temperature, precipitation, wind speed, and humidity data

### Weather Management

* CRUD operations for weather entries
* Search and filter capabilities
* Pagination for large datasets
* Detailed weather view
* Edit and delete functionality

### Location Features

* City search with geocoding
* Current location detection
* Country code support
* Coordinate-based weather fetching

## Setup Instructions

### Backend Setup

1. Install PHP 8.1+ and Composer
2. Install MySQL/MariaDB
3. Clone the repository
4. Navigate to the backend directory:

   ```bash
   cd backend
   ```
5. Install dependencies:

   ```bash
   composer install
   ```
6. Configure your database in `.env`:

   ```
   DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_%kernel.environment%.d"
   ```
7. Run migrations:

   ```bash
   php bin/console doctrine:database:create
   php bin/console make:migration
   php bin/console doctrine:migrations:migrate
   ```
8. Start the Symfony server:

   ```bash
   symfony server:start

	or  

   php -S localhost:8000 -t public
   ```

### Frontend Setup

1. Install Node.js 16+
2. Navigate to the frontend directory:

   ```bash
   cd frontend
   ```
3. Install dependencies:

   ```bash
   npm install
   ```
4. Start the development server:

   ```bash
   npm run dev
   ```

## API Usage

The backend provides the following API endpoints:

* `GET /api/weather` - List weather entries with pagination
* `GET /api/weather/{id}` - Get weather entry details
* `POST /api/weather` - Create new weather entry
* `PUT /api/weather/{id}` - Update weather entry
* `DELETE /api/weather/{id}` - Delete weather entry
* `POST /api/weather/fetch` - Fetch weather data for a location
* `POST /api/weather/live-fetch` - Fetch live weather data

## Database Schema

The application uses a single `weather_entry` table with the following structure:

* `id` (Primary Key)
* `city` (String)
* `country` (String)
* `latitude` (Float)
* `longitude` (Float)
* `date` (DateTime)
* `temperature` (Float)
* `precipitation` (Float)
* `wind_speed` (Float)
* `weathercode` (Integer)
* `sunrise` (DateTime)
* `sunset` (DateTime)
* `hourly_data` (JSON)
* `humidity` (Float)
* `daily_forecast` (JSON)
* `updated_at` (DateTime)

###  Live Weather Page
![1](https://github.com/user-attachments/assets/95fd2648-da01-4f49-a3ad-cf91dc293260)


###  Save Live weather
![2](https://github.com/user-attachments/assets/53da5f90-70a5-40d0-a22b-8583b0e265ea)

## Add weather Manually
![3](https://github.com/user-attachments/assets/fedc9245-7a97-406a-b73b-337f58bd8b08)

## Weather listing 
![screencapture-localhost-5173-list-2025-05-09-09_21_10](https://github.com/user-attachments/assets/246e2364-6d1a-42b7-967e-d0af0d9837ea)

## License

This project is licensed under the MIT License - see the LICENSE file for details.
