<?php

declare(strict_types=1);

namespace App\Controllers\Store;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Address;
use App\Models\Favorite;
use App\Models\Order;
use App\Services\AuthService;

final class AccountController extends Controller
{
    public function index(array $params = []): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $orders = Order::paginateForUser((int) $user['id'], 1, 5);

        $this->render('account/dashboard', [
            'title' => 'Minha conta',
            'user' => $user,
            'orders' => $orders,
            'addressCount' => count(Address::forUser((int) $user['id'])),
            'favoriteCount' => Favorite::countForUser((int) $user['id']),
        ]);
    }

    public function updateProfile(array $params = []): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $data = [
            'name' => trim((string) Request::input('name', '')),
            'email' => trim((string) Request::input('email', '')),
            'phone' => trim((string) Request::input('phone', '')),
        ];

        $validator = Validator::make($data, [
            'name' => 'required|min:3|max:120',
            'email' => 'required|email|max:180|unique:users,email,' . (int) $user['id'],
            'phone' => 'max:30',
        ]);

        if ($validator->fails()) {
            $this->error($validator->firstError() ?? 'Verifique os dados informados.');
            $this->redirect('/minha-conta');
        }

        \App\Models\User::updateProfile((int) $user['id'], $data['name'], $data['email'], $data['phone']);
        Auth::refresh();

        $this->success('Dados atualizados com sucesso.');
        $this->redirect('/minha-conta');
    }

    public function updatePassword(array $params = []): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->redirect('/login');
        }

        $current = (string) Request::input('current_password', '');
        $new = (string) Request::input('new_password', '');
        $confirmation = (string) Request::input('new_password_confirmation', '');

        $validator = Validator::make(
            ['current_password' => $current, 'new_password' => $new, 'new_password_confirmation' => $confirmation],
            [
                'current_password' => 'required',
                'new_password' => 'required|min:8|max:72|confirmed',
            ]
        );

        if ($validator->fails()) {
            $this->error($validator->firstError() ?? 'Verifique os dados informados.');
            $this->redirect('/minha-conta');
        }

        if (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            $this->error('Use ao menos uma letra e um número na nova senha.');
            $this->redirect('/minha-conta');
        }

        $result = AuthService::updatePassword((int) $user['id'], $current, $new);
        if (!$result['ok']) {
            $this->error($result['message']);
            $this->redirect('/minha-conta');
        }

        $this->success($result['message']);
        $this->redirect('/minha-conta');
    }

    public function addresses(array $params = []): void
    {
        $userId = (int) Auth::id();

        $this->render('account/addresses', [
            'title' => 'Meus endereços',
            'addresses' => Address::forUser($userId),
        ]);
    }

    public function createAddress(array $params = []): void
    {
        $this->renderAddressForm(null);
    }

    public function editAddress(array $params = []): void
    {
        $address = Address::findForUser((int) ($params['id'] ?? 0), (int) Auth::id());
        if ($address === null) {
            abort(404, 'Endereço não encontrado.');
        }

        $this->renderAddressForm($address);
    }

    public function storeAddress(array $params = []): void
    {
        $data = $this->addressInput();
        $validator = Validator::make($data, $this->addressRules());

        if ($validator->fails()) {
            $this->renderAddressForm(null, $data, $validator->errors());
            return;
        }

        Address::createForUser((int) Auth::id(), $data);
        $this->success('Endereço cadastrado com sucesso.');
        $this->redirect('/minha-conta/enderecos');
    }

    public function updateAddress(array $params = []): void
    {
        $addressId = (int) ($params['id'] ?? 0);
        $address = Address::findForUser($addressId, (int) Auth::id());
        if ($address === null) {
            abort(404, 'Endereço não encontrado.');
        }

        $data = $this->addressInput();
        $validator = Validator::make($data, $this->addressRules());

        if ($validator->fails()) {
            $this->renderAddressForm($address, $data, $validator->errors());
            return;
        }

        Address::updateForUser($addressId, (int) Auth::id(), $data);
        $this->success('Endereço atualizado com sucesso.');
        $this->redirect('/minha-conta/enderecos');
    }

    public function deleteAddress(array $params = []): void
    {
        $addressId = (int) ($params['id'] ?? 0);
        if (Address::softDeleteForUser($addressId, (int) Auth::id())) {
            $this->success('Endereço removido.');
        } else {
            $this->error('Endereço não encontrado.');
        }

        $this->redirect('/minha-conta/enderecos');
    }

    public function setDefaultAddress(array $params = []): void
    {
        $addressId = (int) ($params['id'] ?? 0);
        if (Address::setDefaultForUser($addressId, (int) Auth::id())) {
            $this->success('Endereço padrão atualizado.');
        } else {
            $this->error('Endereço não encontrado.');
        }

        $this->redirect('/minha-conta/enderecos');
    }

    public function orders(array $params = []): void
    {
        $page = max(1, Request::integer('page', 1));

        $this->render('account/orders', [
            'title' => 'Meus pedidos',
            'orders' => Order::paginateForUser((int) Auth::id(), $page, 10),
        ]);
    }

    public function orderShow(array $params = []): void
    {
        $code = (string) ($params['code'] ?? '');
        $order = Order::findByCodeForUser($code, (int) Auth::id());

        if ($order === null) {
            abort(404, 'Pedido não encontrado.');
        }

        $this->render('account/order-show', [
            'title' => 'Pedido ' . $order['code'],
            'order' => $order,
            'items' => Order::items((int) $order['id']),
            'history' => Order::history((int) $order['id']),
            'payment' => Order::payment((int) $order['id']),
        ]);
    }

    /** @param array<string,mixed>|null $address */
    private function renderAddressForm(?array $address, ?array $old = null, array $errors = []): void
    {
        $data = $old ?? $address ?? [
            'label' => 'Casa',
            'recipient_name' => Auth::user()['name'] ?? '',
            'phone' => Auth::user()['phone'] ?? '',
            'cep' => '',
            'street' => '',
            'number' => '',
            'complement' => '',
            'district' => '',
            'city' => '',
            'state' => '',
            'is_default' => 0,
        ];

        $this->render('account/address-form', [
            'title' => $address === null ? 'Novo endereço' : 'Editar endereço',
            'address' => $data,
            'addressId' => $address !== null ? (int) $address['id'] : null,
            'errors' => $errors,
        ]);
    }

    /** @return array<string,mixed> */
    private function addressInput(): array
    {
        $cep = preg_replace('/\D/', '', (string) Request::input('cep', '')) ?? '';
        if (strlen($cep) === 8) {
            $cep = substr($cep, 0, 5) . '-' . substr($cep, 5);
        }

        return [
            'label' => trim((string) Request::input('label', 'Casa')),
            'recipient_name' => trim((string) Request::input('recipient_name', '')),
            'phone' => trim((string) Request::input('phone', '')),
            'cep' => $cep,
            'street' => trim((string) Request::input('street', '')),
            'number' => trim((string) Request::input('number', '')),
            'complement' => trim((string) Request::input('complement', '')),
            'district' => trim((string) Request::input('district', '')),
            'city' => trim((string) Request::input('city', '')),
            'state' => mb_strtoupper(trim((string) Request::input('state', ''))),
            'is_default' => Request::boolean('is_default') ? 1 : 0,
        ];
    }

    /** @return array<string,string> */
    private function addressRules(): array
    {
        return [
            'label' => 'max:60',
            'recipient_name' => 'required|max:120',
            'phone' => 'required|max:30',
            'cep' => 'required|regex:/^\d{5}-?\d{3}$/',
            'street' => 'required|max:180',
            'number' => 'required|max:20',
            'complement' => 'max:120',
            'district' => 'required|max:120',
            'city' => 'required|max:120',
            'state' => 'required|regex:/^[A-Za-z]{2}$/',
        ];
    }
}
