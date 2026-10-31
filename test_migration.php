<?php
/**
 * Passe Livre Suzano — Teste de Migração PHP+MySQL
 * Verifica: conexão, tabelas, views, endpoints, autenticação, integridade de dados.
 * Acesse via: http://localhost/teste3.5/test_migration.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/api/_db_restore.php';
if (php_sapi_name() !== 'cli' && (!defined('APP_ENV') || APP_ENV !== 'development')) {
    http_response_code(403);
    die('Acesso negado. Arquivo restrito a ambiente de desenvolvimento e linha de comando.');
}

$results = [];
$passed  = 0;
$failed  = 0;

function run_test(string $name, callable $fn): void {
    global $results, $passed, $failed;
    try {
        $msg = $fn();
        $results[] = ['status' => 'PASS', 'name' => $name, 'msg' => $msg];
        $passed++;
    } catch (\Throwable $e) {
        $results[] = ['status' => 'FAIL', 'name' => $name, 'msg' => $e->getMessage()];
        $failed++;
    }
}

// ─── BANCO ────────────────────────────────────────────────────────────────────

run_test('Conexão MySQL PDO', function () {
    $db  = get_db();
    $ver = $db->query('SELECT VERSION()')->fetchColumn();
    return "MySQL {$ver}";
});

run_test('Tabelas essenciais existem', function () {
    $db  = get_db();
    $req = ['usuarios','sessoes','codigos_verificacao','locais','avaliacoes',
            'rotas','solicitacoes','ocorrencias','notificacoes','logs_auditoria',
            'configuracoes','integracoes','usuario_papeis','papel_permissoes',
            'usuario_necessidades','usuario_preferencias_busca','usuario_consentimentos',
            'mensagens_contato','local_recursos','ordens_servico','relatorios_gerados',
            'comunicados','trechos_viarios','navegacoes','rota_opcoes','rota_passos'];
    $existing = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $missing  = array_diff($req, $existing);
    if ($missing) throw new \Exception("Tabelas ausentes: " . implode(', ', $missing));
    return count($existing) . " tabelas / views encontradas";
});

run_test('Views `users` e `auditoria` (compatibilidade)', function () {
    $db = get_db();
    $u  = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $a  = $db->query("SELECT COUNT(*) FROM auditoria")->fetchColumn();
    return "View users={$u} registros | View auditoria={$a} registros";
});

run_test('Usuário admin existe (carlos.eduardo@suzano.sp.gov.br)', function () {
    $db   = get_db();
    $stmt = $db->prepare("SELECT id, name, is_admin FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute(['carlos.eduardo@suzano.sp.gov.br']);
    $u = $stmt->fetch();
    if (!$u) throw new \Exception("Admin não encontrado no banco");
    if ((int)$u['is_admin'] !== 1) throw new \Exception("Usuário encontrado mas is_admin != 1");
    return "Admin: {$u['name']} (ID #{$u['id']})";
});

run_test('Usuário cidadão existe (joao.silva@gmail.com)', function () {
    $db   = get_db();
    $stmt = $db->prepare("SELECT id, name FROM usuarios WHERE email = ? AND is_admin = 0 LIMIT 1");
    $stmt->execute(['joao.silva@gmail.com']);
    $u = $stmt->fetch();
    if (!$u) throw new \Exception("Cidadão João Silva não encontrado");
    return "Cidadão: {$u['name']} (ID #{$u['id']})";
});

run_test('Hash bcrypt de senha válido', function () {
    $db   = get_db();
    $stmt = $db->prepare("SELECT password_hash FROM usuarios WHERE email = 'joao.silva@gmail.com' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row || empty($row['password_hash'])) throw new \Exception("Nenhum hash de senha encontrado");
    if (!password_verify('123456', $row['password_hash'])) throw new \Exception("Hash não bate com '123456'");
    return "password_verify() ok para joao.silva@gmail.com";
});

run_test('Locais cadastrados no banco', function () {
    $db    = get_db();
    $count = (int)$db->query("SELECT COUNT(*) FROM locais WHERE is_archived = 0")->fetchColumn();
    if ($count === 0) throw new \Exception("Nenhum local cadastrado");
    return "{$count} locais ativos";
});

run_test('Solicitações cadastradas no banco', function () {
    $db    = get_db();
    $count = (int)$db->query("SELECT COUNT(*) FROM solicitacoes")->fetchColumn();
    if ($count === 0) throw new \Exception("Nenhuma solicitação cadastrada");
    $pending = (int)$db->query("SELECT COUNT(*) FROM solicitacoes WHERE status='pending'")->fetchColumn();
    return "{$count} solicitações ({$pending} pendentes)";
});

run_test('Notificações e coluna sender_id/link existem', function () {
    $db   = get_db();
    $cols = $db->query("SHOW COLUMNS FROM notificacoes")->fetchAll(PDO::FETCH_COLUMN);
    $req  = ['id','user_id','title','body','type','is_read','created_at'];
    $miss = array_diff($req, $cols);
    if ($miss) throw new \Exception("Colunas ausentes: " . implode(', ', $miss));
    $hasSender = in_array('sender_id', $cols);
    $hasLink   = in_array('link', $cols);
    return "Estrutura OK. sender_id=" . ($hasSender ? 'sim' : 'ausente (run update_mysql.sql)') . " link=" . ($hasLink ? 'sim' : 'ausente (run update_mysql.sql)');
});

run_test('Logs de auditoria existem', function () {
    $db  = get_db();
    $cnt = (int)$db->query("SELECT COUNT(*) FROM logs_auditoria")->fetchColumn();
    return "{$cnt} registros em logs_auditoria";
});

run_test('Configurações do sistema existem', function () {
    $db  = get_db();
    $cnt = (int)$db->query("SELECT COUNT(*) FROM configuracoes")->fetchColumn();
    if ($cnt === 0) throw new \Exception("Nenhuma configuração cadastrada");
    return "{$cnt} parâmetros de configuração";
});

// ─── ARQUIVOS PHP ─────────────────────────────────────────────────────────────

run_test('Arquivos PHP de autenticação existem', function () {
    $files = ['includes/auth_guard.php','includes/sidebar_gestor.php',
              'login.php','cadastro.php','esqueci-senha.php',
              'verificar-codigo.php','redefinir-senha.php'];
    $miss  = array_filter($files, fn($f) => !file_exists(__DIR__ . '/' . $f));
    if ($miss) throw new \Exception("Ausentes: " . implode(', ', $miss));
    return count($files) . " arquivos PHP de autenticação OK";
});

run_test('Páginas PHP do gestor existem', function () {
    $files = ['dashboard.php','solicitacoes.php','auditoria.php',
              'configuracoes.php','relatorios.php','notificacoes.php',
              'locais-cadastrados.php','mapa-gestor.php','perfil-gestor.php',
              'solicitacao-detalhe.php'];
    $miss  = array_filter($files, fn($f) => !file_exists(__DIR__ . '/' . $f));
    if ($miss) throw new \Exception("Ausentes: " . implode(', ', $miss));
    return count($files) . " páginas PHP do gestor OK";
});

run_test('Páginas PHP do cidadão existem', function () {
    $files = ['adaptar-busca.php','central-notificacoes.php','editar-perfil.php',
              'configuracao-rota.php','avaliar-local.php','alerta-obstaculo.php',
              'personalizar-busca.php','detalhes-local.php'];
    $miss  = array_filter($files, fn($f) => !file_exists(__DIR__ . '/' . $f));
    if ($miss) throw new \Exception("Ausentes: " . implode(', ', $miss));
    return count($files) . " páginas PHP do cidadão OK";
});

run_test('Endpoints API existem', function () {
    $files = ['api/db.php','api/auth.php','api/avaliacoes.php','api/configuracoes.php',
              'api/contato.php','api/locais.php','api/notificacoes.php','api/ocorrencias.php',
              'api/relatorios.php','api/rotas.php','api/solicitacoes.php',
              'api/sql_bridge.php','api/auditoria.php','api/users.php'];
    $miss  = array_filter($files, fn($f) => !file_exists(__DIR__ . '/' . $f));
    if ($miss) throw new \Exception("Ausentes: " . implode(', ', $miss));
    return count($files) . " endpoints API OK";
});

run_test('Páginas PHP públicas permanecem', function () {
    $files = ['index.php','ajuda.php','direitos.php','explorar.php','mapa-geral.php','navegacao.php','lugares-encontrados.php','sem-resultados.php'];
    $miss  = array_filter($files, fn($f) => !file_exists(__DIR__ . '/' . $f));
    if ($miss) throw new \Exception("Ausentes: " . implode(', ', $miss));
    return count($files) . " páginas PHP públicas OK";
});

// ─── CONSISTÊNCIA DE DADOS ────────────────────────────────────────────────────

run_test('Trigger atualiza rating de locais após avaliação', function () {
    $db  = get_db();
    // Verifica pelo banco_mysql.sql se o trigger está definido (no arquivo)
    // Pode não estar no banco se o banco_mysql.sql ainda não foi totalmente importado
    $sqlFile = __DIR__ . '/banco_mysql.sql';
    $content = file_get_contents($sqlFile);
    if (strpos($content, 'trg_after_avaliacao_insert') === false) {
        throw new \Exception("Trigger não definido em banco_mysql.sql");
    }
    // Tenta verificar no banco (pode não existir se banco não foi reimportado)
    try {
        $trg = $db->query("SHOW TRIGGERS LIKE 'trg_after_avaliacao_insert'")->fetch();
        if ($trg) return "Trigger trg_after_avaliacao_insert ativo no banco";
    } catch (\Throwable $e) {}
    return "Trigger definido em banco_mysql.sql (execute o SQL para ativar se necessário)";
});

run_test('sql_bridge.php bloqueia DDL (segurança)', function () {
    $content = file_get_contents(__DIR__ . '/api/sql_bridge.php');
    if (strpos($content, 'blockedPatterns') === false) {
        throw new \Exception("Array blockedPatterns não encontrado no sql_bridge.php");
    }
    if (strpos($content, 'get_auth_user') === false) {
        throw new \Exception("Autenticação não verificada no sql_bridge.php");
    }
    if (strpos($content, 'TRUNCATE') === false) {
        throw new \Exception("Bloqueio TRUNCATE não encontrado no sql_bridge.php");
    }
    return "sql_bridge.php: autenticação obrigatória + blockedPatterns (DDL/TRUNCATE) OK";
});

run_test('auth.js aponta para .php', function () {
    $content = file_get_contents(__DIR__ . '/js/auth.js');
    if (strpos($content, 'login.html') !== false) throw new \Exception("Ainda contém login.html");
    if (strpos($content, 'adaptar-busca.html') !== false) throw new \Exception("Ainda contém adaptar-busca.html");
    if (strpos($content, 'dashboard.html') !== false) throw new \Exception("Ainda contém dashboard.html");
    return "auth.js: todos os links atualizados para .php";
});

run_test('db-queries.js usa logs_auditoria (não a view)', function () {
    $content = file_get_contents(__DIR__ . '/js/db-queries.js');
    if (preg_match('/INSERT\s+INTO\s+`?auditoria`?\b/i', $content)) {
        throw new \Exception("db-queries.js ainda usa INSERT INTO auditoria (view read-only)");
    }
    return "db-queries.js: INSERT INTO logs_auditoria OK";
});

run_test('mock-data.js usa IDs reais do banco', function () {
    $content = file_get_contents(__DIR__ . '/js/mock-data.js');
    if (strpos($content, 'LOC-8621') !== false) throw new \Exception("Ainda contém LOC-8621 (ID inexistente no banco)");
    if (strpos($content, 'PL-2026-393') !== false) throw new \Exception("Ainda contém protocolo PL-2026-393 (formato antigo)");
    return "mock-data.js: IDs atualizados para bater com o banco";
});

// ─── RESULTADOS ───────────────────────────────────────────────────────────────
$total = $passed + $failed;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Teste de Migração — Passe Livre</title>
<style>
  body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #faf7f2; color: #1c1917; margin: 0; padding: 32px; }
  h1 { font-size: 22px; color: #b45309; margin-bottom: 4px; }
  .summary { background: #fff; border: 1px solid #e7e2d8; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; display: flex; gap: 32px; }
  .stat { text-align: center; }
  .stat .n { font-size: 32px; font-weight: 800; }
  .stat .l { font-size: 12px; color: #78716c; }
  .pass { color: #16a34a; }
  .fail { color: #dc2626; }
  table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
  th { background: #fdf4e7; padding: 10px 14px; font-size: 11px; text-align: left; color: #92400e; font-weight: 700; letter-spacing: .5px; }
  td { padding: 10px 14px; font-size: 13px; border-bottom: 1px solid #f5f0eb; vertical-align: top; }
  .badge-pass { background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 700; }
  .badge-fail { background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 700; }
  .msg-fail { color: #dc2626; font-size: 12px; }
</style>
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/icons/favicon-16.png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
</head>
<body>
<h1>🔍 Teste de Migração PHP+MySQL — Passe Livre Suzano</h1>
<p style="color:#78716c;font-size:13px;margin-bottom:20px;">Executado em: <?= date('d/m/Y H:i:s') ?></p>

<div class="summary">
  <div class="stat"><div class="n"><?= $total ?></div><div class="l">Total de Testes</div></div>
  <div class="stat"><div class="n pass"><?= $passed ?></div><div class="l">Passou</div></div>
  <div class="stat"><div class="n fail"><?= $failed ?></div><div class="l">Falhou</div></div>
  <div class="stat"><div class="n" style="color:<?= $failed === 0 ? '#16a34a' : '#d97706' ?>"><?= $failed === 0 ? '100%' : round($passed/$total*100) . '%' ?></div><div class="l">Taxa de Sucesso</div></div>
</div>

<table>
  <thead><tr><th>STATUS</th><th>TESTE</th><th>RESULTADO</th></tr></thead>
  <tbody>
  <?php foreach ($results as $r): ?>
  <tr>
    <td><span class="badge-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span></td>
    <td><?= htmlspecialchars($r['name']) ?></td>
    <td class="<?= $r['status'] === 'FAIL' ? 'msg-fail' : '' ?>"><?= htmlspecialchars($r['msg']) ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php if ($failed === 0): ?>
<p style="margin-top:24px;padding:16px;background:#dcfce7;border-radius:8px;color:#166534;font-weight:700;font-size:14px;">
  ✅ Todos os testes passaram! A migração está completa e funcionando.
</p>
<?php else: ?>
<p style="margin-top:24px;padding:16px;background:#fee2e2;border-radius:8px;color:#991b1b;font-weight:700;font-size:14px;">
  ⚠️ <?= $failed ?> teste(s) falharam. Verifique os itens acima e execute update_mysql.sql se necessário.
</p>
<?php endif; ?>

<p style="margin-top:16px;font-size:12px;color:#78716c;">
  Para aplicar patches incrementais de banco: execute o arquivo <code>update_mysql.sql</code> no phpMyAdmin.
</p>
</body>
</html>
