<?php
/** @noinspection PhpInternalEntityUsedInspection */


namespace TgScraper\Common;


use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Type;

/**
 * Class StubCreator
 * @package TgScraper\Common
 */
class StubCreator
{

    /**
     * @var string
     */
    private string $namespace;

    /**
     * StubCreator constructor.
     * @param array $schema
     * @param string $namespace
     */
    public function __construct(private array $schema, string $namespace = '')
    {
        if (str_ends_with($namespace, '\\')) {
            $namespace = substr($namespace, 0, -1);
        }
        if (!empty($namespace)) {
            if (!Helpers::isNamespaceIdentifier($namespace)) {
                throw new InvalidArgumentException('Namespace invalid');
            }
        }
        if (!is_array($this->schema['methods']) or !is_array($this->schema['types'])) {
            throw new InvalidArgumentException('Schema invalid');
        }
        $this->namespace = $namespace;
    }

    private static function toCamelCase(string $str): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $str))));
    }

    /**
     * @param array $fieldTypes
     * @param PhpNamespace $phpNamespace
     * @return array
     */
    #[ArrayShape(['types' => "string", 'comments' => "string"])]
    private function parseFieldTypes(
        array $fieldTypes,
        PhpNamespace $phpNamespace
    ): array {
        $types = [];
        $comments = [];
        foreach ($fieldTypes as $fieldType) {
            $comments[] = $fieldType;
            if (str_starts_with($fieldType, 'Array')) {
                $types[] = 'array';
                continue;
            }
            if (ucfirst($fieldType) == $fieldType) {
                $fieldType = $phpNamespace->getName() . '\\' . $fieldType;
            }
            $types[] = $fieldType;
        }
        $comments = empty($comments) ? '' : sprintf('@var %s', implode('|', $comments));
        return [
            'types' => implode('|', $types),
            'comments' => $comments
        ];
    }

    /**
     * @param array $apiTypes
     * @param PhpNamespace $phpNamespace
     * @return array
     */
    #[ArrayShape(['types' => "string", 'comments' => "string"])]
    private function parseApiFieldTypes(
        array $apiTypes,
        PhpNamespace $phpNamespace
    ): array {
        $types = [];
        $comments = [];
        foreach ($apiTypes as $apiType) {
            $comments[] = $apiType;
            if (str_starts_with($apiType, 'Array')) {
                $types[] = 'array';
                continue;
            }
            if (ucfirst($apiType) == $apiType) {
                $apiType = $this->namespace . '\\Types\\' . $apiType;
                $phpNamespace->addUse($apiType);
            }
            $types[] = $apiType;
        }
        $comments = empty($comments) ? '' : sprintf('@var %s', implode('|', $comments));
        return [
            'types' => implode('|', $types),
            'comments' => $comments
        ];
    }

    /**
     * @param string $namespace
     * @return PhpFile[]
     */
    #[ArrayShape(['Response' => "\Nette\PhpGenerator\PhpFile"])]
    private function generateDefaultTypes(
        string $namespace
    ): array {
        $file = new PhpFile;
        $phpNamespace = $file->addNamespace($namespace);
        $response = $phpNamespace->addClass('Response')
            ->setType('class');
        $response->addProperty('ok')
            ->setPublic()
            ->setType(Type::BOOL);
        $response->addProperty('result')
            ->setPublic()
            ->setType(Type::MIXED)
            ->setNullable(true)
            ->setValue(null);
        $response->addProperty('errorCode')
            ->setPublic()
            ->setType(Type::INT)
            ->setNullable(true)
            ->setValue(null);
        $response->addProperty('description')
            ->setPublic()
            ->setType(Type::STRING)
            ->setNullable(true)
            ->setValue(null);
        return [
            'Response' => $file
        ];
    }

    /**
     * @return PhpFile[]
     */
    private function generateTypes(): array
    {
        $namespace = $this->namespace . '\\Types';
        $types = $this->generateDefaultTypes($namespace);
        foreach ($this->schema['types'] as $type) {
            $file = new PhpFile;
            $phpNamespace = $file->addNamespace($namespace);
            $typeClass = $phpNamespace->addClass($type['name'])
                ->setType('class');
            foreach ($type['fields'] as $field) {
                ['types' => $fieldTypes, 'comments' => $fieldComments] = $this->parseFieldTypes(
                    $field['types'],
                    $phpNamespace
                );
                $fieldName = self::toCamelCase($field['name']);
                $typeProperty = $typeClass->addProperty($fieldName)
                    ->setPublic()
                    ->setType($fieldTypes);
                if ($field['optional']) {
                    $typeProperty->setNullable(true)
                        ->setValue(null);
                    $fieldComments .= '|null';
                }
                if (!empty($fieldComments)) {
                    $typeProperty->addComment($fieldComments);
                }
            }
            $types[$type['name']] = $file;
        }
        return $types;
    }

    /**
     * @return string
     */
    private function generateApi(): string
    {
        $file = new PhpFile;
        $file->addComment('@noinspection PhpUnused');
        $file->addComment('@noinspection PhpUnusedParameterInspection');
        $phpNamespace = $file->addNamespace($this->namespace);
        $apiClass = $phpNamespace->addClass('API')
            ->setTrait();
        $sendRequest = $apiClass->addMethod('sendRequest')
            ->setPublic()
            ->setAbstract()
            ->setReturnType(Type::MIXED);
        $sendRequest->addParameter('method')
            ->setType(Type::STRING);
        $sendRequest->addParameter('args')
            ->setType(Type::ARRAY);
        foreach ($this->schema['methods'] as $method) {
            $function = $apiClass->addMethod($method['name'])
                ->setPublic()
                ->addBody('$args = get_defined_vars();')
                ->addBody('return $this->sendRequest(__FUNCTION__, $args);');
            $fields = $method['fields'];
            usort(
                $fields,
                function ($a, $b) {
                    return $b['required'] - $a['required'];
                }
            );
            foreach ($fields as $field) {
                $types = $this->parseApiFieldTypes($field['types'], $phpNamespace)['types'];
                $fieldName = self::toCamelCase($field['name']);
                $parameter = $function->addParameter($fieldName)
                    ->setType($types);
                if (!$field['required']) {
                    $parameter->setNullable()
                        ->setDefaultValue(null);
                }
            }
            $returnTypes = $this->parseApiFieldTypes($method['return_types'], $phpNamespace)['types'];
            $function->setReturnType($returnTypes);
        }
        return $file;
    }

    /**
     * @return array
     */
    #[ArrayShape(['types' => "\Nette\PhpGenerator\PhpFile[]", 'api' => "string"])]
    public function generateCode(): array
    {
        return [
            'types' => $this->generateTypes(),
            'api' => $this->generateApi()
        ];
    }

}