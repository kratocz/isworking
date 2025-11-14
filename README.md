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

## How It Works

1. Fetches time entries from Toggl API v9 for the current month
2. Calculates cumulative billable hours per day
3. Generates progressive threshold lines based on working days (excludes weekends and specific holidays)
4. Displays motivational alerts when falling behind schedule

## Configuration

Required environment variables:
- `CHART_TITLE` - Dashboard page title
- `TOGGL_API_TOKEN` - Toggl API authentication token
- `TOGGL_CLIENT_NAME` - Client name to filter projects
- `TZ` - Timezone (e.g., "Europe/Prague")

## Tech Stack

- PHP (backend API)
- JavaScript (Chart.js for visualization)
- Toggl API v9
