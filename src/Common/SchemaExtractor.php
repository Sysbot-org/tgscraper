<?php


namespace TgScraper\Common;


use PHPHtmlParser\Dom;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\ContentLengthException;
use PHPHtmlParser\Exceptions\LogicalException;
use PHPHtmlParser\Exceptions\NotLoadedException;
use PHPHtmlParser\Exceptions\ParentNotFoundException;
use PHPHtmlParser\Exceptions\StrictException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use TgScraper\Constants\Versions;
use Throwable;

/**
 * Class SchemaExtractor
 * @package TgScraper\Common
 */
class SchemaExtractor
{

    /**
     * Additional methods with boolean return value.
     */
    private const BOOL_RETURNS = [
        'answerShippingQuery',
        'answerPreCheckoutQuery'
    ];

    /**
     * SchemaExtractor constructor.
     * @param LoggerInterface $logger
     * @param string $url
     */
    public function __construct(private LoggerInterface $logger, private string $url = Versions::LATEST)
    {
    }

    /**
     * @return array
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws ContentLengthException
     * @throws LogicalException
     * @throws NotLoadedException
     * @throws ParentNotFoundException
     * @throws StrictException
     * @throws ClientExceptionInterface
     * @throws Throwable
     */
    public function extract(): array
    {
        $dom = new Dom;
        try {
            $dom->loadFromURL($this->url);
        } catch (Throwable $e) {
            $this->logger->critical(sprintf('Unable to load data from URL "%s": %s', $this->url, $e->getMessage()));
            throw $e;
        }
        try {
            $elements = $dom->find('h4');
        } catch (Throwable $e) {
            $this->logger->critical(sprintf('Unable to load data from URL "%s": %s', $this->url, $e->getMessage()));
            throw $e;
        }
        $data = [];
        /* @var Dom\Node\AbstractNode $element */
        foreach ($elements as $element) {
            if (!str_contains($name = $element->text, ' ')) {
                $isMethod = lcfirst($name) == $name;
                $path = $isMethod ? 'methods' : 'types';
                $temp = $element;
                $description = '';
                $table = null;
                while (true) {
                    try {
                        $element = $element->nextSibling();
                    } catch (ChildNotFoundException) {
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
                /* @var Dom\Node\AbstractNode $element */
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

    /**
     * @param string $name
     * @param string $description
     * @param Dom\Node\Collection|null $unparsedFields
     * @param bool $isMethod
     * @return array
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws ContentLengthException
     * @throws LogicalException
     * @throws NotLoadedException
     * @throws StrictException
     */
    private static function generateElement(
        string $name,
        string $description,
        ?Dom\Node\Collection $unparsedFields,
        bool $isMethod
    ): array {
        $fields = self::parseFields($unparsedFields, $isMethod);
        $result = [
            'name' => $name,
            'description' => htmlspecialchars_decode(strip_tags($description), ENT_QUOTES),
            'fields' => $fields
        ];
        if ($isMethod) {
            $returnTypes = self::parseReturnTypes($description);
            if (empty($returnTypes) and in_array($name, self::BOOL_RETURNS)) {
                $returnTypes[] = 'bool';
            }
            $result['return_types'] = $returnTypes;
            return $result;
        }
        return $result;
    }

    /**
     * @param Dom\Node\Collection|null $fields
     * @param bool $isMethod
     * @return array
     * @throws ChildNotFoundException
     * @throws NotLoadedException
     */
    private static function parseFields(?Dom\Node\Collection $fields, bool $isMethod): array
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
                $description = htmlspecialchars_decode(strip_tags($fieldData[2]->innerHtml), ENT_QUOTES);
                $parsedData['optional'] = str_starts_with($description, 'Optional.');
                $parsedData['description'] = $description;
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
     * @throws NotLoadedException
     * @throws StrictException
     * @throws ContentLengthException
     * @throws LogicalException
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
            $dom->loadStr($phrase);
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

}