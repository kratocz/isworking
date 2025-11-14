# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a work hours tracking dashboard that integrates with Toggl API to visualize monthly work progress against various thresholds (crisis/minimum/optimum/maximum levels). The application displays real-time charts and alerts based on billable hours tracked for a specific client.

## Architecture

### Backend (PHP)
- **API Endpoint**: `/api/v1/month/index.php` - Main API that fetches data from Toggl and calculates thresholds
  - Integrates with Toggl API v9 (`https://api.track.toggl.com`)
  - Requires environment variables: `TOGGL_API_TOKEN`, `TOGGL_CLIENT_NAME`, `TZ`
  - Returns JSON with chart data and metadata (current work status, hours above thresholds)

- **CalendarTools** (`/api/v1/month/CalendarTools.php`) - Utility class for calendar calculations
  - Calculates work percentages per day, excluding weekends (Saturday/Sunday) and holidays (12-24, 12-25, 12-26, 01-01)
  - Reserves last 2 days of month as non-working days
  - Used to generate progressive thresholds throughout the month

### Frontend
- **Main Page**: `index.php` - Dashboard HTML structure
  - Displays chart title from `CHART_TITLE` environment variable
  - Auto-refreshes every 30 seconds

- **Chart Logic**: `chart.js` - Client-side visualization
  - Uses Chart.js library (`/lib/chart.umd.min.js`) for rendering
  - Fetches data from `/api/v1/month/` endpoint
  - Updates UI with work status, alerts, and hours above/below thresholds
  - Visual alerts: red background when below crisis threshold

### Threshold System
The API calculates four progressive thresholds based on working days in the month:
- **Crisis (red)**: Starts at ~140h/month with special calculation to stay flat longer
- **Minimum (yellow)**: 140h scaled by work percentage
- **Optimum (green)**: 170h scaled by work percentage
- **Maximum (blue)**: 200h scaled by work percentage

Percentages are calculated by `CalendarTools::getPercentagesForDaysInMonth()` which counts working days (excluding weekends and specific holidays).

## Key Data Flow

1. Browser loads `index.php` → initializes chart and starts 30s refresh interval
2. JavaScript calls `/api/v1/month/` every 30 seconds
3. PHP API:
   - Authenticates with Toggl API using `TOGGL_API_TOKEN`
   - Finds workspace and client by `TOGGL_CLIENT_NAME`
   - Fetches time entries for current month
   - Calculates cumulative billable hours per day
   - Generates threshold lines using `CalendarTools`
   - Checks if currently working (has active time entry)
4. Returns JSON with chart datasets and metadata
5. JavaScript updates chart and displays alerts

## Environment Variables

Required in production environment:
- `CHART_TITLE` - Dashboard page title
- `TOGGL_API_TOKEN` - Toggl API authentication token
- `TOGGL_CLIENT_NAME` - Client name to filter projects
- `TZ` - Timezone (e.g., "Europe/Prague")

## Deployment

Production deployment location: `sftp://krato@router.kratonet.cz/www/nepracuje`

### Deployment Workflow

1. **Update production server from Git:**
   ```bash
   ssh krato@router.kratonet.cz 'cd /www/nepracuje && git pull'
   ```

2. **Sync files from production to local (for inspection):**
   ```bash
   rsync -avz --exclude='.git' krato@router.kratonet.cz:/www/nepracuje/ ./
   ```

3. **Important notes:**
   - If git pull fails due to local changes on server, use `git clean -fd && git reset --hard && git pull` to force update
   - Always commit and push local changes before syncing to production
   - Check file ownership if git operations fail (`.git` directory must be owned by `krato`)

## Security

- **API Token Security**: `TOGGL_API_TOKEN` is read from environment variables (`getenv()` in `api/v1/month/index.php:39`), not hardcoded in source code
- **Environment Variables**: All sensitive configuration is managed via environment variables, not committed to repository
- **No .env file**: Environment variables are set at the server/webserver level, not via `.env` files

## Testing

`test.php` - Simple timezone diagnostic script (outputs current timezone)
