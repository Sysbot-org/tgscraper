<?php

namespace TgScraper\Parsers;

use JetBrains\PhpStorm\ArrayShape;

/**
 * Class Field
 * @package TgScraper\Parsers
 */
class Field
{

    /**
     * Parsed types map.
     */
    public const TYPES = [
        'Integer' => 'int',
        'Float' => 'float',
        'String' => 'string',
        'Boolean' => 'bool',
        'True' => 'bool',
        'False' => 'bool'
    ];

    /**
     * @var string
     */
    private string $name;
    /**
     * @var array
     */
    private array $types;
    /**
     * @var FieldDescription
     */
    private FieldDescription $description;
    /**
     * @var bool
     */
    private bool $optional;
    /**
     * @var mixed
     */
    private mixed $defaultValue;

    /**
     * @param string $name
     * @param string $types
     * @param bool $optional
     * @param string $description
     */
    public function __construct(string $name, string $types, bool $optional, string $description)
    {
        $this->name = $name;
        $this->types = $this->parseTypesString($types);
        $this->optional = $optional;
        $this->description = new FieldDescription($description);
    }

    /**
     * @param string $type
     * @return string
     */
    private function parseTypeString(string $type): string
    {
        if ($type == 'True') {
            $this->defaultValue = true;
            return self::TYPES['Boolean'];
        } elseif ($type == 'False') {
            $this->defaultValue = false;
            return self::TYPES['Boolean'];
        }
        $type = trim(str_replace('number', '', $type));
        return trim(str_replace(array_keys(self::TYPES), array_values(self::TYPES), $type));
    }

    /**
     * @param string $text
     * @return array
     */
    private function parseTypesString(string $text): array
    {
        $types = [];
        $parts = explode(' or ', $text);
        foreach ($parts as $part) {
            $part = trim(str_replace(' and', ',', $part));
            $arrays = 0;
            while (stripos($part, 'array of') === 0) {
                $part = substr($part, 9);
                $arrays++;
            }
            $pieces = explode(',', $part);
            foreach ($pieces as $index => $piece) {
                $pieces[$index] = $this->parseTypeString($piece);
            }
            $type = implode('|', $pieces);
            for ($i = 0; $i < $arrays; $i++) {
                $type = sprintf('Array<%s>', $type);
            }
            $types[] = $type;
        }
        return $types;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @return bool
     */
    public function isOptional(): bool
    {
        return $this->optional;
    }

    /**
     * @return mixed
     */
    public function getDefaultValue(): mixed
    {
        if (!isset($this->defaultValue)) {
            $this->defaultValue = $this->description->getDefaultValue();
        }
        return $this->defaultValue;
    }

    /**
     * @return array
     */
    #[ArrayShape([
        'name' => "string",
        'types' => "array",
        'optional' => "bool",
        'description' => "string",
        'default' => "mixed"
    ])] public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'types' => $this->types,
            'optional' => $this->optional,
            'description' => (string)$this->description,
        ];
        $defaultValue = $this->getDefaultValue();
        if (null !== $defaultValue) {
            $result['default'] = $defaultValue;
        }
        return $result;
    }

}