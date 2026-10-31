-- =============================================================================
-- PASSE LIVRE — Migração: município/UF dos locais cadastrados
-- Arquivo: migracao_municipio_sp.sql
-- Aplicação: phpMyAdmin ou CLI no banco EXISTENTE (passe_livre_suzano)
--
-- O que esta migração faz:
--   1. ADICIONA `locais.municipio` e `locais.uf` (ambas NULL por padrão).
--      NULL significa "município não informado". O ponto é justamente esse: um
--      DEFAULT 'Suzano' faria todo estabelecimento novo parecer de Suzano, que é
--      o defeito que se está corrigindo.
--   2. ADICIONA índices para a filtragem por município e para a busca geográfica.
--   3. RECUPERA o município dos registros atuais usando apenas evidências que já
--      existem na linha: o texto de `name`/`address` e, em seguida, as coordenadas
--      dentro do perímetro municipal. Nenhum endereço é reescrito.
--
-- O que esta migração NÃO faz:
--   - Não apaga, não recria e não arquiva nenhum registro de `locais`.
--   - Não altera nome, endereço, lat/lng, `access_status` nem avaliações.
--   - Não inventa município: linha sem evidência fica com municipio NULL e a
--     interface a apresenta como "município não informado".
--
-- Idempotente: pode ser executada mais de uma vez sem efeitos colaterais.
--
-- IMPORTANTE: execute com cliente utf8mb4 (phpMyAdmin já faz isso; na CLI use
-- `mysql --default-character-set=utf8mb4`). SET NAMES abaixo protege os nomes de
-- município com acento ('São Paulo', 'Arujá', 'Poá'), que não casariam com o
-- REGEXP se o cliente negociasse latin1.
-- =============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 1. Colunas novas (NULL = não informado; sem DEFAULT de cidade)
ALTER TABLE locais
  ADD COLUMN IF NOT EXISTS municipio VARCHAR(120) NULL DEFAULT NULL AFTER bairro,
  ADD COLUMN IF NOT EXISTS uf       VARCHAR(2)   NULL DEFAULT NULL AFTER municipio;

-- 2. Índices de apoio à busca por município e à busca geográfica
ALTER TABLE locais
  ADD INDEX IF NOT EXISTS idx_locais_municipio (municipio, uf),
  ADD INDEX IF NOT EXISTS idx_locais_geo (lat, lng);

-- 3a. Município citado no nome ou no endereço da linha. O texto é "amarrado" por
--     separadores para casar o nome inteiro da cidade, não um pedaço dele.
UPDATE locais
SET municipio = CASE
        WHEN CONCAT(' | ', name, ' | ', address, ' | ') LIKE '% | Suzano | %'               THEN 'Suzano'
        WHEN CONCAT(' | ', name, ' | ', address, ' | ') LIKE '% | Mogi das Cruzes | %'      THEN 'Mogi das Cruzes'
        WHEN CONCAT(' | ', name, ' | ', address, ' | ') LIKE '% | Guarulhos | %'            THEN 'Guarulhos'
        WHEN CONCAT(' | ', name, ' | ', address, ' | ') LIKE '% | Arujá | %'                THEN 'Arujá'
        WHEN CONCAT(' | ', name, ' | ', address, ' | ') LIKE '% | Santa Isabel | %'         THEN 'Santa Isabel'
        WHEN CONCAT(' | ', name, ' | ', address, ' | ') LIKE '% | Biritiba-Mirim | %'       THEN 'Biritiba-Mirim'
        WHEN CONCAT(' | ', name, ' | ', address, ' | ') LIKE '% | Itaquaquecetuba | %'      THEN 'Itaquaquecetuba'
        WHEN CONCAT(' | ', name, ' | ', address, ' | ') LIKE '% | Poá | %'                  THEN 'Poá'
        WHEN CONCAT(' | ', name, ' | ', address, ' | ') LIKE '% | Ferraz de Vasconcelos | %' THEN 'Ferraz de Vasconcelos'
        WHEN CONCAT(' | ', name, ' | ', address, ' | ') LIKE '% | São Paulo | %'            THEN 'São Paulo'
        ELSE NULL
    END,
    uf = 'SP'
WHERE municipio IS NULL
  AND CONCAT(' | ', name, ' | ', address, ' | ') REGEXP '\\| (Suzano|Mogi das Cruzes|Guarulhos|Arujá|Santa Isabel|Biritiba-Mirim|Itaquaquecetuba|Poá|Ferraz de Vasconcelos|São Paulo) \\|';

-- 3b. Sem cidade no texto: usa a coordenada, que já está na linha.
--     Caixa do perímetro municipal de Suzano (lat -23.62…-23.42, lng -46.45…-46.25).
--     Fica de fora o que não estiver dentro dela — inclusive bairros de Mogi,
--     cujo centro está em lng ≈ -46.19.
UPDATE locais
SET municipio = 'Suzano', uf = 'SP'
WHERE municipio IS NULL
  AND lat IS NOT NULL AND lng IS NOT NULL
  AND lat BETWEEN -23.62 AND -23.42
  AND lng BETWEEN -46.45 AND -46.25;

-- 4. Verificação sugerida depois de aplicar:
--    SELECT municipio, uf, COUNT(*) AS total FROM locais GROUP BY municipio, uf;
