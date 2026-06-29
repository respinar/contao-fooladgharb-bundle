# Fooladgharb Request Bundle

A Contao 5 bundle for storing website requests in the `tl_requests` table and managing them from the Contao backend.

## Features

- Adds `tl_requests` as a form target table.
- Adds a backend module under **Fooladgharb > Requests**.
- Shows submitted requests in a Contao backend list view.
- Exports all requests as downloadable CSV files.
- Exports all requests as downloadable Excel-compatible `.xls` files.
- Includes backend export icons.
- Includes English and Persian backend language files.

## Requirements

- PHP `^8.3`
- Contao Core Bundle `^5.3`

## Installation

Install the bundle with Composer:

```bash
composer require respinar/fooladgharb-request-bundle
```

Update the database from the Contao install tool or with:

```bash
php bin/console contao:migrate
```

Install public bundle assets so the backend export icons are available:

```bash
php bin/console assets:install public
```

Clear the cache if needed:

```bash
php bin/console cache:clear
```

## Backend Usage

Open the Contao backend and go to:

```text
Fooladgharb > Requests
```

The module lists submitted requests from `tl_requests`.

Use the header buttons to download exports:

- **Export CSV** downloads `requests.csv`
- **Export Excel** downloads `requests.xls`

## Form Integration

The bundle adds `tl_requests` to the Contao form generator target table options.

Create or edit a form in the Contao backend, enable form data storage, and select:

```text
tl_requests
```

## Exported Fields

The CSV and Excel exports include:

- ID
- Name
- Phone
- Email
- Province
- Product
- Type
- Usage
- Insulation
- Area
- Message
- Date
- Time
- URL
- Lead ID
- Client ID
- Created

## Languages

The bundle includes backend translations for:

- English: `contao/languages/en`
- Persian: `contao/languages/fa`

## Development Notes

After changing files in `public/`, run:

```bash
php bin/console assets:install public
```

The export callbacks use Contao's `ResponseException` so downloads are sent directly to the browser from backend `key=` actions.

## License

MIT
