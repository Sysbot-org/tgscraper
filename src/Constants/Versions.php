<?php


namespace TgScraper\Constants;


class Versions
{

    public const V100 = 'https://web.archive.org/web/20150714025308/https://core.telegram.org/bots/api/';
    public const V110 = 'https://web.archive.org/web/20150812125616/https://core.telegram.org/bots/api';
    public const V140 = 'https://web.archive.org/web/20150909214252/https://core.telegram.org/bots/api';
    public const V150 = 'https://web.archive.org/web/20150921091215/https://core.telegram.org/bots/api/';
    public const V160 = 'https://web.archive.org/web/20151023071257/https://core.telegram.org/bots/api';
    public const V180 = 'https://web.archive.org/web/20160112101045/https://core.telegram.org/bots/api';
    public const V182 = 'https://web.archive.org/web/20160126005312/https://core.telegram.org/bots/api';
    public const V183 = 'https://web.archive.org/web/20160305132243/https://core.telegram.org/bots/api';
    public const V200 = 'https://web.archive.org/web/20160413101342/https://core.telegram.org/bots/api';
    public const V210 = 'https://web.archive.org/web/20160912130321/https://core.telegram.org/bots/api';
    public const V211 = self::V210;
    public const V220 = 'https://web.archive.org/web/20161004150232/https://core.telegram.org/bots/api';
    public const V230 = 'https://web.archive.org/web/20161124162115/https://core.telegram.org/bots/api';
    public const V231 = 'https://web.archive.org/web/20161204181811/https://core.telegram.org/bots/api';
    public const V300 = 'https://web.archive.org/web/20170612094628/https://core.telegram.org/bots/api';
    public const V310 = 'https://web.archive.org/web/20170703123052/https://core.telegram.org/bots/api';
    public const V320 = 'https://web.archive.org/web/20170819054238/https://core.telegram.org/bots/api';
    public const V330 = 'https://web.archive.org/web/20170914060628/https://core.telegram.org/bots/api';
    public const V350 = 'https://web.archive.org/web/20171201065426/https://core.telegram.org/bots/api';
    public const V360 = 'https://web.archive.org/web/20180217001114/https://core.telegram.org/bots/api';
    public const V400 = 'https://web.archive.org/web/20180728174553/https://core.telegram.org/bots/api';
    public const V410 = 'https://web.archive.org/web/20180828155646/https://core.telegram.org/bots/api';
    public const V420 = 'https://web.archive.org/web/20190417160652/https://core.telegram.org/bots/api';
    public const V430 = 'https://web.archive.org/web/20190601122107/https://core.telegram.org/bots/api';
    public const V440 = 'https://web.archive.org/web/20190731114703/https://core.telegram.org/bots/api';
    public const V450 = 'https://web.archive.org/web/20200107090812/https://core.telegram.org/bots/api';
    public const V460 = 'https://web.archive.org/web/20200208225346/https://core.telegram.org/bots/api';
    public const V470 = 'https://web.archive.org/web/20200401052001/https://core.telegram.org/bots/api';
    public const V480 = 'https://web.archive.org/web/20200429054924/https://core.telegram.org/bots/api';
    public const V490 = 'https://web.archive.org/web/20200611131321/https://core.telegram.org/bots/api';
    public const V500 = 'https://web.archive.org/web/20201104151640/https://core.telegram.org/bots/api';
    public const V510 = 'https://web.archive.org/web/20210315055600/https://core.telegram.org/bots/api';
    public const V520 = 'https://web.archive.org/web/20210428195636/https://core.telegram.org/bots/api';
    public const V530 = 'https://web.archive.org/web/20210626142851/https://core.telegram.org/bots/api';
    public const LATEST = 'https://core.telegram.org/bots/api';


    public static function getVersionFromText(string $text): string
    {
        $text = str_replace('.', '', $text);
        $const = sprintf('%s::V%s', self::class, $text);
        if (defined($const)) {
            return constant($const);
        }
        return self::LATEST;
    }

}