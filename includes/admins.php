<?php
/**
 * Passe Livre Suzano — Helpers de nível de acesso administrativo
 *
 * Modelo de permissões (fonte de verdade: tabela `usuarios` no MySQL):
 *   - usuário comum ................ is_admin = 0
 *   - administrador ................ is_admin = 1, is_primary_admin = 0
 *   - administrador principal ...... is_admin = 1, is_primary_admin = 1
 *
 * Nenhum nível é deduzido de nome de usuário, texto de `role`, e-mail,
 * parâmetro de URL ou variável JavaScript.
 */

if (!defined('PL_ADMINS_LOADED')) {
    define('PL_ADMINS_LOADED', true);
}

require_once __DIR__ . '/../api/_db_restore.php';

/**
 * Confere no banco (consulta preparada, sempre atualizada) se a conta é o
 * administrador principal ativo. Usado por página e API a cada requisição,
 * para não confiar em valor de sessão que possa estar desatualizado.
 */
function pl_admin_is_primary(PDO $db, int $userId): bool {
    if ($userId <= 0) {
        return false;
    }
    $stmt = $db->prepare("
        SELECT 1 FROM usuarios
        WHERE id = ? AND is_admin = 1 AND is_primary_admin = 1 AND status = 'ativo'
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Nível de acesso a partir da linha do banco.
 * @return string principal|admin|cidadao
 */
function pl_admin_level(array $row): string {
    if ((int)($row['is_admin'] ?? 0) !== 1) {
        return 'cidadao';
    }
    return (int)($row['is_primary_admin'] ?? 0) === 1 ? 'principal' : 'admin';
}

/**
 * Rótulo legível do nível de acesso.
 */
function pl_admin_level_label(string $level): string {
    return match ($level) {
        'principal' => 'Administrador principal',
        'admin'     => 'Administrador',
        default     => 'Usuário comum',
    };
}
