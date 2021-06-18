<?php
/** @noinspection PhpInternalEntityUsedInspection */


namespace TgScraper;


use InvalidArgumentException;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Type;

/**
 * Class StubCreator
 * @package TgScraper
 */
class StubCreator
{

    /**
     * @var string
     */
    private string $namespace;

    /**
     * StubCreator constructor.
     * @param array $scheme
     * @param string $namespace
     */
    public function __construct(private array $scheme, string $namespace = '')
    {
        if (str_ends_with($namespace, '\\')) {
            $namespace = substr($namespace, 0, -1);
        }
        if (!empty($namespace)) {
            if (!Helpers::isNamespaceIdentifier($namespace)) {
                throw new InvalidArgumentException('Namespace invalid');
            }
        }
        $this->namespace = $namespace;
    }

    /**
     * @param array $fieldTypes
     * @param PhpNamespace $phpNamespace
     * @return array
     */
    private function parseFieldTypes(array $fieldTypes, PhpNamespace $phpNamespace): array
    {
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
    private function parseApiFieldTypes(array $apiTypes, PhpNamespace $phpNamespace): array
    {
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
    private function generateDefaultTypes(string $namespace): array
    {
        $file = new PhpFile;
        $phpNamespace = $file->addNamespace($namespace);
        $response = $phpNamespace->addClass('Response')
            ->setType('class');
        $response->addProperty('ok')
            ->setPublic();
        $response->addProperty('result')
            ->setPublic();
        $response->addProperty('error_code')
            ->setPublic();
        $response->addProperty('description')
            ->setPublic();
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
        foreach ($this->scheme['types'] as $type) {
            $file = new PhpFile;
            $phpNamespace = $file->addNamespace($namespace);
            $typeClass = $phpNamespace->addClass($type['name'])
                ->setType('class');
            foreach ($type['fields'] as $field) {
                ['types' => $fieldTypes, 'comments' => $fieldComments] = $this->parseFieldTypes(
                    $field['types'],
                    $phpNamespace
                );
                $type_property = $typeClass->addProperty($field['name'])
                    ->setPublic()
                    ->setType($fieldTypes);
                if (!empty($fieldComments)) {
                    $type_property->addComment($fieldComments);
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
        $file->addComment('@noinspection PhpIncompatibleReturnTypeInspection');
        $file->addComment('@noinspection PhpVoidFunctionResultUsedInspection');
        $phpNamespace = $file->addNamespace($this->namespace);
        $apiClass = $phpNamespace->addClass('API')
            ->setType('class');
        $apiClass->addMethod('__construct')
            ->setPublic()
            ->addPromotedParameter('client')
            ->setType('\GuzzleHttp\Client')
            ->setPrivate();
        $sendRequest = $apiClass->addMethod('sendRequest')
            ->setPublic();
        $sendRequest->addParameter('method')
            ->setType(Type::STRING);
        $sendRequest->addParameter('args')
            ->setType(Type::ARRAY);
        $sendRequest->addBody('//TODO: add your logic here');
        foreach ($this->scheme['methods'] as $method) {
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
                $parameter = $function->addParameter($field['name'])
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
    public function generateCode(): array
    {
        return [
            'types' => $this->generateTypes(),
            'api' => $this->generateApi()
        ];
    }

}