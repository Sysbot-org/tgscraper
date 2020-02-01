<?php


namespace TGBotApi;

use Nette\{PhpGenerator, PhpGenerator\Type};

class StubProvider
{

    private $namespace_prefix = '';
    private $methods = [];
    private $types = [];

    /**
     * StubProvider constructor.
     * @param string $namespace_prefix
     * @throws \Exception
     */
    public function __construct(string $namespace_prefix = '')
    {
        if (substr($namespace_prefix, -1) == '\\') {
            $namespace_prefix = substr($namespace_prefix, 0, -1);
        }
        if (!empty($namespace_prefix)) {
            if (!PhpGenerator\Helpers::isNamespaceIdentifier($namespace_prefix)) {
                throw new \Exception('Invalid namespace prefix provided');
            }
            $namespace_prefix .= '\\';
        }
        $types_namespace = $namespace_prefix . 'Types';
        $this->namespace_prefix = $namespace_prefix;
        $this->methods = $this->createDefaultMethods();
        $this->types = $this->createDefaultTypes($types_namespace);
    }

    private function createDefaultMethods(): array
    {
        $method_inteface = (new PhpGenerator\ClassType('MethodInterface'))
            ->setInterface();
        $method_inteface->addMethod('getParams')
            ->setPublic()
            ->setReturnType(Type::ARRAY);
        $method_inteface->addMethod('getMethodName')
            ->setPublic()
            ->setStatic()
            ->setReturnType(Type::STRING);
        $method_inteface->addMethod('isMultipart')
            ->setPublic()
            ->setReturnType(Type::BOOL);
        $method_inteface->addMethod('getResultParams')
            ->setPublic()
            ->setStatic()
            ->setReturnType(Type::ARRAY);
        $method_abstract = (new PhpGenerator\ClassType('DefaultMethod'))
            ->setClass()
            ->setAbstract()
            ->addImplement('MethodInterface');
        $method_abstract->addConstant('METHOD_NAME', '')
            ->setPrivate();
        $method_abstract->addConstant('RESULT_TYPE', '')
            ->setPrivate();
        $method_abstract->addConstant('MULTIPLE_RESULTS', false)
            ->setPrivate();
        $method_abstract->addProperty('multipart', false)
            ->setPrivate();
        $method_abstract->addMethod('getMethodName')
            ->setPublic()
            ->setStatic()
            ->setReturnType(Type::STRING)
            ->setBody('return static::METHOD_NAME;');
        $method_abstract->addMethod('isMultipart')
            ->setPublic()
            ->setReturnType(Type::BOOL)
            ->setBody('return $this->multipart;');
        $method_abstract->addMethod('getResultParams')
            ->setPublic()
            ->setStatic()
            ->setReturnType(Type::ARRAY)
            ->addBody('return [')
            ->addBody('    \'type\' => static::RESULT_TYPE,')
            ->addBody('    \'multiple\' => static::MULTIPLE_RESULTS')
            ->addBody('];');
        return [
            'MethodInterface' => $this->addNamespace($method_inteface, 'Methods'),
            'DefaultMethod' => $this->addNamespace($method_abstract, 'Methods')
        ];
    }

    private function addNamespace(string $code, string $sub_namespace): string
    {
        return '<?php' . str_repeat(PHP_EOL,
                2) . 'namespace ' . $this->namespace_prefix . $sub_namespace . ';' . str_repeat(PHP_EOL, 2) . $code;
    }

    private function createDefaultTypes(string $namespace): array
    {
        $response = (new PhpGenerator\ClassType('Response'))
            ->setType('class');
        $response->addProperty('ok')
            ->setPublic();
        $response->addProperty('result')
            ->setPublic();
        $response->addProperty('error_code')
            ->setPublic();
        $response->addProperty('description')
            ->setPublic();
        $response->addMethod('parseResponse')
            ->setReturnType(Type::SELF)
            ->setReturnNullable(true)
            ->setPublic()
            ->setStatic()
            ->setBody('if (null == $response) {
            return null;
        }
        $parsed_response = (new self())
            ->setOk($response->ok ?? null)
            ->setErrorCode($response->error_code ?? null)
            ->setDescription($response->description ?? null);
        if (empty($response->result)) {
            $parsed_response->setResult(null);
        } elseif (!empty($response->result->migrate_to_chat_id) or !empty($response->result->retry_after)) {
            $parsed_response->setResult(ResponseParameters::parseResponseParameters($response->result ?? null));
        } elseif (!empty($response->result_type)) {
            $result_class = sprintf(\'' . $namespace . '\\%s\', $response->result_type->class);
            $parsed_response->setResult(call_user_func([$result_class, $response->result_type->method],
                $response->result ?? null));
        } else {
            $parsed_response->setResult($response->result ?? null);
        }
        return $parsed_response;')
            ->addParameter('response')
            ->setType('\stdClass')
            ->setNullable(true);
        $this->createSetters($response, [
            ['name' => 'ok', 'type' => 'bool', 'nullable' => true],
            ['name' => 'result', 'type' => null, 'nullable' => false],
            ['name' => 'error_code', 'type' => 'int', 'nullable' => true],
            ['name' => 'description', 'type' => 'string', 'nullable' => true]
        ]);
        return ['Response' => $this->addNamespace($response, 'Types')];
    }

    private function createSetters(PhpGenerator\ClassType $class, array $properties): void
    {
        foreach ($properties as $property) {
            $class->addMethod('set' . $this->getCamelCaseName($property['name']))
                ->setPublic()
                ->setReturnType(Type::SELF)
                ->setBody('$this->' . $property['name'] . ' = $' . $property['name'] . ';' . PHP_EOL . 'return $this;')
                ->addParameter($property['name'])
                ->setType($property['type'])
                ->setNullable($property['nullable']);
        }
        return;
    }

    private function getCamelCaseName(string $snake_case): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $snake_case)));
    }

    public function generateCode(array $scheme): array
    {
        foreach ($scheme['types'] as $type) {
            $type_class = (new PhpGenerator\ClassType($type['name']))
                ->setType('class');
            $fields = [];
            foreach ($type['fields'] as $field) {
                $type_class->addProperty($field['name'])
                    ->setPublic()
                    ->setType($field['type']);
                $fields[] = [
                    'name' => $field['name'],
                    'type' => $field['type'],
                    'nullable' => true
                ];
            }
            $this->createSetters($type_class, $fields);
            $this->types[$type['name']] = $this->addNamespace($type_class, 'Types');
        }
        foreach ($scheme['methods'] as $method) {
            $method_class = (new PhpGenerator\ClassType(ucfirst($method['name'])))
                ->setType('class')
                ->addExtend('DefaultMethod');
            $method_class->addConstant('METHOD_NAME', $method['name'])
                ->setPrivate();
            $return_type = $method['return_types'][0];
            $multiple_results = false;
            if (substr($return_type, -2) == '[]') {
                $return_type = substr($return_type, 0, -2);
                $multiple_results = true;
            }
            $method_class->addConstant('RESULT_TYPE', $return_type)
                ->setPrivate();
            $method_class->addConstant('MULTIPLE_RESULTS', $multiple_results)
                ->setPrivate();
            $fields = [];
            $get_params = $method_class->addMethod('getParams')
                ->setPublic()
                ->setReturnType(Type::ARRAY)
                ->addBody('return [');
            $last_index = array_key_last($method['fields']);
            foreach ($method['fields'] as $index => $field) {
                $field_type = count($field['types']) == 0 ? $field['types'][0] : null; //maybe there will be a better implementation
                $method_class->addProperty($field['name'])
                    ->setPublic()
                    ->setType($field_type);
                $comma = $index != $last_index ? ',' : '';
                $get_params->addBody('    \'' . $field['name'] . '\' => $this->' . $field['name'] . $comma);
                $fields[] = [
                    'name' => $field['name'],
                    'type' => $field_type,
                    'nullable' => true
                ];
            }
            $get_params->addBody('];');
            $this->createConstructor($method_class, $fields);
            $this->methods[ucfirst($method['name'])] = $this->addNamespace($method_class, 'Methods');
        }
        return [
            'methods' => $this->methods,
            'types' => $this->types
        ];
    }

    private function createConstructor(PhpGenerator\ClassType $class, array $properties): void
    {
        $method = $class->addMethod('__construct');
        foreach ($properties as $property) {
            $method->addBody('$this->' . $property['name'] . ' = $' . $property['name'] . ';')
                ->addParameter($property['name'])
                ->setType($property['type'])
                ->setNullable($property['nullable']);
        }
        return;
    }
}