<?php

namespace TGBotApi;

use PHPHtmlParser\Dom;

class Generator
{

    private const EMPTY_FIELDS = [
        'deleteWebhook',
        'getWebhookInfo',
        'getMe',
        'InputFile',
        'InputMedia',
        'InlineQueryResult',
        'InputMessageContent',
        'PassportElementError',
        'CallbackGame'
    ];

    private const BOOL_RETURNS = [
        'answerShippingQuery',
        'answerPreCheckoutQuery'
    ];

    /**
     * @param string $target_directory
     * @param string $namespace_prefix
     * @return bool
     * @throws \Exception
     */
    public static function toClasses(string $target_directory = '', string $namespace_prefix = ''): bool
    {
        $target_directory = self::getTargetDirectory($target_directory);
        mkdir($target_directory . '/Methods', 0755);
        mkdir($target_directory . '/Types', 0755);
        try {
            $stub_provider = new StubProvider($namespace_prefix);
            $code = $stub_provider->generateCode(self::extractScheme());
            foreach ($code['methods'] as $class_name => $method) {
                file_put_contents($target_directory . '/Methods/' . $class_name . '.php', $method);
            }
            foreach ($code['types'] as $class_name => $type) {
                file_put_contents($target_directory . '/Types/' . $class_name . '.php', $type);
            }
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }

    private static function getTargetDirectory(string $target_directory): string
    {
        mkdir($target_directory, 0755);
        $target_directory = realpath($target_directory);
        if (false == $target_directory) {
            $target_directory = __DIR__ . '/generated';
            if (!file_exists($target_directory)) {
                mkdir($target_directory, 0755);
            }
        }
        return $target_directory;
    }

    /**
     * @return array
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\CurlException
     * @throws \PHPHtmlParser\Exceptions\NotLoadedException
     * @throws \PHPHtmlParser\Exceptions\ParentNotFoundException
     * @throws \PHPHtmlParser\Exceptions\StrictException
     */
    private static function extractScheme(): array
    {
        $dom = new Dom;
        $dom->loadFromURL('https://core.telegram.org/bots/api');
        $elements = $dom->find('h4');
        $i = 0;
        $data = [];
        foreach ($elements as $element) {
            if (false === strpos($name = $element->text, ' ')) {
                $is_method = self::isMethod($name);
                $path = $is_method ? 'methods' : 'types';
                $empty = in_array($name, self::EMPTY_FIELDS);
                /* @var Dom $fields_table */
                $fields_table = $dom->find('table')[$i];
                $unparsed_fields = $fields_table->find('tbody')->find('tr');
                /* @var Dom\AbstractNode $element */
                /** @noinspection PhpUndefinedFieldInspection */
                $data[$path][] = self::generateElement($name, $element->nextSibling()->nextSibling()->innerHtml,
                    ($empty ? null : $unparsed_fields), $is_method);
                if (!$empty) {
                    $i++;
                }
            }
        }
        return $data;
    }

    private static function isMethod(string $name): bool
    {
        $first_letter = substr($name, 0, 1);
        return (strtolower($first_letter) == $first_letter);
    }

    /**
     * @param string $name
     * @param string $description
     * @param Dom\Collection|null $unparsed_fields
     * @param bool $is_method
     * @return array
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\CurlException
     * @throws \PHPHtmlParser\Exceptions\NotLoadedException
     * @throws \PHPHtmlParser\Exceptions\StrictException
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
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\NotLoadedException
     */
    private static function parseFields(?Dom\Collection $fields, bool $is_method): array
    {
        $parsed_fields = [];
        $fields = $fields ?? [];
        foreach ($fields as $field) {
            /* @var Dom $field */
            $field_data = $field->find('td');
            $parsed_data = [
                'name' => $field_data[0]->text,
                'type' => strip_tags($field_data[1]->innerHtml)
            ];
            if ($is_method) {
                $parsed_data['required'] = ($field_data[2]->text == 'Yes');
                $parsed_data['types'] = self::parseMethodFieldTypes($parsed_data['type']);
                unset($parsed_data['type']);
                $parsed_data['description'] = htmlspecialchars_decode(strip_tags($field_data[3]->innerHtml ?? $field_data[3]->text ?? ''),
                    ENT_QUOTES);
            } else {
                $parsed_data['type'] = self::parseObjectFieldType($parsed_data['type']);
                $parsed_data['description'] = htmlspecialchars_decode(strip_tags($field_data[2]->innerHtml),
                    ENT_QUOTES);
            }
            $parsed_fields[] = $parsed_data;
        }
        return $parsed_fields;
    }

    /**
     * @param string $raw_type
     * @return array
     */
    private static function parseMethodFieldTypes(string $raw_type): array
    {
        $types = explode(' or ', $raw_type);
        $parsed_types = [];
        foreach ($types as $type) {
            $type = trim(str_replace(['number', 'of'], '', $type));
            $multiples_count = substr_count(strtolower($type), 'array');
            $parsed_types[] = trim(str_replace(['Array', 'Integer', 'String', 'Boolean', 'Float'],
                    ['', 'int', 'string', 'bool', 'float'], $type)) . str_repeat('[]', $multiples_count);
        }
        return $parsed_types;
    }

    /**
     * @param string $raw_type
     * @return string
     */
    private static function parseObjectFieldType(string $raw_type): string
    {
        $type = trim(str_replace(['number', 'of'], '', $raw_type));
        $multiples_count = substr_count(strtolower($type), 'array');
        return trim(str_replace(['Array', 'Integer', 'String', 'Boolean', 'Float'],
                ['', 'int', 'string', 'bool', 'float'], $type)) . str_repeat('[]', $multiples_count);
    }

    /**
     * @param string $description
     * @return array
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\CurlException
     * @throws \PHPHtmlParser\Exceptions\NotLoadedException
     * @throws \PHPHtmlParser\Exceptions\StrictException
     */
    private static function parseReturnTypes(string $description): array
    {
        $return_types = [];
        $phrases = explode('.', $description);
        $phrases = array_filter($phrases, function ($phrase) {
            return (false !== stripos($phrase, 'returns') or false !== stripos($phrase, 'is returned'));
        });
        foreach ($phrases as $phrase) {
            $dom = new Dom;
            $dom->load($phrase);
            $a = $dom->find('a');
            $em = $dom->find('em');
            foreach ($a as $element) {
                if ($element->text == 'Messages') {
                    $return_types[] = 'Message[]';
                    continue;
                }
                $multiples_count = substr_count(strtolower($phrase), 'array');
                $return_types[] = $element->text . str_repeat('[]', $multiples_count);
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
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\CurlException
     * @throws \PHPHtmlParser\Exceptions\NotLoadedException
     * @throws \PHPHtmlParser\Exceptions\ParentNotFoundException
     * @throws \PHPHtmlParser\Exceptions\StrictException
     */
    public static function toJson(int $options = 0): string
    {
        $scheme = self::extractScheme();
        return json_encode($scheme, $options);
    }

}