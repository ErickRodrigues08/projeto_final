/**
 * Passe Livre Suzano — Central de Dados (mock-data.js)
 *
 * INTEGRAÇÃO SQL: Todos os getters agora leem do banco SQL (window.PLDB) quando
 * disponível. Mantém arrays locais como fallback para compatibilidade.
 *
 * Expõe: window.PasseLivreMockData  (API idêntica à versão anterior)
 */

(function () {
  'use strict';

  // =========================================================================
  // FALLBACK — arrays locais (usados se o banco SQL ainda não estiver pronto)
  // =========================================================================
  const DEFAULT_CITIZEN = {
    id: 'USR-2026-001', name: 'João Silva', role: 'Cidadão Conectado',
    email: 'joao.silva@gmail.com', phone: '(11) 98765-4321', city: 'Suzano - SP',
    condition: 'Cadeirante cadastrado', avatar: 'assets/images/avatar.jpg',
    isGoogleConnected: true, pcdCategories: ['Física/motora'],
    savedPreferences: ['cadeirante', 'rampas', 'calcadas_niveladas']
  };

  const DEFAULT_GESTOR = {
    id: 'ADM-2026-007', name: 'Carlos Eduardo', role: 'Administrador Municipal',
    department: 'Secretaria de Transporte e Mobilidade Urbana (SMTU) — Suzano',
    email: 'carlos.eduardo@suzano.sp.gov.br',
    avatar: 'assets/images/avatar-admin.jpg', initials: 'CE'
  };

  const FALLBACK_PLACES = [
    { id: 'LOC-001', name: 'Estação CPTM Suzano (Linha 11-Coral)', category: 'Transporte Público', categoryKey: 'transporte', address: 'Rua Prudente de Moraes, s/n', bairro: 'Centro', accessStatus: 'verified', accessStatusLabel: 'Rota Verificada', accessText: 'Totalmente Acessível', rating: 4.8, reviewsCount: 124, image: 'assets/images/map-background.jpg', lat: -23.5325, lng: -46.3112, features: ['Rampas suaves NBR 9050','Piso podotátil direcional','Sanitários adaptados','Embarque em nível'], lastAudit: '28/08/2026' },
    { id: 'LOC-002', name: 'Terminal de Ônibus Ayrton Senna', category: 'Transporte Público', categoryKey: 'transporte', address: 'Av. Brasil, 500 - Parque Suzano', bairro: 'Parque Suzano', accessStatus: 'verified', accessStatusLabel: 'Rota Verificada', accessText: 'Totalmente Acessível', rating: 4.5, reviewsCount: 89, image: 'assets/images/terminal-ayrton-senna.jpg', lat: -23.5358, lng: -46.3089, features: ['Rampas','Piso tátil','Sinalização sonora','Banheiro adaptado'], lastAudit: '15/08/2026' },
    { id: 'LOC-003', name: 'Suzano Shopping', category: 'Comércio & Serviços', categoryKey: 'comercio', address: 'Rua Sete de Setembro, 555 - Jardim Iraí', bairro: 'Jardim Iraí', accessStatus: 'verified', accessStatusLabel: 'Rota Verificada', accessText: 'Totalmente Acessível', rating: 4.7, reviewsCount: 210, image: 'assets/images/shopping-suzano.jpg', lat: -23.5412, lng: -46.3165, features: ['Rampas','Elevador','Vagas PcD','Banheiro adaptado','Cadeira de empréstimo'], lastAudit: '02/09/2026' },
    { id: 'LOC-004', name: 'Prefeitura Municipal de Suzano', category: 'Órgão Público', categoryKey: 'publico', address: 'Rua Baruel, 501 - Vila Amorim', bairro: 'Vila Amorim', accessStatus: 'verified', accessStatusLabel: 'Rota Verificada', accessText: 'Totalmente Acessível', rating: 4.3, reviewsCount: 45, image: 'assets/images/map-background.jpg', lat: -23.5380, lng: -46.3130, features: ['Rampas','Elevador','Piso tátil','Atendimento prioritário'], lastAudit: '10/07/2026' },
    { id: 'LOC-005', name: 'Parque Municipal Max Feffer', category: 'Parque & Lazer', categoryKey: 'parque', address: 'Av. Senador Roberto Simonsen, 90 - Jardim Imperador', bairro: 'Jd. Imperador', accessStatus: 'verified', accessStatusLabel: 'Rota Verificada', accessText: 'Totalmente Acessível', rating: 4.9, reviewsCount: 340, image: 'assets/images/map-background.jpg', lat: -23.5290, lng: -46.3190, features: ['Pistas niveladas','Banheiro adaptado','Estacionamento PcD','Brinquedos inclusivos'], lastAudit: '20/08/2026' }
  ];

  const FALLBACK_SOLICITACOES = [
    { id: '#1', rawId: '1', protocol: 'SOL-2026-0891', citizen: 'Mariana Souza', avatar: 'assets/images/mariana-souza.jpg', email: 'mariana.souza@email.com', phone: '(11) 98765-4321', pcdCategory: 'Visual', badges: ['Laudo Aprovado','Deficiência Visual'], type: 'semaforo', typeName: 'Semáforo Sonoro', typeIcon: 'assets/icons/traffic-light.svg', title: 'Semáforo Sonoro Inoperante', address: 'Av. Armando Salles, 840', region: 'centro', regionName: 'Região Central', complement: 'Próximo ao Hospital Santa Maria', date: '2026-09-03 08:30', status: 'pending', statusName: 'Pendente de Triagem', priority: 'urgente', priorityName: 'Urgente', description: 'O semáforo sonoro não emite o sinal na travessia de pedestres.', photo: 'assets/images/semaforo-evidencia.jpg' },
    { id: '#2', rawId: '2', protocol: 'SOL-2026-0887', citizen: 'João Silva', avatar: 'assets/images/avatar.jpg', email: 'joao.silva@gmail.com', phone: '(11) 98765-4321', pcdCategory: 'Física/motora', badges: ['Laudo Aprovado','Cadeirante'], type: 'rampa', typeName: 'Rampa de Acesso', typeIcon: 'assets/icons/ramp.svg', title: 'Rampa com Degrau Rompido', address: 'Rua General Francisco Glicério, 450', region: 'centro', regionName: 'Região Central', complement: 'Em frente à farmácia municipal', date: '2026-09-02 14:15', status: 'pending', statusName: 'Pendente de Triagem', priority: 'alta', priorityName: 'Alta', description: 'A rampa de acesso está quebrada com desnível de mais de 8cm.', photo: 'assets/images/rampa-interna.jpg' },
    { id: '#3', rawId: '3', protocol: 'SOL-2026-0882', citizen: 'Lucas Mendes', avatar: 'assets/images/avatar.jpg', email: 'lucas.mendes@mobilidade.org', phone: '(11) 91234-5678', pcdCategory: 'Auditiva', badges: ['Fiscal Cidadão'], type: 'piso_tatil', typeName: 'Piso Tátil', typeIcon: 'assets/icons/tactile-paving.svg', title: 'Piso Tátil Interrompido', address: 'Rua Benjamin Constant, 120', region: 'centro', regionName: 'Região Central', complement: 'Esquina com Rua Campos Salles', date: '2026-08-29 11:00', status: 'analyzing', statusName: 'Em Análise', priority: 'alta', priorityName: 'Alta', description: 'Piso tátil direcional termina bruscamente em um poste metálico.', photo: 'assets/images/map-background.jpg' },
    { id: '#4', rawId: '4', protocol: 'SOL-2026-0879', citizen: 'Roberto Almeida', avatar: 'assets/images/avatar.jpg', email: 'roberto.almeida@gmail.com', phone: '(11) 97654-3210', pcdCategory: 'Mobilidade Reduzida', badges: ['Idoso Conectado'], type: 'calcada', typeName: 'Calçada Inacessível', typeIcon: 'assets/icons/traffic-light.svg', title: 'Buraco e desnível na calçada', address: 'Rua Baruel, 700', region: 'sul', regionName: 'Região Sul', complement: 'Próximo ao posto de saúde', date: '2026-08-28 09:40', status: 'analyzing', statusName: 'Em Análise', priority: 'media', priorityName: 'Média', description: 'Calçada destruída pelas chuvas.', photo: 'assets/images/map-background.jpg' }
  ];

  const FALLBACK_STATS = {
    totalAuditedPoints: 184, conformityRate: '94,2%', activeRamps: 42,
    audioSignals: 28, tactileSidewalkKm: '18,5 km', openIncidents: 24,
    monthlyUsers: '14.280', slaAverageHours: 36
  };

  // =========================================================================
  // HELPERS — converte linha SQL → formato legado do PasseLivreMockData
  // =========================================================================
  function _sqlLocalToLegacy(row) {
    let features = [];
    try { features = JSON.parse(row.features || '[]'); } catch (_) {}
    return {
      id:                row.local_code,
      name:              row.name,
      category:          row.category,
      categoryKey:       row.category_key,
      address:           row.address,
      bairro:            row.bairro,
      accessStatus:      row.access_status,
      accessStatusLabel: row.access_status === 'verified' ? 'Rota Verificada' : row.access_status === 'partial' ? 'Acessibilidade Parcial' : 'Não Verificado',
      accessText:        row.access_text,
      rating:            row.rating,
      reviewsCount:      row.reviews_count,
      image:             row.image,
      lat:               row.lat,
      lng:               row.lng,
      features:          features,
      lastAudit:         row.last_audit,
      avatarColor:       row.avatar_color,
      avatarIcon:        row.avatar_icon,
      incidentsCount:    row.incidents_count
    };
  }

  function _sqlSolToLegacy(row) {
    let badges = [];
    try { badges = JSON.parse(row.badges || '[]'); } catch (_) {}
    const rawId = String(row.id);
    return {
      id:           `#${rawId}`,
      rawId:        rawId,
      protocol:     row.protocol,
      citizen:      row.citizen_name,
      avatar:       row.citizen_avatar,
      email:        row.citizen_email,
      phone:        row.citizen_phone,
      pcdCategory:  row.pcd_category,
      badges:       badges,
      type:         row.type_key,
      typeName:     row.type_name,
      typeIcon:     row.type_icon,
      title:        row.title,
      address:      row.address,
      region:       row.region,
      regionName:   row.region_name,
      complement:   row.complement,
      date:         row.created_at ? row.created_at.slice(0, 16).replace('T', ' ') : '',
      status:       row.status,
      statusName:   row.status_name,
      priority:     row.priority,
      priorityName: row.priority_name,
      description:  row.description,
      photo:        row.photo
    };
  }

  // =========================================================================
  // API PÚBLICA — idêntica à versão anterior + integração SQL
  // =========================================================================
  window.PasseLivreMockData = {
    isMock: false, // agora usa SQL real

    getDefaultCitizen() {
      if (window.PLDB) {
        const u = window.PLDB.users.findByCode('USR-2026-001');
        if (u) return { id: u.user_code, name: u.name, role: u.role, email: u.email, phone: u.phone, city: u.city, avatar: u.avatar, pcdCategories: _parseJSON(u.pcd_categories), savedPreferences: _parseJSON(u.saved_prefs), isGoogleConnected: u.is_google === 1 };
      }
      return Object.assign({}, DEFAULT_CITIZEN);
    },

    getDefaultGestor() {
      if (window.PLDB) {
        const u = window.PLDB.users.findByCode('ADM-2026-007');
        if (u) return { id: u.user_code, name: u.name, role: u.role, department: 'SMTU — Suzano', email: u.email, avatar: u.avatar, initials: u.initials };
      }
      return Object.assign({}, DEFAULT_GESTOR);
    },

    getPlaces(filterKey) {
      if (window.PLDB) {
        try {
          const rows = window.PLDB.locais.findAll(
            filterKey && filterKey !== 'all'
              ? { category_key: filterKey }
              : undefined
          );
          if (rows && rows.length) return rows.map(_sqlLocalToLegacy);
        } catch (_) {}
      }
      if (!filterKey || filterKey === 'all') return [...FALLBACK_PLACES];
      return FALLBACK_PLACES.filter(p => p.categoryKey === filterKey || p.accessStatus === filterKey);
    },

    getPlaceById(id) {
      if (window.PLDB) {
        try {
          const row = window.PLDB.locais.findByCode(id);
          if (row) return _sqlLocalToLegacy(row);
        } catch (_) {}
      }
      return FALLBACK_PLACES.find(p => p.id === id) || null;
    },

    getSolicitacoes(filters) {
      if (window.PLDB) {
        try {
          const rows = window.PLDB.solicitacoes.findAll(filters);
          if (rows && rows.length) return rows.map(_sqlSolToLegacy);
        } catch (_) {}
      }
      return [...FALLBACK_SOLICITACOES];
    },

    getSolicitacaoById(id) {
      if (window.PLDB) {
        try {
          const cleanId = String(id).replace('#', '').trim();
          // Tenta por ID numérico primeiro
          const byId = window.PLDB.solicitacoes.findById(parseInt(cleanId, 10));
          if (byId) return _sqlSolToLegacy(byId);
          // Tenta por protocolo
          const byProto = window.PLDB.solicitacoes.findByProtocol(cleanId);
          if (byProto) return _sqlSolToLegacy(byProto);
        } catch (_) {}
      }
      const cleanId = String(id).replace('#', '').trim();
      return FALLBACK_SOLICITACOES.find(s => s.rawId === cleanId || s.id === `#${cleanId}`) || null;
    },

    getStats() {
      if (window.PLDB) {
        try {
          const s = window.PLDB.configuracoes.getStats();
          return {
            totalAuditedPoints: parseInt(s.total_locais_audit || FALLBACK_STATS.totalAuditedPoints),
            conformityRate:     (s.taxa_conformidade || '94.2') + '%',
            activeRamps:        parseInt(s.rampas_ativas || FALLBACK_STATS.activeRamps),
            audioSignals:       parseInt(s.semaforos_sonoros || FALLBACK_STATS.audioSignals),
            tactileSidewalkKm:  (s.piso_tatil_km || '18.5') + ' km',
            openIncidents:      parseInt(s.pending_count || FALLBACK_STATS.openIncidents),
            monthlyUsers:       s.usuarios_mensais ? parseInt(s.usuarios_mensais).toLocaleString('pt-BR') : FALLBACK_STATS.monthlyUsers,
            slaAverageHours:    36,
            totalUsers:         parseInt(s.total_users || 0),
            totalLocais:        parseInt(s.total_locais || 0),
            totalSolicitacoes:  parseInt(s.total_solicitacoes || 0),
            totalAvaliacoes:    parseInt(s.total_avaliacoes || 0)
          };
        } catch (_) {}
      }
      return Object.assign({}, FALLBACK_STATS);
    }
  };

  function _parseJSON(str, fallback) {
    try { return JSON.parse(str || '[]'); } catch (_) { return fallback || []; }
  }

})();
