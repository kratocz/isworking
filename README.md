# IsWorking

A work hours tracking dashboard that integrates with Toggl API to visualize monthly work progress against customizable thresholds.

## Overview

This PHP-based application displays real-time charts showing cumulative billable hours tracked for a specific client in Toggl, compared against four progressive thresholds: crisis, minimum, optimum, and maximum levels.

## Features

- **Real-time tracking**: Auto-refreshes every 30 seconds to show current work status
- **Visual alerts**: Red background warning when below crisis threshold
- **Smart calculations**: Automatically excludes weekends and holidays from threshold calculations
- **Progressive thresholds**: Four levels (crisis/minimum/optimum/maximum) that scale throughout the month based on working days
- **Chart visualization**: Interactive Chart.js graph showing progress over time
- **Redis caching**: Intelligent API response caching to avoid Toggl API rate limits
- **Docker deployment**: Containerized setup with PHP, Apache, and Redis

## How It Works

1. Fetches time entries from Toggl API v9 for the current month
2. Calculates cumulative billable hours per day
3. Generates progressive threshold lines based on working days (excludes weekends and specific holidays)
4. Displays motivational alerts when falling behind schedule

## Quick Start

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd isworking
   ```

2. **Create `.env` file from template**
   ```bash
   cp .env.example .env
   ```

3. **Configure environment variables in `.env`**
   - `PORT` - Web service port (default: 8844)
   - `TOGGL_API_TOKEN` - Get your token from https://track.toggl.com/profile
   - `TOGGL_CLIENT_NAME` - Client name to filter projects (e.g., "ProfiSMS")
   - `CHART_TITLE` - Dashboard page title
   - `TZ` - Timezone (e.g., "Europe/Prague")

4. **Start Docker services**
   ```bash
   docker compose up -d
   ```

5. **Access the dashboard**

   Open http://127.0.0.1:8844 in your browser

## Configuration

Environment variables are stored in `.env` file (not committed to git):
- `PORT` - Web service port (default: 8844)
- `CHART_TITLE` - Dashboard page title
- `TOGGL_API_TOKEN` - Toggl API authentication token
- `TOGGL_CLIENT_NAME` - Client name to filter projects
- `TZ` - Timezone (e.g., "Europe/Prague")

See `.env.example` for a template.

## Tech Stack

- **Backend**: PHP 7.4 with Apache
- **Frontend**: JavaScript with Chart.js for visualization
- **Caching**: Redis for API response caching
- **Deployment**: Docker Compose
- **API**: Toggl API v9

## Redis Caching

To avoid Toggl API rate limits (30 requests/hour for non-workflow endpoints), the application caches API responses in Redis:

- **Workspace/client data**: Cached for 15 minutes
- **Time entries**: Cached for 30 seconds

### Cache Management

View cached data:
```bash
docker compose exec redis redis-cli KEYS "toggl:*"
```

Clear cache:
```bash
docker compose exec redis redis-cli FLUSHALL
```

## Architecture

- **Web service**: PHP 7.4 Apache with custom Dockerfile (includes Redis extension)
- **Redis service**: Alpine-based Redis for caching
- **Persistent storage**: Redis data volume for cache persistence

## Development

Stop services:
```bash
docker compose down
```

Rebuild after code changes:
```bash
docker compose up -d --build
```

View logs:
```bash
docker compose logs -f web
```

## Security

- **Environment variables**: Sensitive data (API tokens, ports) stored in `.env` file (not committed to git)
- **Error handling**: Generic error messages prevent exposure of internal configuration and credentials
- **Localhost binding**: Service binds to `127.0.0.1` for local-only access
- **Redis caching**: Reduces API calls and prevents rate limit exposure
