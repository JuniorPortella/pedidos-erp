<?php

declare(strict_types=1);

namespace Tests\Unit\Documentation;

use PHPUnit\Framework\TestCase;

final class OpenApiTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $document;

    protected function setUp(): void
    {
        parent::setUp();

        $contents = file_get_contents(
            dirname(__DIR__, 3) . '/public/openapi.json'
        );

        self::assertIsString($contents);

        $document = json_decode(
            $contents,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertIsArray($document);

        $this->document = $document;
    }

    public function testDefinesOpenApiMetadataAndComponents(): void
    {
        self::assertSame('3.1.0', $this->document['openapi']);
        self::assertSame(
            'PedidosFull API',
            $this->document['info']['title']
        );

        self::assertArrayHasKey(
            'securitySchemes',
            $this->document['components']
        );
        self::assertArrayHasKey(
            'schemas',
            $this->document['components']
        );
    }

    public function testDocumentsEveryRegisteredRoute(): void
    {
        $routesSource = file_get_contents(
            dirname(__DIR__, 3) . '/routes/api.php'
        );

        self::assertIsString($routesSource);

        preg_match_all(
            '/\$router->(get|post|put|delete)\(\s*\'([^\']+)\'/',
            $routesSource,
            $matches,
            PREG_SET_ORDER
        );

        $registered = array_map(
            static fn (array $match): string => sprintf(
                '%s %s',
                strtoupper($match[1]),
                $match[2]
            ),
            $matches
        );

        $documented = [];
        $httpMethods = ['get', 'post', 'put', 'delete'];

        foreach ($this->document['paths'] as $path => $pathItem) {
            foreach ($httpMethods as $method) {
                if (array_key_exists($method, $pathItem)) {
                    $documented[] = sprintf(
                        '%s %s',
                        strtoupper($method),
                        $path
                    );
                }
            }
        }

        sort($registered);
        sort($documented);

        self::assertSame($registered, $documented);
    }

    public function testUsesUniqueOperationIdentifiers(): void
    {
        $operationIds = [];

        foreach ($this->document['paths'] as $pathItem) {
            foreach (['get', 'post', 'put', 'delete'] as $method) {
                if (isset($pathItem[$method]['operationId'])) {
                    $operationIds[] =
                        $pathItem[$method]['operationId'];
                }
            }
        }

        self::assertCount(
            count(array_unique($operationIds)),
            $operationIds
        );
    }

    public function testEveryLocalReferenceCanBeResolved(): void
    {
        $references = [];

        $this->collectReferences($this->document, $references);

        self::assertNotEmpty($references);

        foreach (array_unique($references) as $reference) {
            self::assertStringStartsWith('#/', $reference);

            $value = $this->document;

            foreach (explode('/', substr($reference, 2)) as $segment) {
                $segment = str_replace(
                    ['~1', '~0'],
                    ['/', '~'],
                    $segment
                );

                self::assertIsArray($value);
                self::assertArrayHasKey($segment, $value);

                $value = $value[$segment];
            }
        }
    }

    public function testProtectsSensitiveWriteOperationsWithCsrf(): void
    {
        $operations = [
            ['/auth/refresh', 'post'],
            ['/auth/logout', 'post'],
            ['/auth/register', 'post'],
            ['/usuarios/{id}', 'put'],
            ['/usuarios/{id}', 'delete'],
            ['/pedidos', 'post'],
            ['/pedidos/{id}', 'put'],
        ];

        foreach ($operations as [$path, $method]) {
            $security =
                $this->document['paths'][$path][$method]['security'];

            self::assertArrayHasKey('csrfCookie', $security[0]);
            self::assertArrayHasKey('csrfHeader', $security[0]);
        }
    }

    public function testDocumentsAccessCookieOnProtectedRoutes(): void
    {
        $operations = [
            ['/auth/me', 'get'],
            ['/usuarios', 'get'],
            ['/auth/register', 'post'],
            ['/usuarios/{id}', 'put'],
            ['/usuarios/{id}', 'delete'],
            ['/pedidos', 'get'],
            ['/pedidos', 'post'],
            ['/pedidos/{id}', 'get'],
            ['/pedidos/{id}', 'put'],
        ];

        foreach ($operations as [$path, $method]) {
            $security =
                $this->document['paths'][$path][$method]['security'];

            self::assertArrayHasKey('accessCookie', $security[0]);
        }
    }

    /**
     * @param mixed $value
     * @param list<string> $references
     */
    private function collectReferences(
        mixed $value,
        array &$references
    ): void {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            if ($key === '$ref' && is_string($item)) {
                $references[] = $item;
                continue;
            }

            $this->collectReferences($item, $references);
        }
    }
}
