<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Product;
use App\Models\StockMovement;
use RuntimeException;

final class StockService
{
    /**
     * Define o estoque para um novo valor, registrando a movimentação.
     *
     * @return array{ok:bool,message:string,previous?:int,new?:int}
     */
    public static function setStock(int $productId, int $newQuantity, ?int $userId, string $reason): array
    {
        if ($newQuantity < 0) {
            return ['ok' => false, 'message' => 'O estoque não pode ser negativo.'];
        }

        try {
            return Database::transaction(static function () use ($productId, $newQuantity, $userId, $reason): array {
                $product = Product::fetchOne('SELECT * FROM products WHERE id = ? FOR UPDATE', [$productId]);
                if ($product === null) {
                    return ['ok' => false, 'message' => 'Produto não encontrado.'];
                }

                $previous = (int) $product['stock_quantity'];
                if ($previous === $newQuantity) {
                    return [
                        'ok' => true,
                        'message' => 'O estoque já estava em ' . $newQuantity . ' unidade(s).',
                        'previous' => $previous,
                        'new' => $newQuantity,
                    ];
                }

                Product::updateById($productId, ['stock_quantity' => $newQuantity]);

                StockMovement::record(
                    $productId,
                    'ajuste',
                    abs($newQuantity - $previous),
                    $previous,
                    $newQuantity,
                    null,
                    $userId,
                    $reason
                );

                return [
                    'ok' => true,
                    'message' => 'Estoque ajustado de ' . $previous . ' para ' . $newQuantity . '.',
                    'previous' => $previous,
                    'new' => $newQuantity,
                ];
            });
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Repõe estoque (entrada manual).
     *
     * @return array{ok:bool,message:string}
     */
    public static function increase(int $productId, int $quantity, ?int $userId, string $reason): array
    {
        if ($quantity <= 0) {
            return ['ok' => false, 'message' => 'Informe uma quantidade maior que zero.'];
        }

        try {
            return Database::transaction(static function () use ($productId, $quantity, $userId, $reason): array {
                $product = Product::fetchOne('SELECT * FROM products WHERE id = ? FOR UPDATE', [$productId]);
                if ($product === null) {
                    return ['ok' => false, 'message' => 'Produto não encontrado.'];
                }

                $previous = (int) $product['stock_quantity'];
                $new = $previous + $quantity;
                Product::updateById($productId, ['stock_quantity' => $new]);
                StockMovement::record($productId, 'entrada', $quantity, $previous, $new, null, $userId, $reason);

                return ['ok' => true, 'message' => 'Estoque reposto: +' . $quantity . ' unidade(s).'];
            });
        } catch (RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
