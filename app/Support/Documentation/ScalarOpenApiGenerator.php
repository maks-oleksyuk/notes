<?php

declare(strict_types=1);

namespace App\Support\Documentation;

use Illuminate\Contracts\Config\Repository;
use Knuckles\Camel\Extraction\Metadata;
use Knuckles\Camel\Output\OutputEndpointData;
use Knuckles\Scribe\Tools\DocumentationConfig;
use Knuckles\Scribe\Writing\OpenApiSpecGenerators\OpenApiGenerator;

final class ScalarOpenApiGenerator extends OpenApiGenerator
{
    public function __construct(
        protected DocumentationConfig $config,
        private readonly Repository $configRepository,
    ) {
        parent::__construct($config);
    }

    /**
     * @param  array<string, mixed>  $root
     * @return array<string, mixed>
     *
     * @throws \JsonException
     */
    #[\Override]
    public function root(array $root, array $groupedEndpoints): array
    {
        $this->config->data['external']['html_attributes'] = [
            'data-configuration' => htmlspecialchars(json_encode([
                'defaultHttpClient' => ['targetKey' => 'js', 'clientKey' => 'fetch'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES),
        ];

        $tags = [];
        $tagsHashmap = [];

        foreach ($groupedEndpoints as $groupedEndpoint) {
            $grouped = [];

            foreach ($groupedEndpoint['endpoints'] as $endpoint) {
                /** @var Metadata $metadata */
                $metadata = $endpoint['metadata'];

                if (empty($metadata->subgroup)) {
                    continue;
                }

                $tagName = $this->generateTagNameFromMetadata($metadata);

                if (isset($tagsHashmap[$tagName])) {
                    continue;
                }

                $tagsHashmap[$tagName] = true;
                $tags[] = [
                    'name' => $tagName,
                    'x-displayName' => $metadata->subgroup,
                    'description' => $metadata->subgroupDescription,
                ];
                $grouped[] = $tagName;
            }

            sort($grouped, SORT_STRING);
            $root['x-tagGroups'][] = [
                'name' => $groupedEndpoint['name'],
                'tags' => array_merge($grouped, [
                    // Tag for an endpoint with a group but without a subgroup.
                    $groupedEndpoint['name'].' '.$this->configRepository->get('scribe.groups.default'),
                ]),
            ];
        }

        // set default(_UNGROUPED) tag.
        $tags[] = ['name' => $this->configRepository->get('scribe.groups.default')];
        $root['tags'] = $tags;

        return $root;
    }

    /**
     * @param  array<string, mixed>  $pathItem
     * @return array<string, mixed>
     */
    #[\Override]
    public function pathItem(array $pathItem, array $groupedEndpoints, OutputEndpointData $endpoint): array
    {
        /** @var Metadata $metadata */
        $metadata = $endpoint['metadata'];

        $pathItem['tags'] = [$this->generateTagNameFromMetadata($metadata)];

        return $pathItem;
    }

    private function generateTagNameFromMetadata(Metadata $metadata): string
    {
        return mb_trim($metadata->groupName.' '.($metadata->subgroup ?: $this->configRepository->get('scribe.groups.default')));
    }
}
