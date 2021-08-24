<?php


namespace TgScraper\Constants;


class Versions
{

    public const V100 = '1.0.0';
    public const V110 = '1.1.0';
    public const V140 = '1.4.0';
    public const V150 = '1.5.0';
    public const V160 = '1.6.0';
    public const V180 = '1.8.0';
    public const V182 = '1.8.2';
    public const V183 = '1.8.3';
    public const V200 = '2.0.0';
    public const V210 = '2.1.0';
    public const V211 = '2.1.1';
    public const V220 = '2.2.0';
    public const V230 = '2.3.0';
    public const V231 = '2.3.1';
    public const V300 = '3.0.0';
    public const V310 = '3.1.0';
    public const V320 = '3.2.0';
    public const V330 = '3.3.0';
    public const V350 = '3.5.0';
    public const V360 = '3.6.0';
    public const V400 = '4.0.0';
    public const V410 = '4.1.0';
    public const V420 = '4.2.0';
    public const V430 = '4.3.0';
    public const V440 = '4.4.0';
    public const V450 = '4.5.0';
    public const V460 = '4.6.0';
    public const V470 = '4.7.0';
    public const V480 = '4.8.0';
    public const V490 = '4.9.0';
    public const V500 = '5.0.0';
    public const V510 = '5.1.0';
    public const V520 = '5.2.0';
    public const V530 = '5.3.0';
    public const LATEST = 'latest';
    public const STABLE = self::V530;

    public const URLS = [
        self::V100 => 'https://web.archive.org/web/20150714025308id_/https://core.telegram.org/bots/api/',
        self::V110 => 'https://web.archive.org/web/20150812125616id_/https://core.telegram.org/bots/api',
        self::V140 => 'https://web.archive.org/web/20150909214252id_/https://core.telegram.org/bots/api',
        self::V150 => 'https://web.archive.org/web/20150921091215id_/https://core.telegram.org/bots/api/',
        self::V160 => 'https://web.archive.org/web/20151023071257id_/https://core.telegram.org/bots/api',
        self::V180 => 'https://web.archive.org/web/20160112101045id_/https://core.telegram.org/bots/api',
        self::V182 => 'https://web.archive.org/web/20160126005312id_/https://core.telegram.org/bots/api',
        self::V183 => 'https://web.archive.org/web/20160305132243id_/https://core.telegram.org/bots/api',
        self::V200 => 'https://web.archive.org/web/20160413101342id_/https://core.telegram.org/bots/api',
        self::V210 => 'https://web.archive.org/web/20160912130321id_/https://core.telegram.org/bots/api',
        self::V211 => 'https://web.archive.org/web/20160912130321id_/https://core.telegram.org/bots/api',
        self::V220 => 'https://web.archive.org/web/20161004150232id_/https://core.telegram.org/bots/api',
        self::V230 => 'https://web.archive.org/web/20161124162115id_/https://core.telegram.org/bots/api',
        self::V231 => 'https://web.archive.org/web/20161204181811id_/https://core.telegram.org/bots/api',
        self::V300 => 'https://web.archive.org/web/20170612094628id_/https://core.telegram.org/bots/api',
        self::V310 => 'https://web.archive.org/web/20170703123052id_/https://core.telegram.org/bots/api',
        self::V320 => 'https://web.archive.org/web/20170819054238id_/https://core.telegram.org/bots/api',
        self::V330 => 'https://web.archive.org/web/20170914060628id_/https://core.telegram.org/bots/api',
        self::V350 => 'https://web.archive.org/web/20171201065426id_/https://core.telegram.org/bots/api',
        self::V360 => 'https://web.archive.org/web/20180217001114id_/https://core.telegram.org/bots/api',
        self::V400 => 'https://web.archive.org/web/20180728174553id_/https://core.telegram.org/bots/api',
        self::V410 => 'https://web.archive.org/web/20180828155646id_/https://core.telegram.org/bots/api',
        self::V420 => 'https://web.archive.org/web/20190417160652id_/https://core.telegram.org/bots/api',
        self::V430 => 'https://web.archive.org/web/20190601122107id_/https://core.telegram.org/bots/api',
        self::V440 => 'https://web.archive.org/web/20190731114703id_/https://core.telegram.org/bots/api',
        self::V450 => 'https://web.archive.org/web/20200107090812id_/https://core.telegram.org/bots/api',
        self::V460 => 'https://web.archive.org/web/20200208225346id_/https://core.telegram.org/bots/api',
        self::V470 => 'https://web.archive.org/web/20200401052001id_/https://core.telegram.org/bots/api',
        self::V480 => 'https://web.archive.org/web/20200429054924id_/https://core.telegram.org/bots/api',
        self::V490 => 'https://web.archive.org/web/20200611131321id_/https://core.telegram.org/bots/api',
        self::V500 => 'https://web.archive.org/web/20201104151640id_/https://core.telegram.org/bots/api',
        self::V510 => 'https://web.archive.org/web/20210315055600id_/https://core.telegram.org/bots/api',
        self::V520 => 'https://web.archive.org/web/20210428195636id_/https://core.telegram.org/bots/api',
        self::V530 => 'https://web.archive.org/web/20210626142851id_/https://core.telegram.org/bots/api',
        self::LATEST => 'https://core.telegram.org/bots/api'
    ];

    public static function getVersionFromText(string $text): string
    {
        $text = str_replace(['.', 'v'], ['', ''], strtolower($text));
        $const = sprintf('%s::V%s', self::class, $text);
        if (defined($const)) {
            return constant($const);
        }
        return self::LATEST;
    }

    public static function getUrlFromText(string $text): string
    {
        $version = self::getVersionFromText($text);
        return self::URLS[$version] ?? self::URLS[self::LATEST];
    }

}