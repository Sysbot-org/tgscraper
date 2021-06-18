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
            echo 'Unable to use target directory:' . $e->getMessage() . PHP_EOL;
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
            foreach ($code['types'] as $className => $type) {
                $filename = sprintf('%s/Types/%s.php', $directory, $className);
                file_put_contents($filename, $type);
            }
            file_put_contents($directory . '/API.php', $code['api']);
        } catch (Exception $e) {
            var_dump($e);
            echo $e->getMessage() . PHP_EOL;
            return false;
        }
        return true;
    }

    /**
     * @param string $path
     * @return string
     * @throws Exception
     */
    private static function getTargetDirectory(string $path): string
    {
        $path = realpath($path);
        if (false == $path) {
            if (!mkdir($path)) {
                $path = __DIR__ . '/../generated';
                if (!file_exists($path)) {
                    mkdir($path, 0755);
                }
            }
        }
        if (realpath($path) == false) {
            throw new Exception('Could not create target directory');
        }
        return $path;
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
                $isMethod = self::isMethod($name);
                $path = $isMethod ? 'methods' : 'types';
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
                    $isMethod
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
     * @param Dom\Collection|null $unparsedFields
     * @param bool $isMethod
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
        ?Dom\Collection $unparsedFields,
        bool $isMethod
    ): array {
        $fields = self::parseFields($unparsedFields, $isMethod);
        if (!$isMethod) {
            return [
                'name' => $name,
                'description' => htmlspecialchars_decode(strip_tags($description), ENT_QUOTES),
                'fields' => $fields
            ];
        }
        $returnTypes = self::parseReturnTypes($description);
        if (empty($returnTypes) and in_array($name, self::BOOL_RETURNS)) {
            $returnTypes[] = 'bool';
        }
        return [
            'name' => $name,
            'description' => htmlspecialchars_decode(strip_tags($description), ENT_QUOTES),
            'fields' => $fields,
            'return_types' => $returnTypes
        ];
    }

    /**
     * @param Dom\Collection|null $fields
     * @param bool $isMethod
     * @return array
     * @throws ChildNotFoundException
     * @throws NotLoadedException
     */
    private static function parseFields(?Dom\Collection $fields, bool $isMethod): array
    {
        $parsedFields = [];
        $fields = $fields ?? [];
        foreach ($fields as $field) {
            /* @var Dom $field */
            $fieldData = $field->find('td');
            $name = $fieldData[0]->text;
            if (empty($name)) {
                continue;
            }
            $parsedData = [
                'name' => $name,
                'type' => strip_tags($fieldData[1]->innerHtml)
            ];
            $parsedData['types'] = self::parseFieldTypes($parsedData['type']);
            unset($parsedData['type']);
            if ($isMethod) {
                $parsedData['required'] = $fieldData[2]->text == 'Yes';
                $parsedData['description'] = htmlspecialchars_decode(
                    strip_tags($fieldData[3]->innerHtml ?? $fieldData[3]->text ?? ''),
                    ENT_QUOTES
                );
            } else {
                $parsedData['description'] = htmlspecialchars_decode(
                    strip_tags($fieldData[2]->innerHtml),
                    ENT_QUOTES
                );
            }
            $parsedFields[] = $parsedData;
        }
        return $parsedFields;
    }

    /**
     * @param string $rawType
     * @return array
     */
    private static function parseFieldTypes(string $rawType): array
    {
        $types = [];
        foreach (explode(' or ', $rawType) as $rawOrType) {
            if (stripos($rawOrType, 'array') === 0) {
                $types[] = str_replace(' and', ',', $rawOrType);
                continue;
            }
            foreach (explode(' and ', $rawOrType) as $unparsedType) {
                $types[] = $unparsedType;
            }
        }
        $parsedTypes = [];
        foreach ($types as $type) {
            $type = trim(str_replace(['number', 'of'], '', $type));
            $multiplesCount = substr_count(strtolower($type), 'array');
            $parsedType = trim(
                str_replace(
                    ['Array', 'Integer', 'String', 'Boolean', 'Float', 'True'],
                    ['', 'int', 'string', 'bool', 'float', 'bool'],
                    $type
                )
            );
            for ($i = 0; $i < $multiplesCount; $i++) {
                $parsedType = sprintf('Array<%s>', $parsedType);
            }
            $parsedTypes[] = $parsedType;
        }
        return $parsedTypes;
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
        $returnTypes = [];
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
                    $returnTypes[] = 'Array<Message>';
                    continue;
                }

                $multiplesCount = substr_count(strtolower($phrase), 'array');
                $returnType = $element->text;
                for ($i = 0; $i < $multiplesCount; $i++) {
                    $returnType = sprintf('Array<%s>', $returnType);
                }
                $returnTypes[] = $returnType;
            }
            foreach ($em as $element) {
                if (in_array($element->text, ['False', 'force', 'Array'])) {
                    continue;
                }
                $type = str_replace(['True', 'Int', 'String'], ['bool', 'int', 'string'], $element->text);
                $returnTypes[] = $type;
            }
        }
        return $returnTypes;
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
