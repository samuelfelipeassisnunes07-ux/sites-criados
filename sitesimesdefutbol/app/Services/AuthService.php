<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Models\LoginAttempt;
use App\Models\PasswordResetToken;
use App\Models\User;
use PDOException;
use Throwable;

final class AuthService
{
    private const MAX_FAILURES_PER_EMAIL = 5;
    private const MAX_FAILURES_PER_IP = 20;

    /** @return array{ok:bool,message:string,user?:array<string,mixed>} */
    public static function attempt(string $email, string $password, string $ip): array
    {
        $email = mb_strtolower(trim($email));

        if (
            LoginAttempt::countFailuresForEmail($email) >= self::MAX_FAILURES_PER_EMAIL
            || LoginAttempt::countFailuresForIp($ip) >= self::MAX_FAILURES_PER_IP
        ) {
            Logger::security('Login bloqueado por excesso de tentativas', ['email' => $email, 'ip' => $ip]);

            return [
                'ok' => false,
                'message' => 'Muitas tentativas de login. Aguarde 15 minutos e tente novamente.',
            ];
        }

        $user = User::findByEmail($email);

        if ($user === null) {
            // Mantém custo de verificação sem revelar que o e-mail não existe.
            password_verify($password, password_hash('senha-inexistente', PASSWORD_DEFAULT));
            LoginAttempt::record($email, $ip, false);

            return ['ok' => false, 'message' => 'E-mail ou senha incorretos.'];
        }

        if ((int) $user['is_active'] !== 1 || $user['deleted_at'] !== null) {
            LoginAttempt::record($email, $ip, false);
            Logger::security('Tentativa de login em conta bloqueada', ['user_id' => $user['id'], 'ip' => $ip]);

            return ['ok' => false, 'message' => 'E-mail ou senha incorretos.'];
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            LoginAttempt::record($email, $ip, false);

            return ['ok' => false, 'message' => 'E-mail ou senha incorretos.'];
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            User::setPassword((int) $user['id'], $password);
        }

        LoginAttempt::record($email, $ip, true);
        LoginAttempt::clearFailures($email, $ip);
        User::recordLogin((int) $user['id']);
        Auth::login($user);

        return ['ok' => true, 'message' => 'Login realizado com sucesso.', 'user' => $user];
    }

    /** @return array{ok:bool,message:string,user?:array<string,mixed>} */
    public static function register(string $name, string $email, string $password, string $ip): array
    {
        $email = mb_strtolower(trim($email));

        if (User::findByEmail($email) !== null) {
            return ['ok' => false, 'message' => 'Este e-mail já está cadastrado. Faça login ou recupere sua senha.'];
        }

        try {
            // Cadastro público SEMPRE cria cliente; o papel nunca vem do formulário.
            $userId = User::create($name, $email, $password, 'customer');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['ok' => false, 'message' => 'Este e-mail já está cadastrado.'];
            }
            Logger::error('Falha ao cadastrar usuário', ['message' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Não foi possível concluir o cadastro. Tente novamente.'];
        }

        $user = User::find($userId);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Não foi possível concluir o cadastro.'];
        }

        LoginAttempt::record($email, $ip, true);
        Auth::login($user);

        return ['ok' => true, 'message' => 'Conta criada com sucesso. Bem-vindo(a)!', 'user' => $user];
    }

    /**
     * Sempre retorna a mesma mensagem, tenha ou não o e-mail cadastro.
     *
     * @return array{ok:bool,message:string}
     */
    public static function requestPasswordReset(string $email, string $ip): array
    {
        $email = mb_strtolower(trim($email));

        if (
            LoginAttempt::countFailuresForEmail('reset:' . $email, 15) >= 3
            || LoginAttempt::countFailuresForIp($ip, 15) >= 10
        ) {
            return ['ok' => false, 'message' => 'Muitas solicitações. Aguarde alguns minutos para tentar novamente.'];
        }

        LoginAttempt::record('reset:' . $email, $ip, false);

        $user = User::findByEmail($email);

        if ($user !== null && (int) $user['is_active'] === 1 && $user['deleted_at'] === null) {
            $token = PasswordResetToken::createForUser((int) $user['id']);
            $resetUrl = absolute_url('/redefinir-senha?' . http_build_query([
                'selector' => $token['selector'],
                'token' => $token['token'],
            ]));

            try {
                MailService::sendPasswordReset($user, $resetUrl);
            } catch (Throwable $e) {
                Logger::error('Falha ao enviar e-mail de recuperação', ['message' => $e->getMessage()]);
            }

            Logger::info('Token de recuperação de senha gerado', ['user_id' => $user['id']]);
        }

        return [
            'ok' => true,
            'message' => 'Se este e-mail estiver cadastrado, enviaremos as instruções para redefinir a senha.',
        ];
    }

    /** @return array{ok:bool,message:string} */
    public static function resetPassword(string $selector, string $token, string $newPassword): array
    {
        $row = PasswordResetToken::findValid($selector, $token);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Link inválido, expirado ou já utilizado. Solicite um novo link.'];
        }

        try {
            Database::transaction(static function () use ($row, $newPassword): void {
                User::setPassword((int) $row['user_id'], $newPassword);
                PasswordResetToken::markUsed((int) $row['id']);
                PasswordResetToken::invalidateAllForUser((int) $row['user_id']);
            });
        } catch (Throwable $e) {
            Logger::error('Falha ao redefinir senha', ['message' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Não foi possível redefinir a senha. Tente novamente.'];
        }

        Logger::security('Senha redefinida via token', ['user_id' => $row['user_id']]);

        return ['ok' => true, 'message' => 'Senha redefinida com sucesso. Faça login com a nova senha.'];
    }

    /** @return array{ok:bool,message:string} */
    public static function updatePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $user = User::find($userId);
        if ($user === null) {
            return ['ok' => false, 'message' => 'Usuário não encontrado.'];
        }

        if (!password_verify($currentPassword, (string) $user['password_hash'])) {
            return ['ok' => false, 'message' => 'A senha atual está incorreta.'];
        }

        if (password_verify($newPassword, (string) $user['password_hash'])) {
            return ['ok' => false, 'message' => 'A nova senha deve ser diferente da senha atual.'];
        }

        User::setPassword($userId, $newPassword);
        Logger::security('Senha alterada pelo próprio usuário', ['user_id' => $userId]);

        return ['ok' => true, 'message' => 'Senha alterada com sucesso.'];
    }
}
