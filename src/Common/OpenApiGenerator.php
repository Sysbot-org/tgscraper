<?php

namespace TgScraper\Common;

class OpenApiGenerator
{


    public function __construct(private array $defaultResponses, private array $data, array $types, array $methods)
    {
        $this->addTypes($types);
        $this->addMethods($methods);
    }

    public function setVersion($version = '1.0.0'): self
    {
        $this->data['info']['version'] = $version;
        return $this;
    }

    public function addMethods(array $methods): self
    {
        foreach ($methods as $method) {
            $this->addMethod($method['name'], $method['description'], $method['fields'], $method['return_types']);
        }
        return $this;
    }

    public function addMethod(string $name, string $description, array $fields, array $returnTypes): self
    {
        $method = '/' . $name;
        $this->data['paths'][$method] = ['description' => $description];
        $path = [];
        $fields = self::addFields(['type' => 'object'], $fields);
        $content = ['schema' => $fields];
        if (!empty($fields['required'])) {
            $path['requestBody']['required'] = true;
        }
        $path['requestBody']['content'] = [
            'application/json' => $content,
            'application/x-www-form-urlencoded' => $content,
            'multipart/form-data' => $content
        ];
        $path['responses'] = $this->defaultResponses;
        $path['responses']['200']['content']['application/json']['schema']
        ['allOf'][1]['properties']['result'] = self::parsePropertyTypes($returnTypes);
        $this->data['paths'][$method]['post'] = $path;
        return $this;
    }

    public function addTypes(array $types): self
    {
        foreach ($types as $type) {
            $this->addType($type['name'], $type['description'], $type['fields'], $type['extended_by']);
        }
        return $this;
    }

    public function addType(string $name, string $description, array $fields, array $extendedBy): self
    {
        $schema = ['description' => $description];
        $schema = self::addFields($schema, $fields);
        $this->data['components']['schemas'][$name] = $schema;
        if (!empty($extendedBy)) {
            foreach ($extendedBy as $extendedType) {
                $this->data['components']['schemas'][$name]['anyOf'][] = self::parsePropertyType($extendedType);
            }
            return $this;
        }
        $this->data['components']['schemas'][$name]['type'] = 'object';
        return $this;
    }

    private static function addFields(array $schema, array $fields): array
    {
        foreach ($fields as $field) {
            $name = $field['name'];
            $required = !$field['optional'];
            if ($required) {
                $schema['required'][] = $name;
            }
            $schema['properties'][$name] = self::parsePropertyTypes($field['types']);
            if (!empty($field['default'] ?? null)) {
                $schema['properties'][$name]['default'] = $field['default'];
            }
        }
        return $schema;
    }

    private static function parsePropertyTypes(array $types): array
    {
        $result = [];
        $hasMultipleTypes = count($types) > 1;
        foreach ($types as $type) {
            $type = self::parsePropertyType(trim($type));
            if ($hasMultipleTypes) {
                $result['anyOf'][] = $type;
                continue;
            }
            $result = $type;
        }
        return $result;
    }

    private static function parsePropertyType(string $type): array
    {
        if (str_starts_with($type, 'Array')) {
            return self::parsePropertyArray($type);
        }
        if (lcfirst($type) == $type) {
            $type = str_replace(['int', 'float', 'bool'], ['integer', 'number', 'boolean'], $type);
            return ['type' => $type];
        }
        return ['$ref' => '#/components/schemas/' . $type];
    }

    private static function parsePropertyArray(string $type): array
    {
        if (preg_match('/Array<(.+)>/', $type, $matches) === 1) {
            return [
                'type' => 'array',
                'items' => self::parsePropertyTypes(explode('|', $matches[1]))
            ];
        }
        return [];
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}