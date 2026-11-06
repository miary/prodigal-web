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
├── admin/          Private sign-up list and reply page
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

The site is hosted on Hostinger and uploaded over FTPS. Use TLS 1.2, because uploads larger than about 12 KB fail over TLS 1.3 on this server. The `.git` and `.impeccable` folders, `.gitignore`, `README.md`, `db/` and `.DS_Store` are not uploaded, and `admin/` goes to its unlisted folder. The Hostinger CDN can cache scripts and styles for up to 7 days, so after changing `js/main.js` or `css/style.css`, raise the `?v=` number on its link in `index.html`.

A copy is also published on GitHub Pages at https://miary.github.io/prodigal-web/ from the root of the `main` branch. GitHub Pages can't run PHP, so the sign-up form shows an error on that copy.

## Sign-up form

`js/main.js` posts the form to `api/signup.php`, which saves each entry to the `signups` table. To set up a new database, run `db/schema.sql`.

To reduce spam, the script uses a hidden trap field, rejects submissions sent within 3 seconds of opening the page or without the time added by `js/main.js`, and limits each IP address to 10 sign-ups per hour. The limits are set at the top of `api/signup.php`. IP addresses are stored only as a keyed hash and deleted after an hour.

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

## Private admin page

`admin/index.php` lists sign-ups and sends personalized replies. Deploy it to an unlisted folder at the web root, not to `admin/`, and don't write that folder name in this repo. It reads the database settings above and `api/admin-config.php`. The page stays closed until that file exists. It is also kept out of git and blocked from the web, and has this format:

```php
<?php
return [
  'password' => 'A_LONG_RANDOM_PASSWORD',
  'mail_from' => 'you@example.com',
  'mail_from_name' => 'Your Name',
];
```

## Credits

Site content © 2026 Miary Andria.

DM Sans and Libre Caslon Display are licensed under the SIL Open Font License. The license texts are in `fonts/`.
