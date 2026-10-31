/**
 * Passe Livre Suzano — Base de Dados de Usuários
 * Integração SQL: expõe window.USUARIOS_DB lido do banco SQL (tabela users)
 * Mantém compatibilidade com perfil-gestor.js e perfil-cidadao-detalhe.js
 */

// Constrói USUARIOS_DB a partir do banco SQL assim que estiver pronto
function buildUsuariosDB() {
  if (!window.PLDB) return window.USUARIOS_DB || [];
  try {
    const rows = window.PLDB.users.findAll();
    window.USUARIOS_DB = rows.map(u => {
      let pcd = [];
      try { pcd = JSON.parse(u.pcd_categories || '[]'); } catch (_) {}
      const st = (u.status || 'ativo').toLowerCase();
      const statusTitle = st === 'ativo' ? 'Ativo' : (st === 'bloqueado' ? 'Bloqueado' : 'Pendente');

      return {
        // Campos compatíveis com perfil-gestor.js
        id:              u.user_code,
        nome:            u.name,
        nomeExibicao:    u.name,
        avatar:          u.avatar || 'assets/images/avatar.jpg',
        email:           u.email,
        telefone:        u.phone || '—',
        cpf:             u.cpf || '***.***.***-**',
        nascimento:      u.birth_date || null,
        idade:           u.birth_date ? (new Date().getFullYear() - new Date(u.birth_date).getFullYear()) : null,
        genero:          u.gender || 'Não informado',
        estadoCivil:     '—',
        endereco:        u.address || '—',
        bairro:          u.bairro || 'Centro',
        cidade:          u.city || 'Suzano - SP',
        cep:             '08674-000',
        deficiencia:     pcd.length > 0 ? pcd[0] : 'Não informada',
        cid:             '—',
        locomocao:       pcd.includes('Física/motora') ? 'Cadeira de Rodas' : '—',
        laudoValido:     true,
        laudoArquivo:    null,
        status:          statusTitle,
        membroDesde:     u.created_at ? u.created_at.slice(0,10).split('-').reverse().join('/') : '—',
        ultimoAcesso:    u.updated_at ? u.updated_at.slice(0,16).replace('T',' ') : '—',
        emailVerificado: u.email_verified === 1,
        telefoneVerificado: false,
        solicitacoes: 0,
        solicitacoesAprovadas: 0,
        solicitacoesAnalise: 0,
        parecer: u.is_admin ? 'Administrador municipal com acesso total ao painel do gestor.' : 'Cidadão cadastrado na plataforma Passe Livre Suzano.',
        isAdmin: u.is_admin === 1,
        role:    u.role,
        // Campos extras SQL
        sqlId:   u.id,
        pcdCategories: pcd
      };
    });
    console.log(`[usuarios.js SQL] USUARIOS_DB populado com ${window.USUARIOS_DB.length} usuários do banco MySQL.`);
    return window.USUARIOS_DB;
  } catch (e) {
    console.warn('[usuarios.js] Falha ao carregar usuários do SQL:', e);
    if (!window.USUARIOS_DB) window.USUARIOS_DB = [];
    return window.USUARIOS_DB;
  }
}

window.buildUsuariosDB = buildUsuariosDB;

// Inicializa assim que o banco SQL estiver pronto
if (window.PasseLivreDBReady) {
  window.PasseLivreDBReady.then(buildUsuariosDB);
} else {
  // Fallback: array vazio até o banco carregar
  if (!window.USUARIOS_DB) window.USUARIOS_DB = [];
  document.addEventListener('DOMContentLoaded', () => {
    if (window.PasseLivreDBReady) window.PasseLivreDBReady.then(buildUsuariosDB);
  });
}
