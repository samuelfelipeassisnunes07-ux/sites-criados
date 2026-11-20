<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\AuthService;
use Tests\IntegrationTestCase;

final class AuthTest extends IntegrationTestCase
{
    public function testPublicRegistrationCreatesCustomerWithHashedPassword(): void
    {
        $result = AuthService::register('Maria Teste', 'maria@teste.local', 'Senha@12345', '127.0.0.1');
        $this->assertTrue($result['ok'], $result['message']);

        $user = User::findByEmail('maria@teste.local');
        $this->assertNotNull($user);
        $this->assertSame('customer', $user['role']);
        $this->assertSame(1, (int) $user['is_active']);
        $this->assertNotContains('Senha@12345', (string) $user['password_hash']);
        $this->assertTrue(password_verify('Senha@12345', (string) $user['password_hash']));
        $this->assertTrue(Auth::check());
        $this->assertFalse(Auth::isAdmin());
    }

    public function testRegistrationRejectsDuplicateEmail(): void
    {
        $this->createUser('dup@teste.local');
        $result = AuthService::register('Outro', 'dup@teste.local', 'Senha@12345', '127.0.0.1');

        $this->assertFalse($result['ok']);
        $this->assertCount(1, \App\Core\Database::pdo()->query("SELECT id FROM users WHERE email = 'dup@teste.local'")->fetchAll());
    }

    public function testLoginSucceedsAndUpdatesLastLogin(): void
    {
        $this->createUser('login@teste.local', 'Senha@12345');

        $result = AuthService::attempt('login@teste.local', 'Senha@12345', '127.0.0.1');
        $this->assertTrue($result['ok'], $result['message']);
        $this->assertTrue(Auth::check());

        $user = User::findByEmail('login@teste.local');
        $this->assertNotNull($user['last_login_at']);
    }

    public function testWrongPasswordFailsAndRecordsAttempt(): void
    {
        $this->createUser('senha@teste.local', 'Senha@12345');

        $result = AuthService::attempt('senha@teste.local', 'Errada@123', '127.0.0.1');
        $this->assertFalse($result['ok']);
        $this->assertFalse(Auth::check());
        $this->assertSame(1, LoginAttempt::countFailuresForEmail('senha@teste.local'));
    }

    public function testLoginBlockedAfterFiveFailures(): void
    {
        $this->createUser('bloqueio@teste.local', 'Senha@12345');

        for ($i = 0; $i < 5; $i++) {
            AuthService::attempt('bloqueio@teste.local', 'SenhaErrada@1', '127.0.0.1');
        }

        $result = AuthService::attempt('bloqueio@teste.local', 'Senha@12345', '127.0.0.1');
        $this->assertFalse($result['ok']);
        $this->assertContains('Muitas tentativas', $result['message']);
    }

    public function testInactiveUserCannotLogin(): void
    {
        $user = $this->createUser('inativo@teste.local', 'Senha@12345');
        User::updateById((int) $user['id'], ['is_active' => 0]);

        $result = AuthService::attempt('inativo@teste.local', 'Senha@12345', '127.0.0.1');
        $this->assertFalse($result['ok']);
        $this->assertFalse(Auth::check());
    }

    public function testAdminRoleIsDetected(): void
    {
        $admin = $this->createUser('admin@teste.local', 'Senha@12345', 'admin');
        $this->assertSame('admin', $admin['role']);

        Auth::login($admin);
        $this->assertTrue(Auth::isAdmin());
        $this->assertSame((int) $admin['id'], Auth::id());
    }

    public function testPasswordUpdateRequiresCurrentPassword(): void
    {
        $user = $this->createUser('troca@teste.local', 'Senha@12345');

        $wrong = AuthService::updatePassword((int) $user['id'], 'Errada@1', 'NovaSenha@123');
        $this->assertFalse($wrong['ok']);

        $ok = AuthService::updatePassword((int) $user['id'], 'Senha@12345', 'NovaSenha@123');
        $this->assertTrue($ok['ok']);

        $updated = User::find((int) $user['id']);
        $this->assertTrue(password_verify('NovaSenha@123', (string) $updated['password_hash']));
    }
}
