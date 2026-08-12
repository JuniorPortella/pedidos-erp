<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\OrderStatus;
use App\Exception\ValidationException;
use App\Service\OrderInputValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderInputValidatorTest extends TestCase
{
    private OrderInputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new OrderInputValidator();
    }

    public function testNormalizesValidInput(): void
    {
        $input = $this->validator->validate([
            'cliente_nome' => '  Cliente Teste  ',
            'descricao' => '  Pedido completo  ',
            'status' => '  em_processamento  ',
        ]);

        self::assertSame(
            'Cliente Teste',
            $input->customerName
        );
        self::assertSame(
            'Pedido completo',
            $input->description
        );
        self::assertSame(
            OrderStatus::Processing,
            $input->status
        );
    }

    #[DataProvider('validStatuses')]
    public function testAcceptsDefinedStatus(
        string $value,
        OrderStatus $expected
    ): void {
        $input = $this->validator->validate([
            'cliente_nome' => 'Cliente',
            'descricao' => 'Descricao',
            'status' => $value,
        ]);

        self::assertSame($expected, $input->status);
    }

    /**
     * @return array<string, array{string, OrderStatus}>
     */
    public static function validStatuses(): array
    {
        return [
            'pending' => [
                'PENDENTE',
                OrderStatus::Pending,
            ],
            'processing' => [
                'EM_PROCESSAMENTO',
                OrderStatus::Processing,
            ],
            'completed' => [
                'CONCLUIDO',
                OrderStatus::Completed,
            ],
        ];
    }

    public function testRejectsMissingRequiredFields(): void
    {
        try {
            $this->validator->validate([]);
            self::fail('Era esperada uma ValidationException.');
        } catch (ValidationException $exception) {
            self::assertSame(
                [
                    'cliente_nome',
                    'descricao',
                    'status',
                ],
                array_keys($exception->errors())
            );
        }
    }

    public function testRejectsInvalidStatus(): void
    {
        try {
            $this->validator->validate([
                'cliente_nome' => 'Cliente',
                'descricao' => 'Descricao',
                'status' => 'CANCELADO',
            ]);
            self::fail('Era esperada uma ValidationException.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey(
                'status',
                $exception->errors()
            );
        }
    }

    public function testRejectsFieldsOverMaximumLength(): void
    {
        try {
            $this->validator->validate([
                'cliente_nome' => str_repeat('a', 121),
                'descricao' => str_repeat('b', 5001),
                'status' => 'PENDENTE',
            ]);
            self::fail('Era esperada uma ValidationException.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey(
                'cliente_nome',
                $exception->errors()
            );
            self::assertArrayHasKey(
                'descricao',
                $exception->errors()
            );
        }
    }
}
