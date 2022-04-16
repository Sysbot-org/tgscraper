<?php

namespace TgScraper\Common;

use Composer\InstalledVersions;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use OutOfBoundsException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TgScraper\Parsers\Field;
use TgScraper\Parsers\ObjectDescription;
use TgScraper\Constants\Versions;
use voku\helper\HtmlDomParser;
use voku\helper\SimpleHtmlDomInterface;
use voku\helper\SimpleHtmlDomNode;
use voku\helper\SimpleHtmlDomNodeInterface;

/**
 * Class SchemaExtractor
 * @package TgScraper\Common
 */
class SchemaExtractor
{
    /**
     * @var string
     */
    private string $version;

    /**
     * SchemaExtractor constructor.
     * @param LoggerInterface $logger
     * @param HtmlDomParser $dom
     */
    public function __construct(private LoggerInterface $logger, private HtmlDomParser $dom)
    {
        $this->version = $this->parseVersion();
        $this->logger->info('Bot API version: ' . $this->version);
    }


    /**
     * @param LoggerInterface $logger
     * @param string $version
     * @return SchemaExtractor
     * @throws OutOfBoundsException
     * @throws Exception
     * @throws GuzzleException
     */
    public static function fromVersion(LoggerInterface $logger, string $version = Versions::LATEST): SchemaExtractor
    {
        if (InstalledVersions::isInstalled('sysbot/tgscraper-cache') and class_exists('\TgScraper\Cache\CacheLoader')) {
            $logger->info('Cache package detected, searching for a cached version.');
            try {
                /** @noinspection PhpFullyQualifiedNameUsageInspection */
                /** @noinspection PhpUndefinedNamespaceInspection */
                /** @psalm-suppress UndefinedClass */
                $path = \TgScraper\Cache\CacheLoader::getCachedVersion($version);
                $logger->info('Cached version found.');
                return self::fromFile($logger, $path);
            } catch (OutOfBoundsException) {
                $logger->info('Cached version not found, continuing with URL.');
            }
        }
        $url = Versions::getUrlFromText($version);
        $logger->info(sprintf('Using URL: %s', $url));
        return self::fromUrl($logger, $url);
    }

    /**
     * @param LoggerInterface $logger
     * @param string $path
     * @return SchemaExtractor
     * @throws InvalidArgumentException
     * @throws RuntimeException
     */
    public static function fromFile(LoggerInterface $logger, string $path): SchemaExtractor
    {
        if (!file_exists($path) or is_dir($path)) {
            throw new InvalidArgumentException('File not found');
        }
        $path = realpath($path);
        try {
            $logger->info(sprintf('Loading data from file "%s".', $path));
            $dom = HtmlDomParser::file_get_html($path);
            $logger->info('Data loaded.');
        } catch (RuntimeException $e) {
            $logger->critical(sprintf('Unable to load data from "%s": %s', $path, $e->getMessage()));
            throw $e;
        }
        return new self($logger, $dom);
    }

    /**
     * @param LoggerInterface $logger
     * @param string $url
     * @return SchemaExtractor
     * @throws GuzzleException
     */
    public static function fromUrl(LoggerInterface $logger, string $url): SchemaExtractor
    {
        $client = new Client();
        try {
            $html = $client->get($url)->getBody();
            $dom = HtmlDomParser::str_get_html((string)$html);
        } catch (GuzzleException $e) {
            $logger->critical(sprintf('Unable to load data from URL "%s": %s', $url, $e->getMessage()));
            throw $e;
        }
        $logger->info(sprintf('Data loaded from "%s".', $url));
        return new self($logger, $dom);
    }

    /**
     * @param SimpleHtmlDomInterface $node
     * @return array{description: string, table: ?SimpleHtmlDomNodeInterface, extended_by: string[]}
     */
    private static function parseNode(SimpleHtmlDomInterface $node): array
    {
        $description = '';
        $table = null;
        $extendedBy = [];
        $tag = '';
        $sibling = $node;
        while (!str_starts_with($tag ?? '', 'h')) {
            $sibling = $sibling?->nextSibling();
            $tag = $sibling?->tag;
            if (empty($node->text()) or empty($tag) or $tag == 'text' or empty($sibling)) {
                continue;
            }
            switch ($tag) {
                case 'p':
                    $description .= PHP_EOL . $sibling->innerHtml();
                    break;
                case 'ul':
                    $items = $sibling->findMulti('li');
                    foreach ($items as $item) {
                        $extendedBy[] = $item->text();
                    }
                    break 2;
                case 'table':
                    /** @var SimpleHtmlDomNodeInterface $table */
                    $table = $sibling->findOne('tbody')->findMulti('tr');
                    break 2;
            }
        }
        return ['description' => $description, 'table' => $table, 'extended_by' => $extendedBy];
    }

    /**
     * @return string
     */
    private function parseVersion(): string
    {
        $element = $this->dom->findOne('h3');
        $tag = '';
        while ($tag != 'p' and !empty($element)) {
            $element = $element->nextSibling();
            $tag = $element?->tag;
        }
        if (empty($element)) {
            return '1.0.0';
        }
        $versionNumbers = explode('.', str_replace('Bot API ', '', $element->text()));
        return sprintf(
            '%s.%s.%s',
            $versionNumbers[0] ?? '1',
            $versionNumbers[1] ?? '0',
            $versionNumbers[2] ?? '0'
        );
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @return array{version: string, methods: array, types: array}
     * @throws Exception
     */
    public function extract(): array
    {
        $elements = $this->dom->findMultiOrFalse('h4');
        if (false === $elements) {
            throw new Exception('Unable to fetch required DOM nodes');
        }
        $data = ['version' => $this->version, 'methods' => [], 'types' => []];
        foreach ($elements as $element) {
            if (!str_contains($name = $element->text(), ' ')) {
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
     * @param SimpleHtmlDomNodeInterface|null $unparsedFields
     * @param array $extendedBy
     * @param bool $isMethod
     * @return array
     */
    private static function generateElement(
        string $name,
        string $description,
        ?SimpleHtmlDomNodeInterface $unparsedFields,
        array $extendedBy,
        bool $isMethod
    ): array {
        $fields = self::parseFields($unparsedFields, $isMethod);
        $result = [
            'name' => $name,
            'description' => htmlspecialchars_decode(strip_tags($description), ENT_QUOTES),
            'fields' => $fields
        ];
        if ($isMethod) {
            $description = new ObjectDescription($description);
            $returnTypes = $description->getTypes();
            $result['return_types'] = $returnTypes;
            return $result;
        }
        $result['extended_by'] = $extendedBy;
        return $result;
    }

    /**
     * @param SimpleHtmlDomNodeInterface|null $fields
     * @param bool $isMethod
     * @return array
     */
    private static function parseFields(?SimpleHtmlDomNodeInterface $fields, bool $isMethod): array
    {
        $parsedFields = [];
        $fields ??= [];
        /** @var SimpleHtmlDomInterface $field */
        foreach ($fields as $field) {
            /** @var SimpleHtmlDomNode $fieldData */
            $fieldData = $field->findMulti('td');
            $name = $fieldData[0]->text();
            if (empty($name)) {
                continue;
            }
            $types = $fieldData[1]->text();
            if ($isMethod) {
                $optional = $fieldData[2]->text() != 'Yes';
                $description = $fieldData[3]->innerHtml();
            } else {
                $description = $fieldData[2]->innerHtml();
                $optional = str_starts_with($fieldData[2]->text(), 'Optional.');
            }
            $field = new Field($name, $types, $optional, $description);
            $parsedFields[] = $field->toArray();
        }
        return $parsedFields;
    }
}
