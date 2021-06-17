<?php

namespace TgScraper;

use Exception;
use JsonException;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\{ChildNotFoundException,
    CircularException,
    CurlException,
    NotLoadedException,
    ParentNotFoundException,
    StrictException
};

class Generator
{

    private const BOOL_RETURNS = [
        'answerShippingQuery',
        'answerPreCheckoutQuery'
    ];

    public const BOT_API_URL = 'https://core.telegram.org/bots/api';

    public function __construct(private string $url = self::BOT_API_URL)
    {
    }

    /**
     * @param string $directory
     * @param string $namespace
     * @param string|null $scheme
     * @return bool
     */
    public function toStubs(string $directory = '', string $namespace = '', string $scheme = null): bool
    {
        try {
            $directory = self::getTargetDirectory($directory);
        } catch (Exception $e) {
            echo 'Unable to use target directory:' . $e->getMessage();
            return false;
        }
        mkdir($directory . '/Types', 0755);
        try {
            if (!empty($scheme)) {
                try {
                    $data = json_decode($scheme, true, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    $data = null;
                }
            }
            $data = $data ?? self::extractScheme();
            $creator = new StubCreator($data, $namespace);
            $code = $creator->generateCode();
            foreach ($code['types'] as $class_name => $type) {
                $filename = sprintf('%s/Types/%s.php', $directory, $class_name);
                file_put_contents($filename, $type);
            }
            file_put_contents($directory . '/API.php', $code['api']);
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
        return true;
    }

    /**
     * @param string $target_directory
     * @return string
     * @throws Exception
     */
    private static function getTargetDirectory(string $target_directory): string
    {
        $target_path = realpath($target_directory);
        if (false == $target_path) {
            if (!mkdir($target_directory)) {
                $target_path = __DIR__ . '/../generated';
                if (!file_exists($target_path)) {
                    mkdir($target_path, 0755);
                }
            }
        }
        if (realpath($target_path) == false) {
            throw new Exception('Could not create target directory');
        }
        return $target_directory;
    }

    /**
     * @return array
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws CurlException
     * @throws NotLoadedException
     * @throws ParentNotFoundException
     * @throws StrictException
     */
    private function extractScheme(): array
    {
        $dom = new Dom;
        $dom->loadFromURL($this->url);
        $elements = $dom->find('h4');
        $data = [];
        /* @var Dom\AbstractNode $element */
        foreach ($elements as $element) {
            if (!str_contains($name = $element->text, ' ')) {
                $is_method = self::isMethod($name);
                $path = $is_method ? 'methods' : 'types';
                $temp = $element;
                $description = '';
                $table = null;
                while (true) {
                    try {
                        $element = $element->nextSibling();
                    } catch (ChildNotFoundException $e) {
                        break;
                    }
                    $tag = $element->tag->name() ?? null;
                    if (empty($temp->text()) or empty($tag) or $tag == 'text') {
                        continue;
                    } elseif (str_starts_with($tag, 'h')) {
                        break;
                    } elseif ($tag == 'p') {
                        $description .= PHP_EOL . $element->innerHtml();
                    } elseif ($tag == 'table') {
                        $table = $element->find('tbody')->find('tr');
                        break;
                    }
                }
                /* @var Dom\AbstractNode $element */
                $data[$path][] = self::generateElement(
                    $name,
                    trim($description),
                    $table,
                    $is_method
                );
            }
        }
        return $data;
    }

    private static function isMethod(string $name): bool
    {
        return lcfirst($name) == $name;
    }

    /**
     * @param string $name
     * @param string $description
     * @param Dom\Collection|null $unparsed_fields
     * @param bool $is_method
     * @return array
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws CurlException
     * @throws NotLoadedException
     * @throws StrictException
     */
    private static function generateElement(
        string $name,
        string $description,
        ?Dom\Collection $unparsed_fields,
        bool $is_method
    ): array {
        $fields = self::parseFields($unparsed_fields, $is_method);
        if (!$is_method) {
            return [
                'name' => $name,
                'description' => htmlspecialchars_decode(strip_tags($description), ENT_QUOTES),
                'fields' => $fields
            ];
        }
        $return_types = self::parseReturnTypes($description);
        if (empty($return_types) and in_array($name, self::BOOL_RETURNS)) {
            $return_types[] = 'bool';
        }
        return [
            'name' => $name,
            'description' => htmlspecialchars_decode(strip_tags($description), ENT_QUOTES),
            'fields' => $fields,
            'return_types' => $return_types
        ];
    }

    /**
     * @param Dom\Collection|null $fields
     * @param bool $is_method
     * @return array
     * @throws ChildNotFoundException
     * @throws NotLoadedException
     */
    private static function parseFields(?Dom\Collection $fields, bool $is_method): array
    {
        $parsed_fields = [];
        $fields = $fields ?? [];
        foreach ($fields as $field) {
            /* @var Dom $field */
            $field_data = $field->find('td');
            $name = $field_data[0]->text;
            if (empty($name)) {
                continue;
            }
            $parsed_data = [
                'name' => $name,
                'type' => strip_tags($field_data[1]->innerHtml)
            ];
            $parsed_data['types'] = self::parseFieldTypes($parsed_data['type']);
            unset($parsed_data['type']);
            if ($is_method) {
                $parsed_data['required'] = $field_data[2]->text == 'Yes';
                $parsed_data['description'] = htmlspecialchars_decode(
                    strip_tags($field_data[3]->innerHtml ?? $field_data[3]->text ?? ''),
                    ENT_QUOTES
                );
            } else {
                $parsed_data['description'] = htmlspecialchars_decode(
                    strip_tags($field_data[2]->innerHtml),
                    ENT_QUOTES
                );
            }
            $parsed_fields[] = $parsed_data;
        }
        return $parsed_fields;
    }

    /**
     * @param string $raw_type
     * @return array
     */
    private static function parseFieldTypes(string $raw_type): array
    {
        $types = [];
        foreach (explode(' or ', $raw_type) as $raw_or_type) {
            if (stripos($raw_or_type, 'array') === 0) {
                $types[] = str_replace(' and', ',', $raw_or_type);
                continue;
            }
            foreach (explode(' and ', $raw_or_type) as $unparsed_type) {
                $types[] = $unparsed_type;
            }
        }
        $parsed_types = [];
        foreach ($types as $type) {
            $type = trim(str_replace(['number', 'of'], '', $type));
            $multiples_count = substr_count(strtolower($type), 'array');
            $parsed_type = trim(
                str_replace(
                    ['Array', 'Integer', 'String', 'Boolean', 'Float', 'True'],
                    ['', 'int', 'string', 'bool', 'float', 'bool'],
                    $type
                )
            );
            for ($i = 0; $i < $multiples_count; $i++) {
                $parsed_type = sprintf('Array<%s>', $parsed_type);
            }
            $parsed_types[] = $parsed_type;
        }
        return $parsed_types;
    }

    /**
     * @param string $description
     * @return array
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws CurlException
     * @throws NotLoadedException
     * @throws StrictException
     * @noinspection PhpUndefinedFieldInspection
     */
    private static function parseReturnTypes(string $description): array
    {
        $return_types = [];
        $phrases = explode('.', $description);
        $phrases = array_filter(
            $phrases,
            function ($phrase) {
                return (false !== stripos($phrase, 'returns') or false !== stripos($phrase, 'is returned'));
            }
        );
        foreach ($phrases as $phrase) {
            $dom = new Dom;
            $dom->load($phrase);
            $a = $dom->find('a');
            $em = $dom->find('em');
            foreach ($a as $element) {
                if ($element->text == 'Messages') {
                    $return_types[] = 'Array<Message>';
                    continue;
                }

                $multiples_count = substr_count(strtolower($phrase), 'array');
                $return_type = $element->text;
                for ($i = 0; $i < $multiples_count; $i++) {
                    $return_type = sprintf('Array<%s>', $return_type);
                }
                $return_types[] = $return_type;
            }
            foreach ($em as $element) {
                if (in_array($element->text, ['False', 'force', 'Array'])) {
                    continue;
                }
                $type = str_replace(['True', 'Int', 'String'], ['bool', 'int', 'string'], $element->text);
                $return_types[] = $type;
            }
        }
        return $return_types;
    }

    /**
     * @param int $options
     * @return string
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws CurlException
     * @throws NotLoadedException
     * @throws ParentNotFoundException
     * @throws StrictException
     */
    public function toJson(int $options = 0): string
    {
        $scheme = $this->extractScheme();
        return json_encode($scheme, $options);
    }

}
