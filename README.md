# The Prodigal Channel

The website for The Prodigal Channel and *The Father Is Not Hiring*, a forthcoming book by Miary Andria.

It is a static site built with plain HTML, CSS and JavaScript. It has no build step, no dependencies and no external services.

**Live site:** https://miary.github.io/prodigal-web/

## Project structure

```
.
├── index.html      Page content, including the article dialogs
├── css/style.css   Styles, including the font declarations
├── js/main.js      Dialog and sign-up form behavior
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

Then visit http://localhost:8000.

## Deployment

The site is hosted on GitHub Pages and published from the root of the `main` branch. Each push to `main` updates the live site.

## Sign-up form

The sign-up form is not connected to a backend. It stores and sends nothing, and it tells visitors that sign-ups are not open yet. To collect sign-ups, connect the form's submit handler in `js/main.js` to a backend.

## Credits

Site content © 2026 Miary Andria.

DM Sans and Libre Caslon Display are licensed under the SIL Open Font License. The license texts are in `fonts/`.
