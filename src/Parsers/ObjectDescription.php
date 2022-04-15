<?php

namespace TgScraper\Parsers;

use voku\helper\HtmlDomParser;

/**
 * Class ObjectDescription
 * @package TgScraper\Parsers
 */
class ObjectDescription
{

    /**
     * @var array
     */
    private array $types;

    /**
     * @param string $description
     */
    public function __construct(string $description)
    {
        $this->types = self::parseReturnTypes($description);
    }

    /**
     * @param string $description
     * @return array
     */
    private static function parseReturnTypes(string $description): array
    {
        $returnTypes = [];
        $phrases = explode('.', $description);
        $phrases = array_filter(
            $phrases,
            function ($phrase) {
                return (false !== stripos($phrase, 'returns') or false !== stripos($phrase, 'is returned'));
            }
        );
        foreach ($phrases as $phrase) {
            $dom = HtmlDomParser::str_get_html($phrase);
            $a = $dom->findMulti('a');
            $em = $dom->findMulti('em');
            foreach ($a as $element) {
                if ($element->text() == 'Messages') {
                    $returnTypes[] = 'Array<Message>';
                    continue;
                }
                $arrays = substr_count(strtolower($phrase), 'array');
                $returnType = $element->text();
                for ($i = 0; $i < $arrays; $i++) {
                    $returnType = sprintf('Array<%s>', $returnType);
                }
                $returnTypes[] = $returnType;
            }
            foreach ($em as $element) {
                if (in_array($element->text(), ['False', 'force', 'Array'])) {
                    continue;
                }
                $type = str_replace(['True', 'Int', 'String'], ['bool', 'int', 'string'], $element->text());
                $returnTypes[] = $type;
            }
        }
        return $returnTypes;
    }

    /**
     * @return array
     */
    public function getTypes(): array
    {
        return $this->types;
    }

}