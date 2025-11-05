<?php

declare(strict_types=1);

use App\Support\Scribe\Writing\OpenApiSpecGenerators\ScalarOpenApiGenerator;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Collection;
use Knuckles\Camel\Extraction\Metadata;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Symfony\Component\HttpFoundation\Request;

covers(ScalarOpenApiGenerator::class);

beforeEach(function (): void {
    $this->configRepository = Mockery::mock(Repository::class);
    $this->configRepository
        ->shouldReceive('get')
        ->with('scribe.groups.default')
        ->andReturn('_UNGROUPED');

    $this->generator = new ScalarOpenApiGenerator(
        new DocumentationConfig,
        $this->configRepository
    );
});

describe('Scribe | ScalarOpenApiGenerator', function (): void {
    it('calls parent constructor and initializes base properties', function (): void {
        $reflection = new ReflectionClass($this->generator);
        $property = $reflection->getParentClass()->getProperty('config');

        $value = $property->getValue($this->generator);
        expect($value)->toBeInstanceOf(DocumentationConfig::class);
    });

    it('adds tags and x-tagGroups for grouped endpoints with subgroups', function (): void {
        $endpoint1 = new OutputEndpointData([
            'uri' => '/auth',
            'httpMethods' => [Request::METHOD_GET],
            'metadata' => new Metadata([
                'groupName' => 'Users',
                'subgroup' => 'Authentication',
                'subgroupDescription' => 'Endpoints related to auth',
            ]),
        ]);
        $endpoint2 = new OutputEndpointData([
            'uri' => '/profiles',
            'httpMethods' => [Request::METHOD_GET],
            'metadata' => new Metadata([
                'groupName' => 'Users',
                'subgroup' => 'Profiles',
                'subgroupDescription' => 'User profile management',
            ]),
        ]);

        $groupedEndpoints = [[
            'name' => 'Users',
            'description' => 'User operations',
            'endpoints' => [$endpoint1, $endpoint2],
        ]];

        $root = $this->generator->root([], $groupedEndpoints);

        expect($root)
            ->toHaveKey('tags')
            ->and($root['tags'])
            ->toContain([
                'name' => 'Users Authentication',
                'x-displayName' => 'Authentication',
                'description' => 'Endpoints related to auth',
            ], [
                'name' => 'Users Profiles',
                'x-displayName' => 'Profiles',
                'description' => 'User profile management',
            ], [
                'name' => '_UNGROUPED',
            ])
            ->and($root['x-tagGroups'][0])
            ->toHaveKeys(['name', 'tags'])
            ->and($root['x-tagGroups'][0]['name'])->toBe('Users')
            ->and($root['x-tagGroups'][0]['tags'])
            ->toContain('Users Authentication', 'Users Profiles', 'Users _UNGROUPED');

    });

    it('continues processing endpoints after encountering one without subgroup', function (): void {
        $endpoint1 = new OutputEndpointData([
            'uri' => '/no-subgroup',
            'httpMethods' => [Request::METHOD_GET],
            'metadata' => new Metadata([
                'groupName' => 'Mixed',
                'subgroup' => '',
            ]),
        ]);

        $endpoint2 = new OutputEndpointData([
            'uri' => '/with-subgroup',
            'httpMethods' => [Request::METHOD_GET],
            'metadata' => new Metadata([
                'groupName' => 'Mixed',
                'subgroup' => 'Valid',
                'subgroupDescription' => 'Valid subgroup',
            ]),
        ]);

        $groupedEndpoints = [[
            'name' => 'Mixed',
            'endpoints' => [$endpoint1, $endpoint2],
        ]];

        $root = $this->generator->root([], $groupedEndpoints);

        expect(array_column($root['tags'], 'name'))->toContain('Mixed Valid');
    });

    it('avoids duplicate subgroup tags using tagsHashmap', function (): void {
        $metadata1 = new Metadata([
            'groupName' => 'Products',
            'subgroup' => 'Catalog',
        ]);

        $metadata2 = new Metadata([
            'groupName' => 'Products',
            'subgroup' => 'Details',
        ]);

        $endpoint1 = new OutputEndpointData([
            'uri' => '/endpoint/1',
            'httpMethods' => [Request::METHOD_GET],
            'metadata' => $metadata1,
        ]);
        $endpoint2 = new OutputEndpointData([
            'uri' => '/endpoint/2',
            'httpMethods' => [Request::METHOD_GET],
            'metadata' => $metadata1,
        ]);
        $endpoint3 = new OutputEndpointData([
            'uri' => '/endpoint/3',
            'httpMethods' => [Request::METHOD_GET],
            'metadata' => $metadata2,
        ]);

        $groupedEndpoints = [[
            'name' => 'Products',
            'description' => 'desc',
            'endpoints' => [$endpoint1, $endpoint2, $endpoint3],
        ]];

        $root = $this->generator->root([], $groupedEndpoints);

        $tagNames = new Collection($root['tags'])->pluck('name');

        expect($tagNames)->toContain('Products Catalog', 'Products Details', '_UNGROUPED')
            ->and($tagNames->filter(fn ($t): bool => $t === 'Products Catalog'))->toHaveCount(1);
    });

    it('sorts tag names alphabetically within each tagGroup', function (): void {
        $endpointA = new OutputEndpointData([
            'uri' => '/a',
            'httpMethods' => [Request::METHOD_GET],
            'metadata' => new Metadata([
                'groupName' => 'Sorted',
                'subgroup' => 'Zeta',
            ]),
        ]);
        $endpointB = new OutputEndpointData([
            'uri' => '/b',
            'httpMethods' => [Request::METHOD_GET],
            'metadata' => new Metadata([
                'groupName' => 'Sorted',
                'subgroup' => 'Alpha',
            ]),
        ]);

        $groupedEndpoints = [[
            'name' => 'Sorted',
            'endpoints' => [$endpointA, $endpointB],
        ]];

        $root = $this->generator->root([], $groupedEndpoints);

        $tags = $root['x-tagGroups'][0]['tags'];
        expect($tags)->toBe(['Sorted Alpha', 'Sorted Zeta', 'Sorted _UNGROUPED']);
    });

    it('handles case when x-tagGroups already exists and is an array', function (): void {
        $endpoint = new OutputEndpointData([
            'uri' => '/orders',
            'httpMethods' => [Request::METHOD_GET],
            'metadata' => new Metadata([
                'groupName' => 'Orders',
                'subgroup' => 'Checkout',
            ]),
        ]);

        $groupedEndpoints = [[
            'name' => 'Orders',
            'description' => 'description',
            'endpoints' => [$endpoint],
        ]];

        $root = [
            'x-tagGroups' => [['name' => 'ExistingGroup', 'tags' => ['ExistingTag']]],
        ];

        $result = $this->generator->root($root, $groupedEndpoints);

        expect($result['x-tagGroups'])
            ->toHaveCount(2)
            ->and($result['x-tagGroups'][1]['name'])->toBe('Orders');
    });

    it('skips endpoints without subgroup', function (): void {
        $endpoint = new OutputEndpointData([
            'uri' => '/users',
            'httpMethods' => [Request::METHOD_GET],
            'metadata' => new Metadata([
                'groupName' => 'Users',
            ]),
        ]);

        $groupedEndpoints = [[
            'name' => 'Users',
            'description' => 'User operations',
            'endpoints' => [$endpoint],
        ]];

        $root = $this->generator->root([], $groupedEndpoints);

        expect($root['tags'])->toContain(['name' => '_UNGROUPED'])
            ->and($root['x-tagGroups'][0]['tags'])->toContain('Users _UNGROUPED');
    });

    it('does not duplicate tags if the same subgroup appears multiple times', function (): void {
        $metadata = new Metadata([
            'groupName' => 'Users',
            'subgroup' => 'Authentication',
            'subgroupDescription' => 'Duplicate test',
        ]);

        $endpoint1 = new OutputEndpointData([
            'uri' => '/auth/login',
            'httpMethods' => [Request::METHOD_GET],
            'metadata' => $metadata,
        ]);
        $endpoint2 = new OutputEndpointData([
            'uri' => '/auth/register',
            'httpMethods' => [Request::METHOD_GET],
            'metadata' => $metadata,
        ]);

        $groupedEndpoints = [[
            'name' => 'Users',
            'endpoints' => [$endpoint1, $endpoint2],
        ]];

        $root = $this->generator->root([], $groupedEndpoints);

        $names = array_column($root['tags'], 'name');
        $count = array_count_values($names);

        expect($count['Users Authentication'])->toBe(1);
    });

    it('sets default subgroup when subgroup is :dataset', function (?string $subgroup): void {
        $metadata = new Metadata([
            'groupName' => 'Users',
            'subgroup' => $subgroup,
        ]);

        $method = new ReflectionMethod($this->generator, 'generateTagNameFromMetadata');
        $tagName = $method->invoke($this->generator, $metadata);

        expect($tagName)->toBe('Users _UNGROUPED');
    })->with([
        'null' => [null],
        'empty' => [''],
    ]);

    it('adds tags for pathItem method', function (): void {
        $endpoint = new OutputEndpointData([
            'uri' => '/settings',
            'httpMethods' => [Request::METHOD_GET],
            'metadata' => new Metadata([
                'groupName' => 'Users',
                'subgroup' => 'Settings',
            ]),
        ]);

        $pathItem = $this->generator->pathItem([], [], $endpoint);

        expect($pathItem['tags'])->toBe(['Users Settings']);
    });
});
