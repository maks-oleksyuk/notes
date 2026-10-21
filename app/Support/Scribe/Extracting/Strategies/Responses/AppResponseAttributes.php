<?php

declare(strict_types=1);

namespace App\Support\Scribe\Extracting\Strategies\Responses;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Extracting\Shared\ApiResourceResponseTools;
use Knuckles\Scribe\Extracting\Strategies\Responses\UseResponseAttributes;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PropertyTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

/**
 * Scribe's UseResponseAttributes, plus: when no model is given, the resource's `@property ... $resource`
 * docblock is used — an Eloquent model class becomes the model, an `array{...}` shape becomes example input.
 */
final class AppResponseAttributes extends UseResponseAttributes
{
    private const array LIST_TYPES = ['list', 'non-empty-list', 'array', 'non-empty-array', 'iterable'];

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getApiResourceResponse(ResponseFromApiResource $attributeInstance): array
    {
        if ($attributeInstance->modelToBeTransformed() === null) {
            /** @var class-string $resourceClass */
            $resourceClass = $attributeInstance->name;
            $resource = new \ReflectionClass($resourceClass);
            $type = $this->resourcePropertyType($resource);

            if ($type instanceof ArrayShapeNode) {
                return $this->arrayResourceResponse($attributeInstance, $this->exampleFromShape($type));
            }

            if ($type instanceof IdentifierTypeNode && ($model = $this->modelClass($resource, $type->name))) {
                $attributeInstance = clone ($attributeInstance, [
                    'model' => $model,
                ]);
            }
        }

        /** @var array<string, mixed> $response */
        $response = parent::getApiResourceResponse($attributeInstance);

        return $response;
    }

    /**
     * @param  array<array-key, mixed>  $example
     * @return array<string, mixed>
     */
    private function arrayResourceResponse(ResponseFromApiResource $attributeInstance, array $example): array
    {
        return [
            'status' => $attributeInstance->status,
            'description' => $attributeInstance->description,
            'content' => ApiResourceResponseTools::fetch(
                $attributeInstance->name,
                $attributeInstance->isCollection(),
                fn (): array => $example,
                $this->endpointData ?? throw new \LogicException('Endpoint data is set in __invoke().'),
                [],
                $attributeInstance->additional,
            ),
        ];
    }

    /**
     * @param  \ReflectionClass<object>  $resource
     */
    private function resourcePropertyType(\ReflectionClass $resource): ?TypeNode
    {
        $docComment = $resource->getDocComment();

        if ($docComment === false) {
            return null;
        }

        $config = new ParserConfig([]);
        $constExprParser = new ConstExprParser($config);
        $phpDocParser = new PhpDocParser($config, new TypeParser($config, $constExprParser), $constExprParser);
        $phpDocNode = $phpDocParser->parse(new TokenIterator(new Lexer($config)->tokenize($docComment)));

        return Arr::first(
            $phpDocNode->getPropertyTagValues(),
            fn (PropertyTagValueNode $property): bool => $property->propertyName === '$resource',
        )?->type;
    }

    /**
     * Resolves a docblock class name against the resource file's namespace and `use` imports.
     *
     * @param  \ReflectionClass<object>  $resource
     * @return class-string<Model>|null
     */
    private function modelClass(\ReflectionClass $resource, string $typeName): ?string
    {
        /** @var string $file Resources are user classes, so they always have a source file. */
        $file = $resource->getFileName();

        $nameResolver = new NameResolver;
        new NodeTraverser($nameResolver)->traverse(
            new ParserFactory()->createForHostVersion()->parse(new Filesystem()->get($file)) ?? [],
        );

        $name = str_starts_with($typeName, '\\') ? new FullyQualified(mb_ltrim($typeName, '\\')) : new Name($typeName);
        $class = $nameResolver->getNameContext()->getResolvedClassName($name)->toString();

        return is_subclass_of($class, Model::class) ? $class : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function exampleFromShape(ArrayShapeNode $shape): array
    {
        $example = [];

        foreach ($shape->items as $item) {
            $key = match (true) {
                $item->keyName instanceof IdentifierTypeNode => $item->keyName->name,
                $item->keyName instanceof ConstExprStringNode,
                $item->keyName instanceof ConstExprIntegerNode => $item->keyName->value,
                default => count($example),
            };

            $example[$key] = $this->exampleFromType($item->valueType, (string) $key);
        }

        return $example;
    }

    private function exampleFromType(TypeNode $type, string $name): mixed
    {
        return match (true) {
            $type instanceof ArrayShapeNode => $this->exampleFromShape($type),
            $type instanceof NullableTypeNode => $this->exampleFromType($type->type, $name),
            $type instanceof UnionTypeNode => $this->exampleFromType(
                Arr::first($type->types, fn (TypeNode $member): bool => (string) $member !== 'null') ?? new IdentifierTypeNode('null'),
                $name,
            ),
            $type instanceof ArrayTypeNode => [$this->exampleFromType($type->type, $name)],
            $type instanceof GenericTypeNode && in_array($type->type->name, self::LIST_TYPES, true) => [
                $this->exampleFromType(Arr::last($type->genericTypes) ?? $type->type, $name),
            ],
            Str::endsWith($name, '_at') => $this->getFaker()->dateTime()->format(DATE_ATOM),
            default => $this->generateDummyValue(self::normalizeTypeName((string) $type), ['name' => $name]),
        };
    }
}
