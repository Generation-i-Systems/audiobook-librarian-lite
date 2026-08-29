<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * docs/openapi.json is the source of truth for the lite API, so it has to match
 * the routes this server actually registers — in both directions.
 *
 * The one-directional version of this check let the spec keep 114 paths
 * inherited from the full server (books, authors, series, skins, imports,
 * downloads...) that lite does not route and that answer 404.
 */
class OpenApiRouteCoverageTest extends TestCase
{
    private const HTTP_METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    /**
     * @return array<string, array<int, string>> path => sorted methods
     */
    private function specOperations(): array
    {
        $specPath = base_path('docs/openapi.json');
        $this->assertFileExists($specPath, 'OpenAPI specification file (docs/openapi.json) not found.');

        $spec = json_decode((string) file_get_contents($specPath), true, 512, JSON_THROW_ON_ERROR);

        $operations = [];
        foreach ($spec['paths'] ?? [] as $path => $item) {
            $methods = array_values(array_intersect(array_keys($item), self::HTTP_METHODS));
            sort($methods);
            $operations[$path] = $methods;
        }

        return $operations;
    }

    /**
     * @return array<string, array<int, string>> path => sorted methods
     */
    private function routedOperations(): array
    {
        $operations = [];

        /** @var RoutingRoute $route */
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = '/' . ltrim($route->uri(), '/');

            if (!str_starts_with($uri, '/api/v1')) {
                continue;
            }

            $path = '/' . ltrim(substr($uri, strlen('/api/v1')), '/');
            $path = preg_replace('/\{(\w+)\?\}/', '{$1}', $path) ?? $path;

            foreach ($route->methods() as $method) {
                if ($method === 'HEAD') {
                    continue;
                }
                $operations[$path][] = strtolower($method);
            }
        }

        foreach ($operations as $path => $methods) {
            $methods = array_values(array_unique($methods));
            sort($methods);
            $operations[$path] = $methods;
        }

        return $operations;
    }

    #[Test]
    public function every_registered_api_route_is_documented_in_the_openapi_spec(): void
    {
        $spec = $this->specOperations();
        $undocumented = [];

        foreach ($this->routedOperations() as $path => $methods) {
            foreach ($methods as $method) {
                if (!in_array($method, $spec[$path] ?? [], true)) {
                    $undocumented[] = strtoupper($method) . ' ' . $path;
                }
            }
        }

        $this->assertSame(
            [],
            $undocumented,
            "These routes are missing from docs/openapi.json:\n" . implode("\n", $undocumented)
        );
    }

    #[Test]
    public function the_openapi_spec_documents_no_endpoint_this_server_does_not_serve(): void
    {
        $routed = $this->routedOperations();
        $phantom = [];

        foreach ($this->specOperations() as $path => $methods) {
            foreach ($methods as $method) {
                if (!in_array($method, $routed[$path] ?? [], true)) {
                    $phantom[] = strtoupper($method) . ' ' . $path;
                }
            }
        }

        $this->assertSame(
            [],
            $phantom,
            "docs/openapi.json documents endpoints the lite server does not route "
            . "(they answer 404). Lite hosts no book catalog, so full-server endpoints must not "
            . "be carried over:\n" . implode("\n", $phantom)
        );
    }

    #[Test]
    public function every_internal_reference_in_the_spec_resolves(): void
    {
        $spec = json_decode((string) file_get_contents(base_path('docs/openapi.json')), true, 512, JSON_THROW_ON_ERROR);

        $refs = [];
        $collect = static function ($node) use (&$collect, &$refs): void {
            if (!is_array($node)) {
                return;
            }
            if (isset($node['$ref']) && is_string($node['$ref'])) {
                $refs[] = $node['$ref'];
            }
            foreach ($node as $value) {
                $collect($value);
            }
        };
        $collect($spec);

        $dangling = [];
        foreach (array_unique($refs) as $ref) {
            $segments = explode('/', $ref);
            if (count($segments) !== 4 || $segments[0] !== '#' || $segments[1] !== 'components') {
                $dangling[] = $ref;
                continue;
            }
            if (!isset($spec['components'][$segments[2]][$segments[3]])) {
                $dangling[] = $ref;
            }
        }

        sort($dangling);
        $this->assertSame([], $dangling, "docs/openapi.json contains unresolvable \$refs:\n" . implode("\n", $dangling));
    }

    /**
     * Lite identifies a book by title + author because it has no book catalog and
     * therefore no numeric book ids. Nothing in the spec may require one.
     */
    #[Test]
    public function no_endpoint_requires_a_numeric_book_id(): void
    {
        $spec = json_decode((string) file_get_contents(base_path('docs/openapi.json')), true, 512, JSON_THROW_ON_ERROR);

        $offenders = [];
        foreach ($spec['paths'] as $path => $item) {
            if (preg_match('/\{book(Id)?\}/i', $path)) {
                $offenders[] = "path template {$path}";
            }

            foreach ($item as $method => $operation) {
                if (!in_array($method, self::HTTP_METHODS, true)) {
                    continue;
                }
                foreach ($operation['parameters'] ?? [] as $parameter) {
                    if (
                        preg_match('/^book_?id$/i', (string) ($parameter['name'] ?? ''))
                        && ($parameter['required'] ?? false)
                    ) {
                        $offenders[] = strtoupper($method) . ' ' . $path . ' requires parameter ' . $parameter['name'];
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Lite has no book ids; books are identified by title and author:\n" . implode("\n", $offenders)
        );
    }

    /**
     * The event payload is the one place a full-server client may still send a
     * bookId. It must be optional, and title/author must be documented alongside it.
     */
    #[Test]
    public function the_listening_event_schema_is_keyed_by_title_and_author(): void
    {
        $spec = json_decode((string) file_get_contents(base_path('docs/openapi.json')), true, 512, JSON_THROW_ON_ERROR);
        $event = $spec['components']['schemas']['ListeningEvent'] ?? null;

        $this->assertIsArray($event, 'The ListeningEvent schema is missing from docs/openapi.json.');
        $this->assertArrayHasKey('bookTitle', $event['properties']);
        $this->assertArrayHasKey('bookAuthor', $event['properties']);
        $this->assertNotContains('bookId', $event['required'] ?? [], 'bookId must never be required.');
        $this->assertTrue(
            $event['properties']['bookId']['deprecated'] ?? false,
            'bookId is accepted only for full-server client compatibility and must be marked deprecated.'
        );
    }
}
