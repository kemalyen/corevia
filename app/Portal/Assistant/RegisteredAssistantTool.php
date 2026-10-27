<?php

namespace App\Portal\Assistant;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RegisteredAssistantTool implements Tool
{
    /**
     * @param  array<string, mixed>  $definition
     * @param  Closure(string, array<string, mixed>): array<string, mixed>  $execute
     */
    public function __construct(
        private readonly array $definition,
        private readonly Closure $execute,
    ) {}

    public function name(): string
    {
        return (string) $this->definition['function']['name'];
    }

    public function description(): Stringable|string
    {
        return (string) $this->definition['function']['description'];
    }

    public function handle(Request $request): Stringable|string
    {
        return json_encode(($this->execute)($this->name(), $request->all()), JSON_THROW_ON_ERROR);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        $parameters = $this->definition['function']['parameters'];
        $required = $parameters['required'] ?? [];
        $types = [];

        foreach ($parameters['properties'] as $name => $property) {
            $type = match ($property['type']) {
                'integer' => $schema->integer(),
                'number' => $schema->number(),
                'boolean' => $schema->boolean(),
                default => $schema->string(),
            };

            if (isset($property['description'])) {
                $type->description($property['description']);
            }

            if (isset($property['minimum']) && method_exists($type, 'min')) {
                $type->min($property['minimum']);
            }

            if (isset($property['maximum']) && method_exists($type, 'max')) {
                $type->max($property['maximum']);
            }

            $types[$name] = $type->required(in_array($name, $required, true));
        }

        return $types;
    }
}
