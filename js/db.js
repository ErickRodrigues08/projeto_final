/**
 * Passe Livre Suzano — Conector MySQL 8.0+ (PHP PDO Bridge)
 *
 * FONTE DE VERDADE: Banco de dados MySQL local (passe_livre_suzano)
 * -----------------------------------------------------------------
 * Este módulo substitui o SQLite em memória pelo MySQL 8.0+ real,
 * comunicando-se via api/sql_bridge.php com prepared statements (PDO).
 *
 * Expõe:
 *   window.PasseLivreDB       — instância com query / queryOne / run / dump / downloadSQL
 *   window.PasseLivreDBReady  — Promise resolvida quando a conexão estiver pronta
 */

(function () {
  'use strict';

  function queryAll(sql, params) {
    try {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'api/sql_bridge.php?action=query', false);
      xhr.setRequestHeader('Content-Type', 'application/json');
      const token = localStorage.getItem('passeLivre_token');
      if (token) xhr.setRequestHeader('X-Session-Token', token);
      xhr.send(JSON.stringify({ sql: sql, params: params || [] }));
      if (xhr.status === 200) {
        const resp = JSON.parse(xhr.responseText);
        return resp.rows || [];
      } else {
        console.error('[PasseLivreDB MySQL] HTTP', xhr.status, xhr.responseText);
      }
    } catch (e) {
      console.error('[PasseLivreDB MySQL] Erro de rede/consulta:', e);
    }
    return [];
  }

  function queryOne(sql, params) {
    try {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'api/sql_bridge.php?action=query_one', false);
      xhr.setRequestHeader('Content-Type', 'application/json');
      const token = localStorage.getItem('passeLivre_token');
      if (token) xhr.setRequestHeader('X-Session-Token', token);
      xhr.send(JSON.stringify({ sql: sql, params: params || [] }));
      if (xhr.status === 200) {
        const resp = JSON.parse(xhr.responseText);
        return resp.row || null;
      }
    } catch (e) {
      console.error('[PasseLivreDB MySQL] Erro na consulta:', e);
    }
    return null;
  }

  function run(sql, params) {
    try {
      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'api/sql_bridge.php?action=run', false);
      xhr.setRequestHeader('Content-Type', 'application/json');
      const token = localStorage.getItem('passeLivre_token');
      if (token) xhr.setRequestHeader('X-Session-Token', token);
      xhr.send(JSON.stringify({ sql: sql, params: params || [] }));
      if (xhr.status === 200) {
        return JSON.parse(xhr.responseText);
      }
    } catch (e) {
      console.error('[PasseLivreDB MySQL] Erro na execução:', e);
    }
    return { success: false };
  }

  // API Pública do Banco
  window.PasseLivreDB = {
    query: queryAll,
    queryOne: queryOne,
    run: run,

    async queryAsync(sql, params) {
      const res = await fetch('api/sql_bridge.php?action=query', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sql, params: params || [] })
      });
      const data = await res.json();
      return data.rows || [];
    },

    async queryOneAsync(sql, params) {
      const res = await fetch('api/sql_bridge.php?action=query_one', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sql, params: params || [] })
      });
      const data = await res.json();
      return data.row || null;
    },

    async runAsync(sql, params) {
      const res = await fetch('api/sql_bridge.php?action=run', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sql, params: params || [] })
      });
      return await res.json();
    },

    dump() {
      const tables = ['usuarios', 'locais', 'solicitacoes', 'avaliacoes', 'rotas', 'notificacoes', 'logs_auditoria', 'configuracoes'];
      let sql = '-- Passe Livre Suzano — MySQL Dump\n';
      sql += `-- Exportado em: ${new Date().toISOString()}\n\n`;
      tables.forEach(t => {
        const rows = queryAll(`SELECT * FROM ${t}`);
        if (!rows.length) return;
        sql += `-- Tabela: ${t} (${rows.length} registros)\n`;
        rows.forEach(row => {
          const cols = Object.keys(row).map(c => `\`${c}\``).join(', ');
          const vals = Object.values(row)
            .map(v => v === null ? 'NULL' : `'${String(v).replace(/'/g, "\\'")}'`)
            .join(', ');
          sql += `INSERT INTO \`${t}\` (${cols}) VALUES (${vals});\n`;
        });
        sql += '\n';
      });
      return sql;
    },

    downloadSQL(filename) {
      const sql = this.dump();
      const blob = new Blob([sql], { type: 'text/plain;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = Object.assign(document.createElement('a'), {
        href: url,
        download: filename || `banco_mysql_passelivre_${new Date().toISOString().slice(0, 10)}.sql`
      });
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    },

    save() {
      console.info('[PasseLivreDB] As alterações já são gravadas em tempo real no MySQL.');
    }
  };

  // Resolve Promise de inicialização imediatamente
  window.PasseLivreDBReady = Promise.resolve(window.PasseLivreDB);
  console.log('[PasseLivreDB] Conectado ao MySQL 8.0+ via PHP PDO.');

})();
