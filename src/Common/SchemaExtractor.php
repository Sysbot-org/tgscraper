<?php


namespace TgScraper\Common;


use JetBrains\PhpStorm\ArrayShape;
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

    private Dom $dom;

    private string $version;

    /**
     * SchemaExtractor constructor.
     * @param LoggerInterface $logger
     * @param string $url
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws ClientExceptionInterface
     * @throws ContentLengthException
     * @throws LogicalException
     * @throws StrictException
     * @throws Throwable
     */
    public function __construct(private LoggerInterface $logger, private string $url = Versions::LATEST)
    {
        $this->dom = new Dom();
        try {
            $this->dom->loadFromURL($this->url);
        } catch (Throwable $e) {
            $this->logger->critical(sprintf('Unable to load data from URL "%s": %s', $this->url, $e->getMessage()));
            throw $e;
        }
        $this->version = $this->parseVersion();
        $this->logger->info('Bot API version: ' . $this->version);
    }

    /**
     * @throws ParentNotFoundException
     * @throws ChildNotFoundException
     */
    #[ArrayShape(['description' => "string", 'table' => "mixed", 'extended_by' => "array"])]
    private static function parseNode(Dom\Node\AbstractNode $node): ?array
    {
        $description = '';
        $table = null;
        $extendedBy = [];
        $tag = '';
        $sibling = $node;
        while (!str_starts_with($tag, 'h')) {
            $sibling = $sibling->nextSibling();
            $tag = $sibling?->tag?->name();
            if (empty($node->text()) or empty($tag) or $tag == 'text') {
                continue;
            } elseif ($tag == 'p') {
                $description .= PHP_EOL . $sibling->innerHtml();
            } elseif ($tag == 'ul') {
                $items = $sibling->find('li');
                /* @var Dom\Node\AbstractNode $item */
                foreach ($items as $item) {
                    $extendedBy[] = $item->innerText;
                }
                break;
            } elseif ($tag == 'table') {
                $table = $sibling->find('tbody')->find('tr');
                break;
            }
        }
        return ['description' => $description, 'table' => $table, 'extended_by' => $extendedBy];
    }

    private function parseVersion(): string
    {
        /** @var Dom\Node\AbstractNode $element */
        $element = $this->dom->find('h3')[0];
        $tag = '';
        while ($tag != 'p') {
            try {
                $element = $element->nextSibling();
            } catch (ChildNotFoundException | ParentNotFoundException) {
                continue;
            }
            $tag = $element->tag->name();
        }
        $versionNumbers = explode('.', str_replace('Bot API ', '', $element->innerText));
        return sprintf(
            '%s.%s.%s',
            $versionNumbers[0] ?? '1',
            $versionNumbers[1] ?? '0',
            $versionNumbers[2] ?? '0'
        );
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
    #[ArrayShape(['version' => "string", 'methods' => "array", 'types' => "array"])]
    public function extract(): array
    {
        try {
            $elements = $this->dom->find('h4');
        } catch (Throwable $e) {
            $this->logger->critical(sprintf('Unable to load data from URL "%s": %s', $this->url, $e->getMessage()));
            throw $e;
        }
        $data = ['version' => $this->version];
        /* @var Dom\Node\AbstractNode $element */
        foreach ($elements as $element) {
            if (!str_contains($name = $element->text, ' ')) {
                $isMethod = lcfirst($name) == $name;
                $path = $isMethod ? 'methods' : 'types';
                ['description' => $description, 'table' => $table, 'extended_by' => $extendedBy] = self::parseNode(
                    $element
                );
                $data[$path][] = self::generateElement(
                    $name,
                    trim($description),
                    $table,
                    $extendedBy,
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
     * @param array $extendedBy
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
        array $extendedBy,
        bool $isMethod
    ): array {
        $fields = self::parseFields($unparsedFields, $isMethod);
        $result = [
            'name' => $name,
            'description' => htmlspecialchars_decode(strip_tags($description), ENT_QUOTES),
            'fields' => $fields,
            'extended_by' => $extendedBy
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