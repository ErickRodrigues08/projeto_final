<?php
/**
 * Passe Livre Suzano — Endpoint de Configurações Administrativas
 * Parâmetros do município, regras de SLA, integrações (`integracoes`, `integracao_logs`),
 * equipe e papéis (`usuario_papeis`), permissões (`papel_permissoes`) e convite de membros.
 */

require_once __DIR__ . '/_db_restore.php';
require_once __DIR__ . '/../includes/admins.php';

$admin = require_admin();
$action = $_GET['action'] ?? 'get';
$db     = get_db();
$data   = get_request_data();

switch ($action) {

    // =========================================================================
    // 1. CARREGAR TODAS AS CONFIGURAÇÕES
    // =========================================================================
    case 'get':
        // Parâmetros do sistema
        $stmtCfg = $db->query("SELECT * FROM configuracoes ORDER BY config_group ASC, id ASC");
        $configs = $stmtCfg->fetchAll();
        $configMap = [];
        foreach ($configs as $c) {
            $configMap[$c['config_key']] = $c['config_value'];
        }

        // Integrações
        $stmtInt = $db->query("SELECT * FROM integracoes ORDER BY id ASC");
        $integracoes = $stmtInt->fetchAll();

        // Logs recentes de integração
        $stmtIntLogs = $db->query("
            SELECT il.*, i.nome AS integracao_nome
            FROM integracao_logs il
            INNER JOIN integracoes i ON i.id = il.integracao_id
            ORDER BY il.created_at DESC
            LIMIT 15
        ");
        $integracaoLogs = $stmtIntLogs->fetchAll();

        // Equipe administrativa (Gestores e fiscais)
        $stmtTeam = $db->query("
            SELECT u.id, u.user_code, u.name, u.email, u.role, u.avatar, u.status,
                   up.papel, up.departamento, up.created_at AS papel_desde
            FROM usuarios u
            INNER JOIN usuario_papeis up ON up.user_id = u.id
            ORDER BY u.name ASC
        ");
        $equipe = $stmtTeam->fetchAll();

        // Matriz de permissões
        $stmtPerm = $db->query("SELECT * FROM papel_permissoes ORDER BY papel ASC, modulo ASC");
        $permissoes = $stmtPerm->fetchAll();

        json_response([
            'success'         => true,
            'current_user_id' => (int)$admin['id'],
            'configs'         => $configMap,
            'configs_raw'     => $configs,
            'integracoes'     => $integracoes,
            'integracao_logs' => $integracaoLogs,
            'equipe'          => $equipe,
            'permissoes'      => $permissoes
        ]);
        break;

    // =========================================================================
    // 2. ATUALIZAR PARÂMETROS MUNICIPAIS E SLA
    // =========================================================================
    case 'save_params':
        $params = is_array($data['configs'] ?? null) ? $data['configs'] : $data;

        $stmtUp = $db->prepare("
            INSERT INTO configuracoes (config_key, config_value, config_group, label)
            VALUES (?, ?, 'geral', ?)
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_at = NOW()
        ");

        $allowedKeys = [
            'municipio_nome', 'municipio_uf', 'municipio_prefeito',
            'raio_padrao_km', 'sla_triagem_horas', 'sla_atendimento_dias',
            'notificar_email_cidadao', 'otp_expiracao_minutos', 'bloqueio_tentativas_falhas',
            'unidade_gestora', 'decreto_municipal', 'laudo_temporalidade', 'laudo_permissao',
            'concessao_automatica', 'notificar_app_cidadao',
            'escalonamento_aviso_horas', 'escalonamento_atraso_horas'
        ];

        foreach ($params as $k => $v) {
            if (in_array($k, $allowedKeys)) {
                $stmtUp->execute([$k, (string)$v, ucfirst(str_replace('_', ' ', $k))]);
            }
        }

        log_audit(
            'configuracoes',
            'Parâmetros Municipais Atualizados',
            "Gestor {$admin['name']} atualizou as configurações e regras de SLA do município",
            'alto',
            $admin['id']
        );

        json_response([
            'success' => true,
            'message' => 'Parâmetros atualizados com sucesso no banco de dados!'
        ]);
        break;

    // =========================================================================
    // 3. ALTERAR STATUS DE INTEGRAÇÃO (ATIVO / INATIVO)
    // =========================================================================
    case 'toggle_integration':
        $id     = (int)($data['id'] ?? 0);
        $status = trim($data['status'] ?? 'ativo'); // 'ativo' ou 'inativo'

        if ($id <= 0 || !in_array($status, ['ativo', 'inativo', 'instavel'])) {
            json_error('ID de integração ou status inválido.');
        }

        $stmt = $db->prepare("UPDATE integracoes SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $id]);

        // Registra log de conexão na tabela `integracao_logs`
        $stmtLog = $db->prepare("
            INSERT INTO integracao_logs (integracao_id, metodo, endpoint, status_http, sucesso, resposta, tempo_ms)
            VALUES (?, 'POST', '/api/v1/healthcheck', ?, ?, ?, ?)
        ");
        $httpCode = ($status === 'ativo') ? 200 : 503;
        $sucesso  = ($status === 'ativo') ? 1 : 0;
        $stmtLog->execute([$id, $httpCode, $sucesso, "Status alterado para {$status}", 85]);

        log_audit(
            'integracoes',
            "Status de Integração Alterado ({$status})",
            "Integração ID {$id} atualizada para '{$status}' por {$admin['name']}",
            'medio',
            $admin['id']
        );

        json_response([
            'success' => true,
            'message' => "Integração atualizada para \"{$status}\" com sucesso!"
        ]);
        break;

    // =========================================================================
    // 4. ATUALIZAR PERMISSÕES DE PAPÉIS (RBAC)
    // =========================================================================
    case 'save_permissions':
        $permList = is_array($data['permissoes'] ?? null) ? $data['permissoes'] : [];

        $stmtUp = $db->prepare("
            INSERT INTO papel_permissoes (papel, modulo, pode_ler, pode_criar, pode_editar, pode_excluir, pode_despachar)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
              pode_ler = VALUES(pode_ler),
              pode_criar = VALUES(pode_criar),
              pode_editar = VALUES(pode_editar),
              pode_excluir = VALUES(pode_excluir),
              pode_despachar = VALUES(pode_despachar)
        ");

        foreach ($permList as $p) {
            $stmtUp->execute([
                $p['papel'] ?? 'fiscal',
                $p['modulo'] ?? 'solicitacoes',
                !empty($p['pode_ler']) ? 1 : 0,
                !empty($p['pode_criar']) ? 1 : 0,
                !empty($p['pode_editar']) ? 1 : 0,
                !empty($p['pode_excluir']) ? 1 : 0,
                !empty($p['pode_despachar']) ? 1 : 0
            ]);
        }

        log_audit(
            'configuracoes',
            'Matriz de Permissões RBAC Atualizada',
            "Permissões de acesso da equipe atualizadas por {$admin['name']}",
            'alto',
            $admin['id']
        );

        json_response([
            'success' => true,
            'message' => 'Matriz de permissões atualizada com sucesso!'
        ]);
        break;

    // =========================================================================
    // 5. CONVIDAR NOVO MEMBRO DA EQUIPE
    // =========================================================================
    case 'invite_member':
        $nome     = trim($data['name'] ?? '');
        $email    = strtolower(trim($data['email'] ?? ''));
        $papel    = trim($data['papel'] ?? 'fiscal'); // 'gestor', 'fiscal', 'atendente'
        $cargo    = trim($data['cargo'] ?? '');
        $depto    = trim($data['departamento'] ?? 'Secretaria Municipal de Mobilidade');
        $senha    = trim($data['password'] ?? '');
        $senhaGerada = false;

        if (!in_array($papel, ['gestor', 'fiscal', 'atendente'], true)) {
            $papel = 'fiscal';
        }

        // Papel 'gestor' grava is_admin = 1, ou seja, concede acesso ao Painel do
        // Gestor. Isso é concessão de privilégio: só o administrador principal faz,
        // no módulo Gerenciar Administradores.
        if ($papel === 'gestor' && !pl_admin_is_primary($db, (int)$admin['id'])) {
            log_audit(
                'administradores',
                'Criação de Administrador Recusada (Convite de Equipe)',
                "Resultado: RECUSADO. \"{$admin['name']}\" (ID {$admin['id']}) tentou criar uma conta com acesso de administrador pelo convite de equipe, que exige o administrador principal.",
                'alto',
                (int)$admin['id']
            );
            json_error(
                'Somente o administrador principal cria contas com acesso ao Painel do Gestor. Convide como fiscal ou atendente e conceda o acesso depois em Gerenciar Administradores.',
                403,
                ['code' => 'admin_principal_exigido']
            );
        }

        if ($senha === '') {
            $senha = 'PL-' . bin2hex(random_bytes(4)); // senha temporária devolvida ao gestor
            $senhaGerada = true;
        }

        if (empty($nome) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('Nome e e-mail válidos são obrigatórios.');
        }

        // Verifica se e-mail já existe
        $stmtChk = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $stmtChk->execute([$email]);
        if ($stmtChk->fetch()) {
            json_error('Este e-mail já está em uso por outro usuário.');
        }

        try {
            $db->beginTransaction();

            $code = 'ADM-' . date('Y') . '-' . str_pad((string)mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
            $initials = mb_strtoupper(mb_substr($nome, 0, 1) . 'M');
            $isAdmin = ($papel === 'gestor') ? 1 : 0;
            $roleName = $cargo !== ''
                ? $cargo
                : (($papel === 'gestor') ? 'Administrador Municipal' : 'Fiscal de Acessibilidade');
            $hash = password_hash($senha, PASSWORD_DEFAULT);

            // Cria usuário
            $stmtUser = $db->prepare("
                INSERT INTO usuarios
                  (user_code, name, email, password_hash, role, is_admin, avatar, initials, status, email_verified)
                VALUES
                  (?, ?, ?, ?, ?, ?, 'assets/images/avatar-admin.jpg', ?, 'ativo', 1)
            ");
            $stmtUser->execute([$code, $nome, $email, $hash, $roleName, $isAdmin, $initials]);
            $userId = (int)$db->lastInsertId();

            // Atribui papel em `usuario_papeis`
            $stmtPapel = $db->prepare("INSERT INTO usuario_papeis (user_id, papel, departamento) VALUES (?, ?, ?)");
            $stmtPapel->execute([$userId, $papel, $depto]);

            log_audit(
                'usuarios',
                "Novo Membro da Equipe Cadastrado: {$nome}",
                "Membro {$nome} ({$email}) convidado com papel '{$papel}' por {$admin['name']}",
                'alto',
                $admin['id']
            );

            $db->commit();

            json_response([
                'success'       => true,
                'user_id'       => $userId,
                'temp_password' => $senhaGerada ? $senha : null,
                'message'       => "Membro {$nome} adicionado com sucesso à equipe com papel \"{$roleName}\"!"
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            json_error('Erro ao adicionar membro: ' . $e->getMessage(), 500);
        }
        break;

    // =========================================================================
    // 6. EXIBIR O SCHEMA DO BANCO (somente gestor autenticado)
    // =========================================================================
    case 'schema':
        $schemaFile = dirname(__DIR__) . '/banco_mysql.sql';
        if (!is_file($schemaFile)) {
            json_error('Arquivo de schema não encontrado.', 404);
        }
        header('Content-Type: text/plain; charset=utf-8');
        readfile($schemaFile);
        exit;

    default:
        json_error('Ação de configuração não suportada.', 404);
}
