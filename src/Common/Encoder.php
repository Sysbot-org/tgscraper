<?php

namespace TgScraper\Common;

use Symfony\Component\Yaml\Yaml;

class Encoder
{
    /**
     * @param mixed $data
     * @param int $options
     * @param bool $readable
     * @return string
     */
    public static function toJson(mixed $data, int $options = 0, bool $readable = false): string
    {
        if ($readable) {
            $options |= JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        }
        return json_encode($data, $options);
    }

    /**
     * @param mixed $data
     * @param int $inline
     * @param int $indent
     * @param int $flags
     * @return string
     */
    public static function toYaml(mixed $data, int $inline = 16, int $indent = 4, int $flags = 0): string
    {
        return Yaml::dump($data, $inline, $indent, $flags);
    }
}