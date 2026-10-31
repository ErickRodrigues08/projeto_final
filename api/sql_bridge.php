<?php
/**
 * Passe Livre Suzano — Ponte SQL Universal (MySQL 8.0+ PDO Bridge)
 * Permite que o frontend (db.js / db-queries.js / PLDB) execute operações
 * no MySQL de forma transparente, com prepared statements e proteção contra SQL Injection.
 *
 * SEGURANÇA:
 *  - Exige autenticação via sessão válida (cookie ou header X-Session-Token).
 *  - Bloqueia instruções DDL destrutivas (DROP, TRUNCATE, ALTER, CREATE DATABASE).
 *  - Bloqueia acesso à tabela de sessões e hashes de senha diretamente via bridge.
 *  - Limita queries de escrita às tabelas permitidas da aplicação.
 */

require_once __DIR__ . '/_db_restore.php';

header('Content-Type: application/json; charset=utf-8');

// =========================================================================
// VERIFICAÇÃO DE AUTENTICAÇÃO OBRIGATÓRIA
// =========================================================================
$authUser = get_auth_user();
if (!$authUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Acesso não autorizado. Sessão inválida ou expirada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$isAdmin = (int)($authUser['is_admin'] ?? 0) === 1;

$data   = get_request_data();
$sql    = trim($data['sql'] ?? '');
$params = is_array($data['params'] ?? null) ? $data['params'] : [];
$action = $_GET['action'] ?? ($data['action'] ?? 'query');

if (empty($sql)) {
    json_error('SQL não fornecido.');
}

// =========================================================================
// BLOQUEIO ESTRITO DE INSTRUÇÕES PERIGOSAS E CONSULTAS ARBITRÁRIAS
// =========================================================================
$sqlClean = trim(preg_replace('/\s+/', ' ', $sql));
$sqlUpper = strtoupper($sqlClean);

// Texto usado SOMENTE nas analises de seguranca abaixo. Comentarios SQL (bloco,
// "--" e "#") sao removidos dele porque esconderiam clausulas dos filtros de
// permissao sem mudar o que o MySQL executa. A execucao usa $sqlExec.
$sqlAnalyze = trim(preg_replace(
    '/\s+/',
    ' ',
    preg_replace(['/\/\*[\s\S]*?\*\//', '/--[ \t][^\n]*/', '/#[^\n]*/'], ' ', $sqlClean)
));

// Bloqueia DDL destrutivo (DROP, TRUNCATE, ALTER, CREATE, RENAME)
$blockedPatterns = [
    '/^\s*(DROP\s+DATABASE|DROP\s+TABLE|TRUNCATE|ALTER\s+TABLE|CREATE\s+DATABASE|RENAME\s+TABLE)/i',
    '/^\s*(DELETE\s+FROM\s+(sessoes|usuarios)\s*;?\s*$)/i',  // DELETE irrestrito em tabelas críticas
    '/password_hash|password_verify/i',                       // Não expor hashes
    '/;\s*\S+/i',                                            // Impede múltiplas instruções encadeadas
    '/\b(UNION\s+ALL|UNION\s+SELECT|BENCHMARK|SLEEP|LOAD_FILE|INTO\s+OUTFILE|INTO\s+DUMPFILE)\b/i',
    '/\b(information_schema|mysql\.|performance_schema|sys\.)\b/i',
];
foreach ($blockedPatterns as $pattern) {
    if (preg_match($pattern, $sqlAnalyze)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Operação SQL bloqueada por política de segurança.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Bloqueia acesso a tabelas sensíveis de segurança via bridge
if (preg_match('/\b(sessoes|codigos_verificacao|usuario_senhas|usuario_tokens|usuarios_avatares)\b/i', $sqlAnalyze)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acesso bloqueado a tabelas de segurança via bridge.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Permite apenas operações nas tabelas oficiais do domínio Passe Livre
$allowedTables = '/\b(usuarios|users|locais|solicitacoes|avaliacoes|rotas|notificacoes|logs_auditoria|auditoria|configuracoes|usuario_necessidades|usuario_consentimentos|local_recursos|avaliacao_criterios|rota_passos|rota_opcoes|ocorrencias)\b/i';
if (!preg_match($allowedTables, $sqlAnalyze)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Consultas a tabelas não autorizadas não são permitidas. Utilize os endpoints específicos da API.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Adaptação transparente de funções SQLite/PostgreSQL para MySQL 8.0+
$sqlExec = preg_replace("/datetime\('now'\)/i", "NOW()", $sqlClean);
$sqlExec = preg_replace("/RANDOM\(\)/i", "RAND()", $sqlExec);
$sqlExec = preg_replace("/strftime\s*\(\s*(['\"][^'\"]+['\"])\s*,\s*([^)]+)\)/i", "DATE_FORMAT($2, $1)", $sqlExec);
$sqlExec = preg_replace("/INSERT\s+OR\s+IGNORE\s+INTO/i", "INSERT IGNORE INTO", $sqlExec);
$sqlExec = preg_replace("/INSERT\s+OR\s+REPLACE\s+INTO/i", "REPLACE INTO", $sqlExec);

// Substitui referência à view `auditoria` pelo nome real da tabela nas operações de INSERT
$sqlExec = preg_replace('/\bINSERT\s+(INTO\s+)?`?auditoria`?\b/i', 'INSERT INTO `logs_auditoria`', $sqlExec);

// =========================================================================
// AUTORIZAÇÃO DE ESCRITA: cidadão só altera o próprio perfil e conteúdo
// =========================================================================
$denyWrite = static function (string $message) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
};

$stmtType = '';
if (preg_match('/^\s*(INSERT|REPLACE|UPDATE|DELETE)\b/i', $sqlAnalyze, $mType)) {
    $stmtType = strtoupper($mType[1]);
}

// Escrita com comentário SQL é recusada: o comentário muda o que o MySQL executa
// em relação ao texto que as travas de permissão analisam.
if ($stmtType !== '' && $sqlAnalyze !== $sqlClean) {
    $denyWrite('Comentários SQL não são permitidos em operações de escrita neste canal.');
}

// Decomposição do UPDATE feita sobre o texto já sem comentários e tolerante a
// modificadores (`UPDATE IGNORE usuarios SET ...`), para que o alvo real da
// gravação não dependa de a cláusula estar exatamente na forma canônica.
$updTable = '';
$updSet   = '';
$updWhere = '';
if ($stmtType === 'UPDATE' && preg_match('/^\s*UPDATE\s+(.*?)\s+SET\s+(.*?)(?:\s+WHERE\s+(.*))?$/is', $sqlAnalyze, $mUpd)) {
    $updTable = preg_match('/`?(\w+)`?\s*$/i', trim($mUpd[1]), $mT) ? strtolower($mT[1]) : '';
    $updSet   = (string)$mUpd[2];
    $updWhere = trim((string)($mUpd[3] ?? ''));
}

$privColumn = '/`?\b(password_hash|is_primary_admin|is_admin|role|user_code|status|email_verified|failed_login_attempts|locked_until|cpf)\b`?\s*=/i';

if ($stmtType !== '' && preg_match('/\b(usuarios|users)\b/i', $sqlAnalyze)
    && preg_match('/\bavatar\b/i', $sqlAnalyze)) {
    $denyWrite('Fotos de perfil só podem ser alteradas pelo envio ou remoção de foto.');
}

/**
 * Trava válida para TODOS os papéis, inclusive administradores: nenhuma
 * permissão ou credencial de conta é alterada por este canal SQL genérico.
 * Concessão/remoção de acesso de administrador acontece apenas em
 * api/admins.php, com administrador principal, CSRF, confirmação e auditoria.
 */
if ($stmtType === 'UPDATE'
    && in_array($updTable, ['usuarios', 'users'], true)
    && preg_match($privColumn, $updSet)) {
    $denyWrite('Permissões e credenciais de conta não podem ser alteradas por este canal. Use Gerenciar Administradores.');
}

// Mesmo princípio na criação de contas por este canal: ninguém nasce
// administrador (nem administrador principal) pela ponte SQL.
if (($stmtType === 'INSERT' || $stmtType === 'REPLACE')
    && preg_match('/^\s*(?:INSERT|REPLACE)\s+(?:INTO\s+)?`?(usuarios|users)`?\s*\(([^)]*)\)/i', $sqlAnalyze, $mIns)
    && preg_match('/\b(is_admin|is_primary_admin)\b/i', $mIns[2])) {
    $denyWrite('Contas criadas por este canal não podem receber permissões administrativas. Use Gerenciar Administradores.');
}

if ($stmtType !== '' && !$isAdmin) {
    if ($stmtType === 'INSERT' || $stmtType === 'REPLACE') {
        $allowedInsert = '/^\s*(INSERT|REPLACE)\s+(INTO\s+)?`?(logs_auditoria|avaliacoes|solicitacoes|rotas|notificacoes)`?\b/i';
        if (!preg_match($allowedInsert, $sqlAnalyze)) {
            $denyWrite('Seu perfil não tem permissão para inserir registros nesta tabela.');
        }
    } elseif ($stmtType === 'DELETE') {
        if (!preg_match('/^\s*DELETE\s+FROM\s+`?notificacoes`?\b/i', $sqlAnalyze)) {
            $denyWrite('Seu perfil não tem permissão para excluir registros nesta tabela.');
        }
    } else { // UPDATE
        if ($updTable === 'usuarios') {
            if (preg_match($privColumn, $updSet)) {
                $denyWrite('Campos privilegiados do usuário não podem ser alterados por este canal.');
            }
            $ownId = (string)(int)($authUser['id'] ?? 0);
            $ownRow = false;
            foreach ($params as $p) {
                if (is_scalar($p) && (string)$p === $ownId) { $ownRow = true; break; }
            }
            if (!$ownRow || !preg_match('/^`?id`?\s*=\s*\?\s*$/', $updWhere)) {
                $denyWrite('Você só pode atualizar o seu próprio perfil.');
            }
        } elseif ($updTable === 'notificacoes') {
            if (!preg_match('/^\s*is_read\s*=\s*1\s*$/i', $updSet)) {
                $denyWrite('Somente a marcação de leitura de notificações é permitida.');
            }
        } elseif ($updTable === 'locais') {
            if (preg_match('/\b(?!rating\b|reviews_count\b|updated_at\b)\w+\s*=/i', $updSet)) {
                $denyWrite('Somente a atualização da média de avaliações do local é permitida.');
            }
        } else {
            $denyWrite('Seu perfil não tem permissão para atualizar registros nesta tabela.');
        }
    }
}

$db = get_db();

// Nunca devolve hashes/segredos ao cliente, mesmo em SELECT *
$redactRow = static function (?array $row): ?array {
    if ($row === null) {
        return null;
    }
    foreach (['password_hash', 'password', 'senha', 'senha_hash', 'session_token'] as $secret) {
        unset($row[$secret]);
    }
    return $row;
};

try {
    $stmt = $db->prepare($sqlExec);
    $stmt->execute($params);

    if ($action === 'query') {
        $rows = array_map($redactRow, $stmt->fetchAll(PDO::FETCH_ASSOC));
        echo json_encode(['success' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'query_one') {
        $row = $redactRow($stmt->fetch(PDO::FETCH_ASSOC) ?: null);
        echo json_encode(['success' => true, 'row' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'run') {
        $lastId   = $db->lastInsertId();
        $affected = $stmt->rowCount();
        echo json_encode([
            'success'  => true,
            'last_id'  => $lastId ? (int)$lastId : null,
            'affected' => $affected
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

} catch (PDOException $e) {
    error_log("[sql_bridge DB Error] " . $e->getMessage() . " | SQL: " . $sqlClean);
    handle_db_error($e, 'Não foi possível concluir a operação de dados. Tente novamente.');
}
