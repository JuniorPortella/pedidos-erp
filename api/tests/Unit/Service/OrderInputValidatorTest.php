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
            'cliente_id' => '42',
            'descricao' => '  Pedido completo  ',
            'valor_total' => ' 149.9 ',
            'status' => '  em_processamento  ',
        ]);

        self::assertSame(
            42,
            $input->clientId
        );
        self::assertSame(
            'Pedido completo',
            $input->description
        );
        self::assertSame('149.90', $input->totalAmount);
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
            'cliente_id' => 1,
            'descricao' => 'Descricao',
            'valor_total' => '10.00',
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
                    'cliente_id',
                    'descricao',
                    'valor_total',
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
                'cliente_id' => 1,
                'descricao' => 'Descricao',
                'valor_total' => '10.00',
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
                'cliente_id' => 1,
                'descricao' => str_repeat('b', 5001),
                'valor_total' => '10.00',
                'status' => 'PENDENTE',
            ]);
            self::fail('Era esperada uma ValidationException.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey(
                'descricao',
                $exception->errors()
            );
        }
    }

    #[DataProvider('invalidClientIds')]
    public function testRejectsInvalidClientId(mixed $value): void
    {
        try {
            $this->validator->validate([
                'cliente_id' => $value,
                'descricao' => 'Descricao',
                'valor_total' => '10.00',
                'status' => 'PENDENTE',
            ]);
            self::fail('Era esperada uma ValidationException.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey(
                'cliente_id',
                $exception->errors()
            );
        }
    }

    public static function invalidClientIds(): array
    {
        return [
            'missing' => [null],
            'zero' => [0],
            'negative' => [-1],
            'decimal' => [1.5],
            'text' => ['cliente'],
            'boolean' => [true],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function testRejectsInvalidTotalAmount(mixed $value): void
    {
        try {
            $this->validator->validate([
                'cliente_id' => 1,
                'descricao' => 'Descricao',
                'valor_total' => $value,
                'status' => 'PENDENTE',
            ]);
            self::fail('Era esperada uma ValidationException.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey(
                'valor_total',
                $exception->errors()
            );
        }
    }

    public static function invalidAmounts(): array
    {
        return [
            'missing' => [null],
            'zero' => ['0'],
            'negative' => ['-1.00'],
            'three decimals' => ['10.999'],
            'comma' => ['10,50'],
            'over limit' => ['10000000000.00'],
            'float' => [10.5],
            'boolean' => [true],
        ];
    }
}
