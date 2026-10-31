/**
 * Passe Livre Suzano — Módulo de Queries SQL (db-queries.js)
 *
 * Centraliza todas as operações CRUD do banco.
 * Depende de window.PasseLivreDB (inicializado em db.js).
 * Expõe: window.PLDB  (objeto com todos os métodos de acesso ao banco)
 *
 * Uso:
 *   await window.PasseLivreDBReady;
 *   const user = PLDB.users.findByEmail('joao@email.com');
 */

(function () {
  'use strict';

  /* Helper: gera hash SHA-256 simples (para logs de auditoria) */
  async function sha256(message) {
    if (window.crypto && window.crypto.subtle) {
      const msgBuffer = new TextEncoder().encode(message);
      const hashBuffer = await crypto.subtle.digest('SHA-256', msgBuffer);
      const hashArray = Array.from(new Uint8Array(hashBuffer));
      return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }
    // Fallback determinístico se SubtleCrypto não disponível
    let hash = 0;
    for (let i = 0; i < message.length; i++) {
      const char = message.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash;
    }
    return Math.abs(hash).toString(16).padStart(64, '0');
  }

  /* Helper: insere log de auditoria automaticamente */
  async function logAudit(module, title, desc, risk) {
    const db = window.PasseLivreDB;
    if (!db) return;
    let operator = 'Sistema';
    let opRole   = 'Automático';
    try {
      const stored = localStorage.getItem('passeLivreUser');
      if (stored) {
        const u = JSON.parse(stored);
        operator = u.name  || 'Usuário';
        opRole   = u.role  || 'Cidadão';
      }
    } catch (_) {}
    const payload = `${module}|${title}|${desc}|${Date.now()}`;
    const hash = await sha256(payload);
    // INSERT direto no MySQL (tabela logs_auditoria — não usar a view `auditoria` pois é somente-leitura)
    db.run(
      `INSERT INTO logs_auditoria
         (operator_name,operator_role,action_module,action_title,action_desc,risk_level,hash_sha256)
       VALUES (?,?,?,?,?,?,?)`,
      [operator, opRole, module, title, desc, risk || 'baixo', hash]
    );
  }

  // Expõe para outros módulos poderem registrar auditoria
  window.PLDBAuditLog = logAudit;

  // =========================================================================
  // AGUARDA O BANCO ESTAR PRONTO ANTES DE MONTAR O OBJETO
  // =========================================================================
  function buildAPI(db) {

    const PLDB = {

      // =======================================================================
      // USERS
      // =======================================================================
      users: {

        /** SELECT * FROM users */
        findAll() {
          return db.query('SELECT * FROM users ORDER BY created_at DESC');
        },

        /** SELECT por e-mail */
        findByEmail(email) {
          return db.queryOne('SELECT * FROM users WHERE LOWER(email) = LOWER(?)', [email]);
        },

        /** SELECT por user_code (ex: "USR-2026-001") */
        findByCode(code) {
          return db.queryOne('SELECT * FROM users WHERE user_code = ?', [code]);
        },

        /** SELECT por ID */
        findById(id) {
          return db.queryOne('SELECT * FROM users WHERE id = ?', [id]);
        },

        /** SELECT usuários cidadãos (não admin) */
        findCitizens() {
          return db.query('SELECT * FROM users WHERE is_admin = 0 ORDER BY name');
        },

        /** INSERT novo usuário — retorna o objeto criado */
        create(data) {
          const code = 'USR-' + new Date().getFullYear() + '-' + String(Date.now()).slice(-3);
          db.run(
            `INSERT INTO usuarios
               (user_code,name,email,password_hash,phone,city,role,is_admin,avatar,initials,
                pcd_categories,saved_prefs,is_google)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)`,
            [
              code,
              data.name        || '',
              data.email       || '',
              data.password_hash || null,
              data.phone       || '',
              data.city        || 'Suzano - SP',
              data.role        || 'Cidadão Conectado',
              data.is_admin    || 0,
              data.avatar      || 'assets/images/avatar.jpg',
              data.initials    || (data.name || '').split(' ').slice(0,2).map(n => n[0]).join('').toUpperCase(),
              JSON.stringify(data.pcd_categories || []),
              JSON.stringify(data.saved_prefs    || []),
              data.is_google   || 0
            ]
          );
          logAudit('usuarios', 'Novo Cadastro', `Usuário "${data.name}" (${data.email}) cadastrado`, 'baixo');
          return db.queryOne('SELECT * FROM users WHERE email = ?', [data.email]);
        },

        /** UPDATE dados do perfil */
        update(id, data) {
          const fields = [];
          const params = [];
          const allowed = ['name','email','phone','city','role','avatar','initials','pcd_categories','saved_prefs','is_google','password_hash'];
          allowed.forEach(k => {
            if (data[k] !== undefined) {
              fields.push(`${k} = ?`);
              params.push(Array.isArray(data[k]) ? JSON.stringify(data[k]) : data[k]);
            }
          });
          if (!fields.length) return;
          fields.push("updated_at = NOW()");
          params.push(id);
          db.run(`UPDATE usuarios SET ${fields.join(', ')} WHERE id = ?`, params);
          logAudit('usuarios', 'Atualização de Perfil', `Perfil do usuário ID ${id} atualizado`, 'baixo');
        },

        /** DELETE usuário */
        delete(id) {
          db.run('DELETE FROM usuarios WHERE id = ?', [id]);
          logAudit('usuarios', 'Remoção de Usuário', `Usuário ID ${id} removido do sistema`, 'alto');
        },

        /** Contagem total */
        count() {
          const r = db.queryOne('SELECT COUNT(*) as n FROM users');
          return r ? r.n : 0;
        }
      },

      // =======================================================================
      // LOCAIS
      // =======================================================================
      locais: {

        /** SELECT todos, opcionalmente filtrado por category_key ou access_status */
        findAll(filters) {
          let sql  = 'SELECT * FROM locais WHERE 1=1';
          const p  = [];
          if (filters) {
            if (filters.category_key && filters.category_key !== 'all') {
              sql += ' AND category_key = ?'; p.push(filters.category_key);
            }
            if (filters.access_status && filters.access_status !== 'all') {
              sql += ' AND access_status = ?'; p.push(filters.access_status);
            }
            if (filters.q) {
              sql += ' AND (LOWER(name) LIKE ? OR LOWER(address) LIKE ? OR LOWER(bairro) LIKE ? OR LOWER(municipio) LIKE ?)';
              const q = `%${filters.q.toLowerCase()}%`;
              p.push(q, q, q, q);
            }
          }
          sql += ' ORDER BY rating DESC';
          return db.query(sql, p);
        },

        /** SELECT por ID */
        findById(id) {
          return db.queryOne('SELECT * FROM locais WHERE id = ?', [id]);
        },

        /** SELECT por local_code (ex: "LOC-8621") */
        findByCode(code) {
          return db.queryOne('SELECT * FROM locais WHERE local_code = ?', [code]);
        },

        /** INSERT novo local */
        create(data) {
          const code = 'LOC-' + String(Math.floor(1000 + Math.random() * 9000));
          const lat = Number.isFinite(Number(data.lat)) && data.lat !== null && data.lat !== '' ? Number(data.lat) : null;
          const lng = Number.isFinite(Number(data.lng)) && data.lng !== null && data.lng !== '' ? Number(data.lng) : null;
          db.run(
            `INSERT INTO locais
               (local_code,name,category,category_key,address,bairro,municipio,uf,
                access_status,access_text,rating,reviews_count,image,
                lat,lng,features,last_audit,avatar_color,avatar_icon,incidents_count)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
            [
              code,
              data.name           || '',
              data.category       || '',
              data.category_key   || 'outro',
              data.address        || '',
              data.bairro         || '',
              data.municipio      || null,
              data.uf             || 'SP',
              data.access_status  || 'unverified',
              data.access_text    || 'Não verificado',
              // Sem avaliação não existe média: 5.0 faria um local recém
              // cadastrado aparecer como o melhor avaliado da cidade.
              Number(data.rating) || 0,
              data.reviews_count  || 0,
              data.image          || 'assets/images/map-background.jpg',
              // As coordenadas vêm da geocodificação do endereço digitado. O
              // padrão fixo de Suzano colocava qualquer local do estado dentro
              // da cidade no mapa e nas buscas por proximidade.
              lat,
              lng,
              JSON.stringify(data.features || []),
              data.last_audit     || null,
              data.avatar_color   || 'orange',
              data.avatar_icon    || 'assets/icons/pin.svg',
              data.incidents_count || 0
            ]
          );
          logAudit('locais', 'Cadastro de Local', `Local "${data.name}" cadastrado (${code})`, 'medio');
          return db.queryOne('SELECT * FROM locais WHERE local_code = ?', [code]);
        },

        /** UPDATE local */
        update(id, data) {
          const fields = [];
          const params = [];
          const allowed = ['name','category','category_key','address','bairro','municipio','uf','access_status',
                           'access_text','rating','reviews_count','image','lat','lng',
                           'features','last_audit','avatar_color','avatar_icon','incidents_count'];
          allowed.forEach(k => {
            if (data[k] !== undefined) {
              fields.push(`${k} = ?`);
              params.push(Array.isArray(data[k]) ? JSON.stringify(data[k]) : data[k]);
            }
          });
          if (!fields.length) return;
          fields.push("updated_at = NOW()");
          params.push(id);
          db.run(`UPDATE locais SET ${fields.join(', ')} WHERE id = ?`, params);
          logAudit('locais', 'Atualização de Local', `Local ID ${id} atualizado`, 'medio');
        },

        /** DELETE local */
        delete(id) {
          const local = db.queryOne('SELECT name FROM locais WHERE id = ?', [id]);
          db.run('DELETE FROM locais WHERE id = ?', [id]);
          logAudit('locais', 'Remoção de Local', `Local "${local ? local.name : id}" removido do inventário`, 'alto');
        },

        /** Contagem por access_status */
        countByStatus() {
          return db.query('SELECT access_status, COUNT(*) as total FROM locais GROUP BY access_status');
        },

        /** Contagem total */
        count() {
          const r = db.queryOne('SELECT COUNT(*) as n FROM locais');
          return r ? r.n : 0;
        }
      },

      // =======================================================================
      // SOLICITAÇÕES
      // =======================================================================
      solicitacoes: {

        /** SELECT todas, com filtros opcionais */
        findAll(filters) {
          let sql = 'SELECT * FROM solicitacoes WHERE 1=1';
          const p = [];
          if (filters) {
            if (filters.status && filters.status !== 'all') {
              sql += ' AND status = ?'; p.push(filters.status);
            }
            if (filters.type_key && filters.type_key !== 'all') {
              sql += ' AND type_key = ?'; p.push(filters.type_key);
            }
            if (filters.region && filters.region !== 'all') {
              sql += ' AND region = ?'; p.push(filters.region);
            }
            if (filters.priority && filters.priority !== 'all') {
              sql += ' AND priority = ?'; p.push(filters.priority);
            }
            if (filters.q) {
              sql += ` AND (LOWER(citizen_name) LIKE ? OR LOWER(title) LIKE ?
                       OR LOWER(address) LIKE ? OR protocol LIKE ?)`;
              const q = `%${filters.q.toLowerCase()}%`;
              p.push(q, q, q, `%${filters.q}%`);
            }
          }
          sql += ' ORDER BY created_at DESC';
          return db.query(sql, p);
        },

        /** SELECT por ID numérico */
        findById(id) {
          return db.queryOne('SELECT * FROM solicitacoes WHERE id = ?', [id]);
        },

        /** SELECT por protocolo (ex: "PL-2026-393") */
        findByProtocol(protocol) {
          return db.queryOne('SELECT * FROM solicitacoes WHERE protocol = ?', [protocol]);
        },

        /** INSERT nova solicitação */
        create(data) {
          const year     = new Date().getFullYear();
          const protocol = `SOL-${year}-${Math.floor(1000 + Math.random() * 9000)}-${String(Date.now()).slice(-4)}`;
          db.run(
            `INSERT INTO solicitacoes
               (protocol,citizen_id,citizen_name,citizen_email,citizen_phone,citizen_avatar,
                pcd_category,badges,type_key,type_name,type_icon,title,address,region,
                region_name,complement,description,photo,status,status_name,priority,priority_name)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)`,
            [
              protocol,
              data.citizen_id    || null,
              data.citizen_name  || '',
              data.citizen_email || '',
              data.citizen_phone || '',
              data.citizen_avatar || 'assets/icons/user.svg',
              data.pcd_category  || '',
              JSON.stringify(data.badges || []),
              data.type_key      || 'outro',
              data.type_name     || '',
              data.type_icon     || 'assets/icons/pin.svg',
              data.title         || '',
              data.address       || '',
              data.region        || '',
              data.region_name   || '',
              data.complement    || '',
              data.description   || '',
              data.photo         || '',
              data.status        || 'pending',
              data.status_name   || 'Pendente de Triagem',
              data.priority      || 'media',
              data.priority_name || 'Média'
            ]
          );
          logAudit('solicitacoes', 'Nova Solicitação', `"${data.title}" registrada por ${data.citizen_name}`, 'baixo');
          return db.queryOne('SELECT * FROM solicitacoes WHERE protocol = ?', [protocol]);
        },

        /** UPDATE status de uma solicitação */
        updateStatus(id, status, statusName) {
          const map = {
            pending:   'Pendente de Triagem',
            analyzing: 'Em Análise Técnica',
            approved:  'Aprovada / Encaminhada',
            rejected:  'Indeferida'
          };
          const sName = statusName || map[status] || status;
          db.run(
            `UPDATE solicitacoes SET status = ?, status_name = ?, updated_at = NOW() WHERE id = ?`,
            [status, sName, id]
          );
          logAudit('solicitacoes', 'Triagem de Solicitação', `Solicitação ID ${id} → status "${sName}"`, 'medio');
        },

        /** Contagens por status (para KPIs do dashboard) */
        countByStatus() {
          return db.query('SELECT status, COUNT(*) as total FROM solicitacoes GROUP BY status');
        },

        /** Total de solicitações */
        count() {
          const r = db.queryOne('SELECT COUNT(*) as n FROM solicitacoes');
          return r ? r.n : 0;
        },

        /** Solicitações pendentes (para badge da sidebar) */
        countPending() {
          const r = db.queryOne("SELECT COUNT(*) as n FROM solicitacoes WHERE status = 'pending'");
          return r ? r.n : 0;
        },

        /** Dados para gráfico mensal (últimos N meses) */
        monthlyChart(months) {
          const rows = [];
          for (let i = months - 1; i >= 0; i--) {
            const d = new Date();
            d.setMonth(d.getMonth() - i);
            const ym = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2,'0')}`;
            const total    = db.queryOne(`SELECT COUNT(*) as n FROM solicitacoes WHERE DATE_FORMAT(created_at, '%Y-%m') = ?`, [ym]);
            const approved = db.queryOne(`SELECT COUNT(*) as n FROM solicitacoes WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND status = 'approved'`, [ym]);
            const rejected = db.queryOne(`SELECT COUNT(*) as n FROM solicitacoes WHERE DATE_FORMAT(created_at, '%Y-%m') = ? AND status = 'rejected'`, [ym]);
            rows.push({
              label: d.toLocaleString('pt-BR', { month: 'short' }),
              total: total ? total.n : 0,
              approved: approved ? approved.n : 0,
              rejected: rejected ? rejected.n : 0
            });
          }
          return rows;
        }
      },

      // =======================================================================
      // AVALIAÇÕES
      // =======================================================================
      avaliacoes: {

        /** SELECT todas de um local */
        findByLocal(localCode) {
          return db.query(
            'SELECT * FROM avaliacoes WHERE local_code = ? ORDER BY created_at DESC',
            [localCode]
          );
        },

        /** SELECT por usuário */
        findByUser(userId) {
          return db.query(
            'SELECT * FROM avaliacoes WHERE user_id = ? ORDER BY created_at DESC',
            [userId]
          );
        },

        /** INSERT nova avaliação */
        create(data) {
          db.run(
            `INSERT INTO avaliacoes
               (local_id,local_code,user_id,user_name,entrada_status,rampas_status,
                elevadores_status,banheiros_status,calcadas_status,estacionamento_status,
                barrier_desc,photos,rating)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)`,
            [
              data.local_id              || null,
              data.local_code            || '',
              data.user_id               || null,
              data.user_name             || 'Cidadão Anônimo',
              data.entrada_status        || null,
              data.rampas_status         || null,
              data.elevadores_status     || null,
              data.banheiros_status      || null,
              data.calcadas_status       || null,
              data.estacionamento_status || null,
              data.barrier_desc          || '',
              JSON.stringify(data.photos || []),
              data.rating                || 5.0
            ]
          );

          // Recalcula rating médio do local
          if (data.local_code) {
            const avg = db.queryOne(
              'SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM avaliacoes WHERE local_code = ?',
              [data.local_code]
            );
            if (avg) {
              db.run(
                `UPDATE locais SET rating = ?, reviews_count = ?, updated_at = NOW()
                 WHERE local_code = ?`,
                [parseFloat(avg.avg_r.toFixed(1)), avg.cnt, data.local_code]
              );
            }
          }

          logAudit('locais', 'Nova Avaliação', `Avaliação enviada para local ${data.local_code} por ${data.user_name}`, 'baixo');
        },

        /** Contagem total */
        count() {
          const r = db.queryOne('SELECT COUNT(*) as n FROM avaliacoes');
          return r ? r.n : 0;
        }
      },

      // =======================================================================
      // ROTAS
      // =======================================================================
      rotas: {

        /** SELECT rotas de um usuário */
        findByUser(userId) {
          return db.query(
            'SELECT * FROM rotas WHERE user_id = ? ORDER BY created_at DESC LIMIT 20',
            [userId]
          );
        },

        /** INSERT rota salva */
        create(data) {
          db.run(
            `INSERT INTO rotas
               (user_id,origin_text,destination_text,origin_lat,origin_lng,
                dest_lat,dest_lng,route_type,distance_km,duration_min)
             VALUES (?,?,?,?,?,?,?,?,?,?)`,
            [
              data.user_id          || null,
              data.origin_text      || '',
              data.destination_text || '',
              data.origin_lat       || null,
              data.origin_lng       || null,
              data.dest_lat         || null,
              data.dest_lng         || null,
              data.route_type       || 'acessivel',
              data.distance_km      || null,
              data.duration_min     || null
            ]
          );
          logAudit('rotas', 'Rota Calculada', `De "${data.origin_text}" para "${data.destination_text}"`, 'baixo');
        },

        /** Contagem total */
        count() {
          const r = db.queryOne('SELECT COUNT(*) as n FROM rotas');
          return r ? r.n : 0;
        }
      },

      // =======================================================================
      // NOTIFICAÇÕES
      // =======================================================================
      notificacoes: {

        /** SELECT notificações de um usuário */
        findByUser(userId, onlyUnread) {
          let sql = 'SELECT * FROM notificacoes WHERE user_id = ?';
          if (onlyUnread) sql += ' AND is_read = 0';
          sql += ' ORDER BY created_at DESC LIMIT 50';
          return db.query(sql, [userId]);
        },

        /** INSERT nova notificação */
        create(data) {
          db.run(
            `INSERT INTO notificacoes (user_id,title,body,type,local_code,is_read)
             VALUES (?,?,?,?,?,?)`,
            [
              data.user_id    || null,
              data.title      || '',
              data.body       || '',
              data.type       || 'info',
              data.local_code || null,
              0
            ]
          );
        },

        /** Marca como lida */
        markRead(id) {
          db.run('UPDATE notificacoes SET is_read = 1 WHERE id = ?', [id]);
        },

        /** Marca todas como lidas para um usuário */
        markAllRead(userId) {
          db.run('UPDATE notificacoes SET is_read = 1 WHERE user_id = ?', [userId]);
        },

        /** Conta não lidas */
        countUnread(userId) {
          const r = db.queryOne(
            'SELECT COUNT(*) as n FROM notificacoes WHERE user_id = ? AND is_read = 0',
            [userId]
          );
          return r ? r.n : 0;
        }
      },

      // =======================================================================
      // AUDITORIA
      // =======================================================================
      auditoria: {

        /** SELECT logs com filtros */
        findAll(filters) {
          let sql = 'SELECT * FROM logs_auditoria WHERE 1=1';
          const p = [];
          if (filters) {
            if (filters.module && filters.module !== 'all') {
              sql += ' AND action_module = ?'; p.push(filters.module);
            }
            if (filters.risk && filters.risk !== 'all') {
              sql += ' AND risk_level = ?'; p.push(filters.risk);
            }
            if (filters.q) {
              sql += ` AND (LOWER(operator_name) LIKE ? OR LOWER(action_title) LIKE ?
                       OR LOWER(action_desc) LIKE ?)`;
              const q = `%${filters.q.toLowerCase()}%`;
              p.push(q, q, q);
            }
          }
          sql += ' ORDER BY created_at DESC LIMIT 200';
          return db.query(sql, p);
        },

        /** INSERT direto (usado por logAudit) */
        insert(data) {
          db.run(
            `INSERT INTO logs_auditoria
               (operator_name,operator_role,action_module,action_title,action_desc,
                risk_level,ip_address,location_desc,hash_sha256)
             VALUES (?,?,?,?,?,?,?,?,?)`,
            [
              data.operator_name || 'Sistema',
              data.operator_role || 'Automático',
              data.action_module || 'sistema',
              data.action_title  || '',
              data.action_desc   || '',
              data.risk_level    || 'baixo',
              data.ip_address    || '',
              data.location_desc || 'Suzano, SP',
              data.hash_sha256   || ''
            ]
          );
        },

        /** Contagem por nível de risco */
        countByRisk() {
          return db.query('SELECT risk_level, COUNT(*) as total FROM logs_auditoria GROUP BY risk_level');
        },

        /** Total */
        count() {
          const r = db.queryOne('SELECT COUNT(*) as n FROM logs_auditoria');
          return r ? r.n : 0;
        }
      },

      // =======================================================================
      // CONFIGURAÇÕES
      // =======================================================================
      configuracoes: {

        /** SELECT todas as configurações */
        findAll() {
          return db.query('SELECT * FROM configuracoes ORDER BY config_group, config_key');
        },

        /** SELECT por grupo */
        findByGroup(group) {
          return db.query(
            'SELECT * FROM configuracoes WHERE config_group = ? ORDER BY config_key',
            [group]
          );
        },

        /** GET valor de uma config */
        get(key) {
          const r = db.queryOne('SELECT config_value FROM configuracoes WHERE config_key = ?', [key]);
          return r ? r.config_value : null;
        },

        /** SET valor de uma config (upsert) */
        set(key, value) {
          const existing = db.queryOne('SELECT id FROM configuracoes WHERE config_key = ?', [key]);
          if (existing) {
            db.run(
              `UPDATE configuracoes SET config_value = ?, updated_at = NOW() WHERE config_key = ?`,
              [String(value), key]
            );
          } else {
            db.run(
              `INSERT INTO configuracoes (config_key, config_value) VALUES (?,?)`,
              [key, String(value)]
            );
          }
          logAudit('configuracoes', 'Alteração de Configuração', `Parâmetro "${key}" alterado para "${value}"`, 'alto');
        },

        /** Retorna objeto com todas as estatísticas do município */
        getStats() {
          const rows = db.query("SELECT config_key, config_value FROM configuracoes WHERE config_group = 'estatisticas'");
          const stats = {};
          rows.forEach(r => { stats[r.config_key] = r.config_value; });
          // Enriquece com dados em tempo real do banco
          stats.total_users       = PLDB.users.count();
          stats.total_locais      = PLDB.locais.count();
          stats.total_solicitacoes = PLDB.solicitacoes.count();
          stats.pending_count     = PLDB.solicitacoes.countPending();
          stats.total_avaliacoes  = PLDB.avaliacoes.count();
          stats.total_rotas       = PLDB.rotas.count();
          return stats;
        }
      },

      // =======================================================================
      // NOTIFICAÇÕES (MySQL)
      // =======================================================================
      notificacoes: {
        /** SELECT todas as notificações do usuário */
        findByUser(userId) {
          if (!userId) return [];
          return db.query(
            'SELECT n.*, u.name AS sender_name FROM notificacoes n LEFT JOIN usuarios u ON u.id = n.sender_id WHERE n.user_id = ? ORDER BY n.created_at DESC',
            [userId]
          );
        },

        /** SELECT notificações não lidas do usuário */
        findUnread(userId) {
          if (!userId) return [];
          return db.query(
            'SELECT n.*, u.name AS sender_name FROM notificacoes n LEFT JOIN usuarios u ON u.id = n.sender_id WHERE n.user_id = ? AND n.is_read = 0 ORDER BY n.created_at DESC',
            [userId]
          );
        },

        /** Contador de não lidas */
        countUnread(userId) {
          if (!userId) return 0;
          const r = db.queryOne(
            'SELECT COUNT(*) AS total FROM notificacoes WHERE user_id = ? AND is_read = 0',
            [userId]
          );
          return r ? parseInt(r.total, 10) : 0;
        },

        /** Contador total */
        count(userId) {
          if (!userId) return 0;
          const r = db.queryOne(
            'SELECT COUNT(*) AS total FROM notificacoes WHERE user_id = ?',
            [userId]
          );
          return r ? parseInt(r.total, 10) : 0;
        },

        /** SELECT por ID garantindo isolamento de usuário */
        findById(id, userId) {
          if (!id) return null;
          if (userId) {
            return db.queryOne(
              'SELECT n.*, u.name AS sender_name FROM notificacoes n LEFT JOIN usuarios u ON u.id = n.sender_id WHERE n.id = ? AND n.user_id = ?',
              [id, userId]
            );
          }
          return db.queryOne(
            'SELECT n.*, u.name AS sender_name FROM notificacoes n LEFT JOIN usuarios u ON u.id = n.sender_id WHERE n.id = ?',
            [id]
          );
        },

        /** Marca notificação individual como lida */
        markRead(id, userId) {
          if (!id) return false;
          if (userId) {
            db.run(
              'UPDATE notificacoes SET is_read = 1 WHERE id = ? AND user_id = ?',
              [id, userId]
            );
          } else {
            db.run(
              'UPDATE notificacoes SET is_read = 1 WHERE id = ?',
              [id]
            );
          }
          return true;
        },

        /** Marca todas as notificações do usuário como lidas */
        markAllRead(userId) {
          if (!userId) return false;
          db.run(
            'UPDATE notificacoes SET is_read = 1 WHERE user_id = ? AND is_read = 0',
            [userId]
          );
          return true;
        },

        /** Cria nova notificação */
        create(data) {
          if (!data || !data.user_id) return false;
          db.run(
            'INSERT INTO notificacoes (user_id, sender_id, title, body, type, link, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())',
            [
              data.user_id,
              data.sender_id || null,
              data.title || 'Notificação',
              data.body || data.message || '',
              data.type || 'info',
              data.link || null
            ]
          );
          return true;
        },

        /** Exclui notificação garantindo isolamento */
        delete(id, userId) {
          if (!id || !userId) return false;
          db.run(
            'DELETE FROM notificacoes WHERE id = ? AND user_id = ?',
            [id, userId]
          );
          return true;
        }
      }
    };

    window.PLDB = PLDB;
    console.log('[PLDB] Módulo de queries disponível em window.PLDB');
  }

  // Aguarda o banco estar pronto
  if (window.PasseLivreDBReady) {
    window.PasseLivreDBReady.then(buildAPI);
  } else {
    // Se db.js ainda não carregou, aguarda o evento
    document.addEventListener('DOMContentLoaded', () => {
      if (window.PasseLivreDBReady) {
        window.PasseLivreDBReady.then(buildAPI);
      }
    });
  }

})();
