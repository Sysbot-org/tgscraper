<?php

namespace TgScraper\Parsers;

use voku\helper\HtmlDomParser;

class FieldDescription
{

    private HtmlDomParser $dom;

    public function __construct(string $description)
    {
        $this->dom = HtmlDomParser::str_get_html($description);
        foreach ($this->dom->find('.emoji') as $emoji) {
            $emoji->outerhtml .= $emoji->getAttribute('alt');
        }
    }

    public function __toString()
    {
        return htmlspecialchars_decode($this->dom->text(), ENT_QUOTES);
    }

    public function getDefaultValue(): mixed
    {
        $description = (string)$this;
        if (stripos($description, 'must be') !== false) {
            $text = explode('must be ', $this->dom->html())[1] ?? '';
            if (!empty($text)) {
                $text = explode(' ', $text)[0];
                $dom = HtmlDomParser::str_get_html($text);
                $element = $dom->findOneOrFalse('em');
                if ($element !== false) {
                    return $element->text();
                }
            }
        }
        $offset = stripos($description, 'defaults to');
        if ($offset === false) {
            return null;
        }
        $description = substr($description, $offset + 12);
        $parts = explode(' ', $description, 2);
        $value = $parts[0];
        if (str_ends_with($value, '.') or str_ends_with($value, ',')) {
            $value = substr($value, 0, -1);
        }
        if (str_starts_with($value, '“') and str_ends_with($value, '”')) {
            return str_replace(['“', '”'], ['', ''], $value);
        }
        if (is_numeric($value)) {
            return (int)$value;
        }
        if (strtolower($value) == 'true') {
            return true;
        }
        if (strtolower($value) == 'false') {
            return false;
        }
        if ($value === ucfirst($value)) {
            return $value;
        }
        return null;
    }

}