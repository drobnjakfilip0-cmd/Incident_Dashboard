# Incident Dashboard

A small security incident dashboard built as a demo project.

The application displays incidents in a table and allows filtering by severity. The frontend uses Vanilla JavaScript, while PHP provides the backend API and SQLite stores the data.

## Stack

* **Frontend:** HTML, CSS, Vanilla JavaScript
* **Backend:** PHP 8 + PDO
* **Database:** SQLite
* **API:** JSON over HTTP

## Features

* Display security incidents
* Filter incidents by severity
* Fetch data from the PHP API
* Loading and error states
* Responsive layout
* Light and dark theme
* Safe rendering of data using `textContent`

## Project Structure

```text
blade/
├── index.html
├── style.css
├── app.js
├── db.php
├── api/
│   └── incidents.php
└── blade.sqlite
```

### Main files

* `index.html` - page structure
* `style.css` - styling and responsive layout
* `app.js` - frontend logic and API requests
* `db.php` - database connection and initialization
* `api/incidents.php` - backend API for retrieving incidents
* `blade.sqlite` - SQLite database created automatically

## How to Run

### 1. Check PHP

You need PHP 8 or newer with SQLite support.

```bash
php -v
php -m | grep pdo_sqlite
```

### 2. Open the project directory

```bash
cd ~/Desktop/blade
```

### 3. Start the PHP development server

```bash
php -S localhost:8000
```

### 4. Open the application

Open:

```text
http://localhost:8000
```

The database is created automatically on the first request.

To stop the server:

```text
Ctrl + C
```

## API

The frontend communicates with:

```text
GET /api/incidents.php
```

Example:

```text
/api/incidents.php?severity=critical&limit=25
```

The API returns JSON containing the incidents and the total number of matching records.

## Notes

This is a demo/teaching project. SQLite is used to keep the application simple and portable. In a production system, a database such as MySQL or PostgreSQL would typically be used.

The project has no framework, build step, or package manager.
