# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).


## [Unreleased]
### Added
- Support for OpenAPI schema: you can now generate code in any language!
- Support for the new [sysbot/tgscraper-cache](https://github.com/Sysbot-org/tgscraper-cache) package: if installed, TGScraper will be much faster (there is no need to always fetch the live webpages)!
- You can now validate a schema by using the `validateSchema` method, provided by the `TgScraper` class.
- New `Versions::STABLE` constant: it will automatically return the latest stable version instead of the live version (useful for the cache package).
- Added the JSON schema specification for the custom format provided by TGScraper.
- Added this changelog.
- Added a workflow to automatically generate the bot API schema every day.

### Changed
- The `Generator` class has now been renamed `TgScraper`.
- The `required` property in method fields has been replaced by the new `optional` property, for the sake of consistency.
- You now need a schema in order to instantiate the `TgScraper` class (don't worry, you can use the new methods `TgScraper::fromUrl` and `TgScraper::fromVersion`).
- The `Versions` class constants have been replaced with an actual version string. If you still need the URLs, use the new class constant `Versions::URLS`.
- TGScraper will now only return arrays. If you still need JSON or YAML encoding, please use the new `Encoder` class.
- Default inline value for YAML has been changed to 16.

### Fixed
- Minor improvements to `StubCreator`.
- Fixed an issue with the CLI where `autoload.php` couldn't be found.
- When exporting the schema, the CLI will now make sure that the destination directory exists.

### Security
- When a custom schema is used, only use `version`, `types` and `methods` fields.

## [2.1.0] - 2021-07-31
### Added
- New repo workflows: automatic package build (and push to the GitHub registry), and automatic notifications via Telegram.
- New `version` field for schemas: it contains the bot API version (if possible).
- New `extended_by` field for types: if the current type is a parent one, it will contain its child types.

### Changed
- Now all type stubs implement the base `TypeInterface` interface.
- Children type stubs now extend their parent.
- Optional fields are now actually optional in the Postman collection (previously, you had to manually disable optional ones).

### Fixed
- Minor improvements to the schema extractor.

## [2.0.1] - 2021-07-24
### Changed
- The README now includes many CLI examples.

### Fixed
- The link for the Bot API 5.2.0 snapshot was broken, it's working now.

## [2.0.0] - 2021-07-24
### Added
- Support for Postman collection: now you can generate a JSON to use in Postman!
- New class for the URLs of various bot API snapshots: `TgScraper\Constants\Versions`. 

### Changed
- Moved `TgScraper\StubCreator` to the new namespace `TgScraper\Common\StubCreator`.
- Moved scraping logic from `TgScraper\Generator` to the new class `TgScraper\Common\SchemaExtractor`.
- CLI has been completely reworked: it now uses the Symfony Console and it's much more reliable!

## [1.4.0] - 2021-06-23
### Added
- YAML format is now supported.
- Docker support! The package is published on the GitHub registry.

## [1.3.0] - 2021-06-22
### Added
- Badges in the README! They contain a lot of useful information about the project (such as the minimum PHP version, latest stable version, etc).

### Fixed
- CLI now catches exceptions more reliably.

### Security
- Dependency `paquettg/php-html-parser` has been upgraded to `^3.1`.

## [1.2.2] - 2021-06-20
### Fixed
- Fixed a typo in a property name of the `Response` class stub.

## [1.2.1] - 2021-06-19
### Removed
- The abstract constructor for the `API` trait stub has now been removed.

## [1.2.0] - 2021-06-19
### Changed
- The `API` class stub has been converted to a trait, and the constructor and the `sendRequest` methods are now abstract.

### Fixed
- Minor improvements to the CLI.

## [1.1.0] - 2021-06-18
### Added
- New field for types: `optional`. It tells whether a value is always present or not.
- Class stubs now have typed properties (with related PHPDoc comments).

### Changed
- Variable names have been changed to `camelCase`.

## [1.0.2] - 2021-06-18
### Fixed
- Improved argument parsing for the CLI.

## [1.0.1] - 2021-06-17
### Changed
- Project license is now the GNU Lesser GPL.

## [1.0.0] - 2021-06-17
### Added
- New CLI to easily generate JSON schema or class stubs!
- It's now possible to parse old bot API webpages! Pass the URL and it should work just fine.
- New API class stub, it implements all bot API methods (it's incomplete though, so you must add your custom logic).

### Changed
- Renamed project to `tgscraper`.
- Class namespace is now `TgScraper`, for the sake of consistency.
- `StubProvider` class is now named `StubCreator`.
- Reworked syntax for array of objects in field types: `Object[]` has been replaced by `Array<Object>`. 

### Removed
- Method stubs will no longer be generated.

### Fixed
- The parser is now more reliable, it no longer needs to be updated at every bot API release!

[Unreleased]: https://github.com/Sysbot-org/tgscraper/compare/2.1...HEAD
[2.1.0]: https://github.com/Sysbot-org/tgscraper/compare/2.0.1...2.1
[2.0.1]: https://github.com/Sysbot-org/tgscraper/compare/2.0...2.0.1
[2.0.0]: https://github.com/Sysbot-org/tgscraper/compare/1.4...2.0
[1.4.0]: https://github.com/Sysbot-org/tgscraper/compare/1.3...1.4
[1.3.0]: https://github.com/Sysbot-org/tgscraper/compare/1.2.1...1.3
[1.2.1]: https://github.com/Sysbot-org/tgscraper/compare/1.2...1.2.1
[1.2.0]: https://github.com/Sysbot-org/tgscraper/compare/1.1...1.2
[1.1.0]: https://github.com/Sysbot-org/tgscraper/compare/1.0.2...1.1
[1.0.2]: https://github.com/Sysbot-org/tgscraper/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/Sysbot-org/tgscraper/compare/1.0...1.0.1
[1.0.0]: https://github.com/Sysbot-org/tgscraper/releases/tag/1.0