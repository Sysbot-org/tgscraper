# TGScraper

[![License](http://poser.pugx.org/sysbot/tgscraper/license)](https://packagist.org/packages/sysbot/tgscraper)
![Required PHP Version](https://img.shields.io/badge/php-%E2%89%A58.0-brightgreen)
[![Latest Stable Version](http://poser.pugx.org/sysbot/tgscraper/v)](https://packagist.org/packages/sysbot/tgscraper)
[![Dependencies](https://img.shields.io/librariesio/github/Sysbot-org/tgscraper)](https://libraries.io/github/Sysbot-org/tgscraper)
[![Code Quality](https://img.shields.io/scrutinizer/quality/g/Sysbot-org/tgscraper)](https://scrutinizer-ci.com/g/Sysbot-org/tgscraper/?branch=master)

A PHP library used to extract JSON data (and auto-generate PHP classes)
from [Telegram bot API documentation page](https://core.telegram.org/bots/api).

**Note: the scraper is, obviously, based on a hack and you shouldn't rely on automagically generated files from it,
since they are prone to errors. I'll try to fix them ASAP, but manual review is always required (at least for now).**

## Installation

Install the library with composer:

```bash 
  $ composer require sysbot/tgscraper
```

## Using from command line

Once installed, you can use the CLI to interact with the library.

For basic help and command list:

```bash 
  $ vendor/bin/tgscraper help
```

### JSON

Extract the latest schema in a human-readable JSON:

```bash 
  $ vendor/bin/tgscraper app:export-schema --readable botapi.json
```

Or, if you want a Postman-compatible JSON (thanks to [davtur19](https://github.com/davtur19/TuriBotGen/blob/master/postman.php)):

```bash 
  $ vendor/bin/tgscraper app:export-schema --postman botapi_postman.json
```

### YAML

Extract the latest schema in YAML format:

```bash 
  $ vendor/bin/tgscraper app:export-schema --yaml botapi.yaml
```

### Stubs

TGScraper can also generate class stubs that you can use in your library. A sample implementation is available in the [Sysbot API module](https://github.com/Sysbot-org/Sysbot-api).

Create stubs in the `out/` directory using `Sysbot\Api` as namespace prefix:

```bash 
  $ vendor/bin/tgscraper app:create-stubs --namespace-prefix "Sysbot\Api" out
```