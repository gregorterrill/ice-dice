# Ice & Dice

Website for a fictional board game café and bar. Built as a demo for my Dot All 2023 conference talk about extending Craft's search functionality.

## Tools

This project is built with:

* [Craft CMS](https://craftcms.com) - Flexible CMS for building bespoke websites.
* [Tailwind CSS](https://tailwindcss.com) - Utility-first CSS framework.
* [Laravel Mix](https://laravel-mix.com) - Simpler wrapper for Webpack, used to build/compile/minify CSS/JS files.

## Installation

To set up a local copy of this website, run:

```
composer install
```

This will create a `/vendor/` folder containing the required version of Craft and all necessary plugins. Next, run:

```
npm install
```

This will create a `/node_modules/` folder containing all necessary front-end packages.

Create a database and serve the `/web/` directory using your local server (Valet, Herd, MAMP, etc). A copy of the database is included in the root directory.

Copy the `.env.sample` file to `.env`, changing the DB credentials, URLs, and paths in your local copy of the file to match your local setup.

The admin login credentials for Craft are admin / password123.