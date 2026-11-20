<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Validator;
use App\Models\AdminLog;
use App\Models\Coupon;
use App\Models\CouponUsage;

final class AdminCouponController extends Controller
{
    public function index(array $params = []): void
    {
        $page = max(1, Request::integer('page', 1));

        $this->renderAdmin('admin/coupons/index', [
            'title' => 'Cupons',
            'coupons' => Coupon::paginateAll($page, 15),
        ]);
    }

    public function store(array $params = []): void
    {
        [$data, $errors] = $this->validateCoupon(null);

        if ($errors !== []) {
            $this->error(reset($errors) ?: 'Verifique os dados do cupom.');
            $this->redirect('/admin/cupons');
        }

        $couponId = Coupon::insert($data + ['used_count' => 0, 'created_at' => date('Y-m-d H:i:s')]);
        AdminLog::record('coupon.created', 'coupon', $couponId, ['code' => $data['code']]);

        $this->success('Cupom ' . $data['code'] . ' criado.');
        $this->redirect('/admin/cupons');
    }

    public function update(array $params = []): void
    {
        $coupon = Coupon::find((int) ($params['id'] ?? 0));
        if ($coupon === null) {
            abort(404, 'Cupom não encontrado.');
        }

        [$data, $errors] = $this->validateCoupon((int) $coupon['id']);
        if ($errors !== []) {
            $this->error(reset($errors) ?: 'Verifique os dados do cupom.');
            $this->redirect('/admin/cupons');
        }

        Coupon::updateById((int) $coupon['id'], $data);
        AdminLog::record('coupon.updated', 'coupon', (int) $coupon['id'], ['code' => $data['code']]);

        $this->success('Cupom atualizado.');
        $this->redirect('/admin/cupons');
    }

    public function toggle(array $params = []): void
    {
        $coupon = Coupon::find((int) ($params['id'] ?? 0));
        if ($coupon === null) {
            abort(404, 'Cupom não encontrado.');
        }

        $newState = (int) $coupon['is_active'] === 1 ? 0 : 1;
        Coupon::updateById((int) $coupon['id'], ['is_active' => $newState]);
        AdminLog::record('coupon.toggled', 'coupon', (int) $coupon['id'], ['active' => $newState]);

        $this->success($newState === 1 ? 'Cupom ativado.' : 'Cupom desativado.');
        $this->redirect('/admin/cupons');
    }

    public function destroy(array $params = []): void
    {
        $coupon = Coupon::find((int) ($params['id'] ?? 0));
        if ($coupon === null) {
            abort(404, 'Cupom não encontrado.');
        }

        if (CouponUsage::countForCoupon((int) $coupon['id']) > 0) {
            Coupon::updateById((int) $coupon['id'], ['is_active' => 0]);
            AdminLog::record('coupon.deactivated', 'coupon', (int) $coupon['id']);
            $this->success('Este cupom já foi utilizado; ele foi desativado para preservar o histórico.');
            $this->redirect('/admin/cupons');
        }

        Coupon::deleteById((int) $coupon['id']);
        AdminLog::record('coupon.deleted', 'coupon', (int) $coupon['id'], ['code' => $coupon['code']]);

        $this->success('Cupom excluído.');
        $this->redirect('/admin/cupons');
    }

    /** @return array{0:array<string,mixed>,1:array<string,string>} */
    private function validateCoupon(?int $couponId): array
    {
        $input = [
            'code' => mb_strtoupper(trim((string) Request::input('code', ''))),
            'description' => trim((string) Request::input('description', '')),
            'type' => (string) Request::input('type', 'percent'),
            'value' => (string) Request::input('value', ''),
            'min_order_amount' => (string) Request::input('min_order_amount', '0'),
            'max_uses' => (string) Request::input('max_uses', ''),
            'max_uses_per_user' => (string) Request::input('max_uses_per_user', '1'),
            'starts_at' => (string) Request::input('starts_at', ''),
            'expires_at' => (string) Request::input('expires_at', ''),
            'is_active' => Request::boolean('is_active') ? '1' : '0',
        ];

        $validator = Validator::make($input, [
            'code' => 'required|min:3|max:60|unique:coupons,code' . ($couponId !== null ? ',' . $couponId : ''),
            'description' => 'max:180',
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min_value:0.01|max_value:999999.99',
            'min_order_amount' => 'numeric|min_value:0',
            'max_uses' => 'integer|min_value:1|max_value:1000000',
            'max_uses_per_user' => 'integer|min_value:1|max_value:1000',
            'starts_at' => 'date',
            'expires_at' => 'date',
        ]);

        $errors = $validator->errors();

        if ($input['type'] === 'percent' && is_numeric($input['value']) && (float) $input['value'] > 100) {
            $errors['value'] = 'Para desconto percentual, o valor deve ser no máximo 100.';
        }

        $startsAt = $input['starts_at'] !== '' ? $input['starts_at'] . ' 00:00:00' : null;
        $expiresAt = $input['expires_at'] !== '' ? $input['expires_at'] . ' 23:59:59' : null;

        if ($startsAt !== null && $expiresAt !== null && strtotime($expiresAt) <= strtotime($startsAt)) {
            $errors['expires_at'] = 'A validade final deve ser depois do início.';
        }

        $data = [
            'code' => $input['code'],
            'description' => $input['description'] !== '' ? $input['description'] : null,
            'type' => $input['type'],
            'value' => round((float) $input['value'], 2),
            'min_order_amount' => round((float) ($input['min_order_amount'] !== '' ? $input['min_order_amount'] : 0), 2),
            'max_uses' => $input['max_uses'] !== '' ? (int) $input['max_uses'] : null,
            'max_uses_per_user' => (int) ($input['max_uses_per_user'] !== '' ? $input['max_uses_per_user'] : 1),
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'is_active' => (int) $input['is_active'],
        ];

        return [$data, $errors];
    }
}
