# The Prodigal Channel

The website for The Prodigal Channel and *The Father Is Not Hiring*, a forthcoming book by Miary Andria.

It is built with plain HTML, CSS and JavaScript, plus one PHP script that saves sign-ups to MySQL. It has no build step and no dependencies.

**Live site:** https://prodigal-son.com/

## Project structure

```
.
├── index.html      Page content, including the article dialogs
├── css/style.css   Styles, including the font declarations
├── js/main.js      Dialog and sign-up form behavior
├── api/signup.php  Saves sign-ups to MySQL
├── db/schema.sql   Database table definition
├── fonts/          Self-hosted web fonts and their licenses
├── images/         Site images
├── logo.html       Brand sheet (not linked from the main page)
└── assets/         Logos, marks and book cover images used by the brand sheet
```

## Running locally

Open `index.html` in a browser, or serve the folder from a local web server:

```sh
python3 -m http.server 8000
```

Then visit http://localhost:8000. The sign-up form needs PHP and MySQL, so it shows an error when run this way.

## Deployment

The site is hosted on Hostinger and uploaded over FTPS. Use TLS 1.2, because uploads larger than about 12 KB fail over TLS 1.3 on this server. The `.git` folder, `.gitignore`, `README.md`, `db/` and `.DS_Store` are not uploaded. After changing the home page, flush the Hostinger CDN cache.

A copy is also published on GitHub Pages at https://miary.github.io/prodigal-web/ from the root of the `main` branch. GitHub Pages can't run PHP, so the sign-up form shows an error on that copy.

## Sign-up form

`js/main.js` posts the form to `api/signup.php`, which saves each entry to the `signups` table. To set up a new database, run `db/schema.sql`.

The script reads its credentials from `api/db-config.php`. That file is kept out of git and blocked from the web by `api/.htaccess`. It has this format:

```php
<?php
return [
  'host' => 'localhost',
  'name' => 'DATABASE_NAME',
  'user' => 'DATABASE_USER',
  'pass' => 'DATABASE_PASSWORD',
];
```

Sign-ups can be viewed in phpMyAdmin in hPanel.

## Credits

Site content © 2026 Miary Andria.

DM Sans and Libre Caslon Display are licensed under the SIL Open Font License. The license texts are in `fonts/`.
