<?php
/**
 * Passe Livre Suzano — Cooldown de envio de código OTP
 *
 * O tempo de espera é vinculado ao e-mail que recebeu o último código, usando a
 * estrutura já existente em `codigos_verificacao` (email + created_at). Assim,
 * trocar de e-mail permite novo envio imediato e o contador sobrevive ao
 * recarregamento da página, porque a fonte da verdade é o banco, não a página.
 */

if (!defined('PL_OTP_COOLDOWN_SECONDS')) {
    define('PL_OTP_COOLDOWN_SECONDS', 60);
}

if (!function_exists('pl_otp_cooldown_remaining')) {
    /**
     * Segundos que ainda faltam para solicitar outro código para o MESMO e-mail.
     * Retorna 0 quando o e-mail nunca recebeu código daquele tipo neste intervalo.
     */
    function pl_otp_cooldown_remaining(PDO $db, string $email, string $tipo): int
    {
        $email = strtolower(trim($email));
        if ($email === '' || $tipo === '') {
            return 0;
        }

        $stmt = $db->prepare("
            SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) AS idade
            FROM codigos_verificacao
            WHERE email = ? AND tipo = ?
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([$email, $tipo]);
        $row = $stmt->fetch();

        if (!$row || $row['idade'] === null) {
            return 0;
        }

        $restante = PL_OTP_COOLDOWN_SECONDS - (int)$row['idade'];
        return $restante > 0 ? $restante : 0;
    }
}
