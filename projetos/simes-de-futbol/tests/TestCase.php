<?php

declare(strict_types=1);

namespace Tests;

class AssertionFailed extends \Exception
{
}

abstract class TestCase
{
    protected int $assertions = 0;

    public function setUp(): void
    {
    }

    public function tearDown(): void
    {
    }

    public function assertionCount(): int
    {
        return $this->assertions;
    }

    protected function fail(string $message): never
    {
        throw new AssertionFailed($message);
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        $this->assertions++;
        if (!$condition) {
            $this->fail($message !== '' ? $message : 'Esperava que a condição fosse verdadeira.');
        }
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertTrue(!$condition, $message !== '' ? $message : 'Esperava que a condição fosse falsa.');
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            $this->fail($message !== '' ? $message : 'Valores diferentes. Esperado: ' . var_export($expected, true) . ' | Recebido: ' . var_export($actual, true));
        }
    }

    protected function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected != $actual) {
            $this->fail($message !== '' ? $message : 'Valores diferentes. Esperado: ' . var_export($expected, true) . ' | Recebido: ' . var_export($actual, true));
        }
    }

    protected function assertNull(mixed $value, string $message = ''): void
    {
        $this->assertSame(null, $value, $message !== '' ? $message : 'Esperava null.');
    }

    protected function assertNotNull(mixed $value, string $message = ''): void
    {
        $this->assertions++;
        if ($value === null) {
            $this->fail($message !== '' ? $message : 'Esperava valor não nulo.');
        }
    }

    protected function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            $this->fail($message !== '' ? $message : 'Texto não encontrado: "' . $needle . '".');
        }
    }

    protected function assertNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (str_contains($haystack, $needle)) {
            $this->fail($message !== '' ? $message : 'Texto não deveria estar presente: "' . $needle . '".');
        }
    }

    protected function assertCount(int $expected, array $items, string $message = ''): void
    {
        $this->assertSame($expected, count($items), $message !== '' ? $message : 'Quantidade de itens diferente do esperado.');
    }

    protected function assertGreaterThan(float|int $expected, float|int $actual, string $message = ''): void
    {
        $this->assertions++;
        if (!($actual > $expected)) {
            $this->fail($message !== '' ? $message : 'Esperava valor maior que ' . $expected . ', recebido ' . $actual . '.');
        }
    }

    protected function assertLessThan(float|int $expected, float|int $actual, string $message = ''): void
    {
        $this->assertions++;
        if (!($actual < $expected)) {
            $this->fail($message !== '' ? $message : 'Esperava valor menor que ' . $expected . ', recebido ' . $actual . '.');
        }
    }
}
