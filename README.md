# TGScraper
A PHP library used to extract JSON data (and auto-generate PHP classes) from [Telegram bot API documentation page](https://core.telegram.org/bots/api).

**Note: the scraper is, obviously, based on a hack and you shouldn't rely on automagically generated files from it, since they are prone to errors. I'll try to fix them ASAP, but manual review is always required (at least for now).**

## Installation

Install the library with composer:

```bash 
  $ composer require sysbot/tgscraper
```

## Using from command line

Once installed, you can use the CLI to interact with the library:
```bash 
  $ vendor/bin/tgscraper
```