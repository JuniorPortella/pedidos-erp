<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use App\Controller\UserController;
use App\Entity\UserProfile;
use App\Service\UserService;
use PHPUnit\Framework\TestCase;
use Tests\Support\InMemoryUserRepository;

final class UserControllerTest extends TestCase
{
    public function testListsUsersWithoutSensitiveData(): void
    {
        $repository = new InMemoryUserRepository();

        $repository->create(
            'Usuario Admin',
            'admin@example.com',
            'admin',
            password_hash('SenhaSegura123', PASSWORD_DEFAULT),
            UserProfile::Admin
        );

        $response = (new UserController(
            new UserService($repository)
        ))->index();

        self::assertSame(200, $response->status());

        $body = json_decode(
            $response->body(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        self::assertSame('admin', $body['users'][0]['usuario']);
        self::assertSame('ADMIN', $body['users'][0]['perfil']);
        self::assertTrue($body['users'][0]['ativo']);
        self::assertArrayNotHasKey(
            'senha_hash',
            $body['users'][0]
        );
        self::assertStringNotContainsString(
            'SenhaSegura123',
            $response->body()
        );
    }
}
