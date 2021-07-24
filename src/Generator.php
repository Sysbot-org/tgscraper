<?php

namespace TgScraper;

use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use PHPHtmlParser\Exceptions\ChildNotFoundException;
use PHPHtmlParser\Exceptions\CircularException;
use PHPHtmlParser\Exceptions\ContentLengthException;
use PHPHtmlParser\Exceptions\LogicalException;
use PHPHtmlParser\Exceptions\NotLoadedException;
use PHPHtmlParser\Exceptions\ParentNotFoundException;
use PHPHtmlParser\Exceptions\StrictException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;
use TgScraper\Common\SchemaExtractor;
use TgScraper\Common\StubCreator;
use TgScraper\Constants\Versions;
use Throwable;

/**
 * Class Generator
 * @package TgScraper
 */
class Generator
{

    /**
     * @var array
     */
    private array $schema;

    /**
     * Generator constructor.
     * @param LoggerInterface $logger
     * @param string $url
     * @param array|null $schema
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws ClientExceptionInterface
     * @throws ContentLengthException
     * @throws LogicalException
     * @throws NotLoadedException
     * @throws ParentNotFoundException
     * @throws StrictException
     * @throws Throwable
     */
    public function __construct(
        private LoggerInterface $logger,
        private string $url = Versions::LATEST,
        ?array $schema = null
    ) {
        if (empty($schema)) {
            $extractor = new SchemaExtractor($this->logger, $this->url);
            try {
                $this->logger->info('Schema not provided, extracting from URL.');
                $schema = $extractor->extract();
            } catch (Throwable $e) {
                $this->logger->critical(
                    'An exception occurred while trying to extract the schema: ' . $e->getMessage()
                );
                throw $e;
            }
        }
        /** @var array $schema */
        $this->schema = $schema;
    }

    /**
     * @throws ChildNotFoundException
     * @throws CircularException
     * @throws ParentNotFoundException
     * @throws StrictException
     * @throws ClientExceptionInterface
     * @throws NotLoadedException
     * @throws ContentLengthException
     * @throws LogicalException
     * @throws Throwable
     */
    public static function fromYaml(LoggerInterface $logger, string $yaml): self
    {
        $data = Yaml::parse($yaml);
        return new self($logger, schema: $data);
    }

    /**
     * @throws ChildNotFoundException
     * @throws ParentNotFoundException
     * @throws CircularException
     * @throws StrictException
     * @throws ClientExceptionInterface
     * @throws NotLoadedException
     * @throws ContentLengthException
     * @throws LogicalException
     * @throws Throwable
     */
    public static function fromJson(LoggerInterface $logger, string $json): self
    {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        return new self($logger, schema: $data);
    }

    /**
     * @param string $directory
     * @param string $namespace
     * @return void
     * @throws Exception
     */
    public function toStubs(string $directory = '', string $namespace = 'TelegramApi'): void
    {
        try {
            $directory = self::getTargetDirectory($directory);
        } catch (Exception $e) {
            $this->logger->critical(
                'An exception occurred while trying to get the target directory: ' . $e->getMessage()
            );
            throw $e;
        }
        try {
            $creator = new StubCreator($this->schema, $namespace);
        } catch (InvalidArgumentException $e) {
            $this->logger->critical(
                'An exception occurred while trying to parse the schema: ' . $e->getMessage()
            );
            throw $e;
        }
        $code = $creator->generateCode();
        foreach ($code['types'] as $className => $type) {
            $this->logger->info('Generating class for Type: ' . $className);
            $filename = sprintf('%s/Types/%s.php', $directory, $className);
            file_put_contents($filename, $type);
        }
        file_put_contents($directory . '/API.php', $code['api']);
    }

    /**
     * @param string $path
     * @return string
     * @throws Exception
     */
    private static function getTargetDirectory(string $path): string
    {
        $result = realpath($path);
        if (false === $result) {
            if (!mkdir($path)) {
                $path = __DIR__ . '/../generated';
                if (!file_exists($path)) {
                    mkdir($path, 0755);
                }
            }
        }
        $result = realpath($path);
        if (false === $result) {
            throw new Exception('Could not create target directory');
        }
        @mkdir($result . '/Types', 0755);
        return $result;
    }

    /**
     * @param int $options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->schema, $options);
    }

    /**
     * @param int $inline
     * @param int $indent
     * @param int $flags
     * @return string
     */
    public function toYaml(int $inline = 6, int $indent = 4, int $flags = 0): string
    {
        return Yaml::dump($this->schema, $inline, $indent, $flags);
    }


    /**
     * Thanks to davtur19 (https://github.com/davtur19/TuriBotGen/blob/master/postman.php)
     * @param int $options
     * @return string
     */
    #[ArrayShape(['info' => "string[]", 'variable' => "string[]", 'item' => "array[]"])]
    public function toPostman(
        int $options = 0
    ): string {
        $result = [
            'info' => [
                'name' => 'Telegram Bot API',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
            ],
            'variable' => [
                'key' => 'token',
                'value' => '1234:AAbbcc',
                'type' => 'string'
            ]
        ];
        foreach ($this->schema['methods'] as $method) {
            $formData = [];
            if (!empty($method['fields'])) {
                foreach ($method['fields'] as $field) {
                    $formData[] = [
                        'key' => $field['name'],
                        'value' => '',
                        'description' => sprintf(
                            '%s. %s',
                            $field['required'] ? 'Required' : 'Optional',
                            $field['description']
                        ),
                        'type' => 'text'
                    ];
                }
            }
            $result['item'][] = [
                'name' => $method['name'],
                'request' => [
                    'method' => 'POST',
                    'body' => [
                        'mode' => 'formdata',
                        'formdata' => $formData
                    ],
                    'url' => [
                        'raw' => 'https://api.telegram.org/bot{{token}}/' . $method['name'],
                        'protocol' => 'https',
                        'host' => [
                            'api',
                            'telegram',
                            'org'
                        ],
                        'path' => [
                            'bot{{token}}',
                            $method['name']
                        ]
                    ],
                    'description' => $method['description']
                ]
            ];
        }
        return json_encode($result, $options);
    }

}
