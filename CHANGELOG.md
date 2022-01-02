# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [2.1.0] - 2022-01-02
### Added
- Support attributes added to the config. Allows an implementation to add driver
  options that will be included if a connection has to be reconnected.
  Add supported driver options as an array on the `attributes` config key.
### Changed
- Stop using a prepared statement when setting charset and timezone.
  `SET NAMES` isn't supported when using native prepared statements.
- Update `ping()` to support native types.

## [2.0.0] - 2021-08-07
### Added
- Add specific support for PHP v8.
- Type declarations have been added to all method parameters and return types
  where possible. Some methods return mixed type so docblocks are still used.
### Fixed
- **BC break**: Automatically quote identifiers used in data parameters for
  `insert()` and `update()`. Implementations must remove any manual identifier 
  quotes that they were previously forced to include manually.
- `RuntimeException::createFromException()` no longer wraps an instance of itself.
### Changed
- **BC break**: `QuoteHandler` construct requires a `Closure` rather than simply
  allowing any callable. The signature is definied to accept a mixed parameter
  and return a string.
- **BC break**: `QuoteHandler::columnAs()` and `QuoteHandler::tableAs()` no 
  longer accept `null` as a value for *alias*. This unexpected behaviour is
  removed by adding type declarations.
### Removed
- **BC break**: Removed support for PHP versions <= v7.3 as they are no longer
  [actively supported](https://php.net/supported-versions.php) by the PHP project.
- **BC break**: Removed *type* parameter from `QuoteHandler::value()` (and
  therefore also `QuoteHandler::into()`). This method is designed to
  automatically handle the type, and remove the need to instruct it.
- Previous deprecated *bind* parameter for `select()`, `update()` and
  `delete()`, and passing a string to the *where* parameter.

## [1.2.0] - 2021-08-01
### Added
- New method on adapter `upsert()` for performing an insert/on duplicate update
  of a single row.

## [1.1.0] - 2017-11-15
### Added
- New class `SqlFragment` can be used when passing raw SQL to the quoter. The
  quoter already supports objects (on which it will call `__toString()`), this
  class simply helps with the implementation.
- Full quoter support added to *data* parameter of `insert()` and `update()`,
  providing handling for objects and numbers.
- Full quoter support added to *where* parameter of `select()`, `insert()`,
  `update()` and `delete()` when passed as an array, providing handling for
  objects, numbers and arrays.
### Deprecated
- For `select()`, `insert()`, `update()` and `delete()`, the *bind* parameter,
  and passing a string to the *where* parameter are deprecated in favour of
  providing an array for *where*.

## [1.0.0] - 2017-04-10
### Changed
- Removed *quote* prefix from QuoteHandler method names, for better chaining
  from Adapter, eg. `$adapter->quote()->into()`
### Removed
- Drop support for PHP 5.5
- Helpers have been migrated to the `phlib/db-helper` package: `BulkInsert`,
  `BigResult`, `QueryPlanner`, `Replication`
- Remove `CrudInterface`, `QuoteableInterface` and `QuoteableAdapterInterface`
- Remove QuoteHandler pass-through and setter methods from Adapter. Instead use
  QuoteHandler directly by chaining, eg. `$adapter->quoteInto()` is replaced
  with `$adapter->quote()->into()`
- Remove `Adapter/Crud` class, replaced by `CrudTrait` on the Adapter

## [0.0.5] - 2017-01-03
### Fixed
- Remove config platform from Composer file

## [0.0.4] - 2016-08-02
### Added
- README: Add timezone values to the options table and added link to manual
### Changed
- Remove unneeded [at]dev on console composer dependency
- Update console process dependency to latest version
- Changed exception implementation to replicate behaviour of current PDO
  Exception. The original PDO code is now set on the exception class replicating
  the behaviour seen in PDOException. This removes the now defunct method
  `getPDOCode()`.
### Fixed
- Fix unexpected PDOException code param behaviour

## [0.0.3] - 2016-08-01
### Changed
- Dependency versions

## [0.0.2] - 2016-04-22
### Fixed
- Dependencies
- Adapter interface implementation naming

## [0.0.1] - 2016-04-13
Initial alpha release
