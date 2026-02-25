# OpenEMR Institutional Module

A module for institutional settings such as E.R. and/or hospital environments.

## Description

This OpenEMR custom module extends the platform with features tailored for institutional healthcare settings including emergency rooms and hospitals.

## Requirements

- OpenEMR 7.0+
- PHP 8.1+

## Installation

1. Copy or clone this repository into `interface/modules/custom_modules/oe-module-institutional` inside your OpenEMR installation.
2. Navigate to **Admin → Modules** in OpenEMR and enable the **Institutional Module**.

## Directory Structure

```
oe-module-institutional/
├── openemr.bootstrap.php     # Module bootstrap (loaded by OpenEMR)
├── composer.json             # Composer metadata and autoload configuration
├── public/
│   ├── index.php             # Module entry point
│   ├── css/
│   │   └── institutional.css
│   └── js/
│       └── institutional.js
├── src/
│   ├── BootstrapService.php  # Event subscriptions and menu registration
│   └── Controller/
│       └── InstitutionalController.php
└── templates/
    └── index.html.twig       # Twig template for the module dashboard
```

## License

GNU General Public License v3 – see [LICENSE](https://github.com/openemr/openemr/blob/master/LICENSE).

## Author

Jerry Padgett &lt;sjpadgett@gmail.com&gt;
