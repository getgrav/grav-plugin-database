# v1.2.0
## 06/18/2026

1. [](#new)
    * Added an `onDatabaseDrivers` event so third-party plugins can register their own connection types via `$grav['database']->registerDriver()` (see the `database-yetisql` plugin for an example)
2. [](#security)
    * Fixed a potential SQL injection in `tableExists()` by replacing inline table-name interpolation with a parameterized, driver-aware catalog lookup ([GHSA-8jxg-4pw9-xcwf](https://github.com/getgrav/grav/security/advisories/GHSA-8jxg-4pw9-xcwf))
    * Hardened DSN construction against attribute injection from connection settings: reject `;`/line-breaks/null bytes in host/dbname/charset/server/database, cast the port to an integer, and stop embedding PostgreSQL credentials in the DSN ([GHSA-jm58-p4pv-qcwc](https://github.com/getgrav/grav/security/advisories/GHSA-jm58-p4pv-qcwc))

# v1.1.2
## 06/09/2026

1. [](#bugfix)
    * Fixed broken PostgreSQL connections caused by the SSL mode never being applied to the connection settings correctly.
    * Fixed SQL Server connections always requesting encryption regardless of the configured setting.

# v1.1.1
## 04/23/2026

1. [](#new)
    * Added compatibility for Grav 2.0

# v1.1.0
## 04/23/2026

1. [](#new)
    * Named connections: define multiple database connections in plugin config and address them by name — each connection keeps its own driver, host, port, database, credentials, and PDO options. The admin UI exposes per-connection fields so setups with separate read/write or per-service databases no longer need ad-hoc wiring in user code. [#6](https://github.com/getgrav/grav-plugin-database/pull/6)
    * PostgreSQL support alongside MySQL and SQLite via a new `pgsql` driver (default port 5432). Connection configs select driver per-connection, so a single site can mix engines.
2. [](#improved)
    * Driver logic consolidated into the `Database` class with consistent formatting across all drivers, making future driver additions a smaller surface change.
    * All drivers are now registered through Composer autoload instead of hand-wired includes.
    * Expanded README with configuration examples covering single- and multi-connection setups.
3. [](#bugfix)
    * PostgreSQL default port corrected to 5432.
    * Removed unused `text_var` placeholder from the admin blueprint.

# v1.0.2
## 04/27/2020

1. [](#improved)
    * Updated languages

# v1.0.1
## 12/04/2019

1. [](#new)
    * Adding additional PDO constructor's args to allow its "full" db agnostic attributes #1
    * Add username, pwd and options to PDO instances creations #4
    * Added russian language #2

# v1.0.0
## 12/08/2018

1. [](#new)
    * Added support for CLI
    * Added a README.md

# v0.1.0
## 11/01/2018

1. [](#new)
    * ChangeLog started...
