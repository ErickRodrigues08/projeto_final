-- =============================================================================
-- PASSE LIVRE — Migração: lugares conhecidos de todo o estado de São Paulo
-- Arquivo: migracao_locais_famosos_sp.sql
-- Aplicação: phpMyAdmin ou CLI no banco EXISTENTE (passe_livre_suzano)
--
-- O que faz: ADICIONA 123 registros em `locais`, distribuídos por 17 municípios.
-- Cada INSERT usa um `local_code` próprio (LOC-SP-nnn) e `ON DUPLICATE KEY UPDATE id = id`:
-- reexecutar este arquivo não duplica nem sobrescreve nada.
--
-- Origem dos dados: nome, endereço, bairro, município e coordenada vêm do OpenStreetMap,
-- resolvidos pela integração de geocodificação já existente no sistema (api/geocode.php:
-- mesmo provedor, mesmo User-Agent, viewbox do estado de São Paulo e countrycodes=br).
-- Nenhuma coordenada foi escrita à mão. A referência OSM de cada linha está no comentário
-- acima do respectivo INSERT, junto com a prova do município (campo administrativo do
-- provedor ou geocodificação reversa do mesmo ponto). Lugares sem correspondência no mapa
-- aberto foram descartados — nada aqui tem coordenada aproximada ou inventada.
--
-- O que NÃO faz:
--   - Não altera, arquiva nem apaga registro algum que já exista. As linhas LOC-001 a
--     LOC-008 e as demais cadastradas pela equipe permanecem exatamente como estão.
--   - Não classifica acessibilidade. Toda linha nova nasce `access_status = 'unverified'`
--     e `access_text = 'Não verificado'`: a equipe não vistoriou nenhum desses lugares, e
--     apresentá-los como acessíveis seria uma informação sem sustentação.
--   - Não inventa recurso de acessibilidade: `features` fica NULL e nenhuma linha é criada
--     em `local_recursos`. O cálculo de compatibilidade mostra esses casos como "sem dados".
--   - Não inventa horário, linha de ônibus nem data de vistoria: `opening_hours`,
--     `bus_lines` e `last_audit` vão como NULL (o DEFAULT da tabela diria "Segunda a
--     Sexta: 08:00 - 18:00" e citaria linhas municipais de Suzano em qualquer cidade).
--   - Não inventa avaliação: `rating`, `reviews_count` e `useful_count` ficam em 0.
--
-- Cobertura por município:
--   Aparecida (1)
--   Barueri (1)
--   Campinas (10)
--   Campos do Jordão (1)
--   Guarujá (2)
--   Guarulhos (6)
--   Jundiaí (2)
--   Mogi das Cruzes (3)
--   Piracicaba (1)
--   Ribeirão Preto (6)
--   Santo André (3)
--   Santos (11)
--   Sorocaba (4)
--   São Bernardo do Campo (3)
--   São Carlos (1)
--   São José dos Campos (6)
--   São Paulo (62)
--
-- Reversão (remove apenas o que esta migração criou, preservando o resto da tabela):
--   DELETE lr FROM local_recursos lr JOIN locais l ON l.id = lr.local_id WHERE l.local_code LIKE 'LOC-SP-%';
--   DELETE FROM locais WHERE local_code LIKE 'LOC-SP-%';
-- =============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Aparecida — fonte: OpenStreetMap PLACE_OF_WORSHIP/3089108; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-001',
    'Basílica de Nossa Senhora Aparecida',
    'Outros',
    'outro',
    'Avenida Júlio Prestes, Ponte Alta, Aparecida - SP, 12570-000',
    'Ponte Alta',
    'Aparecida',
    'SP',
    'unverified',
    'Não verificado',
    -22.850927,
    -45.233835,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Barueri — fonte: OpenStreetMap MALL/164107613; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-002',
    'Shopping Tamboré',
    'Comércio & Serviços',
    'comercio',
    'Avenida Piracema, 669, Alphaville, Barueri - SP, 06460-030',
    'Alphaville',
    'Barueri',
    'SP',
    'unverified',
    'Não verificado',
    -23.504694,
    -46.834492,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Campinas — fonte: OpenStreetMap GOVERNMENT/11586920732; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-003',
    'Administração Regional 02 Prefeitura Campinas',
    'Órgão Público',
    'publico',
    'Rua Doutor Constantino Raffi, Vila Brandina, Campinas - SP, 13101-501',
    'Vila Brandina',
    'Campinas',
    'SP',
    'unverified',
    'Não verificado',
    -22.905970,
    -47.020327,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Campinas — fonte: OpenStreetMap PLACE_OF_WORSHIP/151427298; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-004',
    'Catedral Metropolitana de Campinas',
    'Outros',
    'outro',
    'Rua José Paulino, Centro, Campinas - SP, 13010-151',
    'Centro',
    'Campinas',
    'SP',
    'unverified',
    'Não verificado',
    -22.906009,
    -47.060391,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Campinas — fonte: OpenStreetMap STADIUM/794294454; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-005',
    'Estádio Brinco de Ouro da Princesa',
    'Outros',
    'outro',
    'Avenida Imperatriz Dona Tereza Cristina, 11, Nova Campinas, Campinas - SP, 13100-200',
    'Nova Campinas',
    'Campinas',
    'SP',
    'unverified',
    'Não verificado',
    -22.909573,
    -47.043370,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Campinas — fonte: OpenStreetMap MALL/89693149; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-006',
    'Iguatemi Campinas',
    'Comércio & Serviços',
    'comercio',
    'Avenida Iguatemi, 777, Vila Brandina, Campinas - SP, 13092-540',
    'Vila Brandina',
    'Campinas',
    'SP',
    'unverified',
    'Não verificado',
    -22.892402,
    -47.027209,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Campinas — fonte: OpenStreetMap MALL/5268738; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-007',
    'Parque Dom Pedro Shopping',
    'Comércio & Serviços',
    'comercio',
    'Avenida Guilherme Campos, 500, Barão Geraldo, Campinas - SP, 13087-901',
    'Barão Geraldo',
    'Campinas',
    'SP',
    'unverified',
    'Não verificado',
    -22.847949,
    -47.063686,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Campinas — fonte: OpenStreetMap PROTECTED_AREA/17146408; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-008',
    'Parque Ecológico Monsenhor Emílio José Salim',
    'Parque & Lazer',
    'parque',
    'Jardim Andorinhas, Campinas - SP',
    'Jardim Andorinhas',
    'Campinas',
    'SP',
    'unverified',
    'Não verificado',
    -22.909589,
    -47.018831,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Campinas — fonte: OpenStreetMap PARK/13556081; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-009',
    'Parque Portugal',
    'Parque & Lazer',
    'parque',
    'Taquaral, Campinas - SP, 13083-900',
    'Taquaral',
    'Campinas',
    'SP',
    'unverified',
    'Não verificado',
    -22.875641,
    -47.054964,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Campinas — fonte: OpenStreetMap THEATRE/262295329; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-010',
    'Teatro Municipal "José de Castro Mendes"',
    'Outros',
    'outro',
    'Rua Conselheiro Gomide, Centro, Campinas - SP, 13035-270',
    'Centro',
    'Campinas',
    'SP',
    'unverified',
    'Não verificado',
    -22.911013,
    -47.065321,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Campinas — fonte: OpenStreetMap BUS_STATION/151846469; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-011',
    'Terminal Rodoviário de Campinas - Ramos de Azevedo',
    'Transporte Público',
    'transporte',
    'Avenida General Euclides de Figueiredo, Botafogo, Campinas - SP, 13070-052',
    'Botafogo',
    'Campinas',
    'SP',
    'unverified',
    'Não verificado',
    -22.905892,
    -47.072567,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/routes.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Campinas — fonte: OpenStreetMap UNIVERSITY/14349862; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-012',
    'Universidade Estadual de Campinas',
    'Educação',
    'educacao',
    'Rua Américo de Campos, Cidade Universitária, Campinas - SP, 13083-855',
    'Cidade Universitária',
    'Campinas',
    'SP',
    'unverified',
    'Não verificado',
    -22.822656,
    -47.062796,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Campos do Jordão — fonte: OpenStreetMap HELIPAD/563180740; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-013',
    'Palácio Boa Vista',
    'Outros',
    'outro',
    'Avenida Adhemar de Barros, Alto da Boa Vista, Campos do Jordão - SP, 12472-800',
    'Alto da Boa Vista',
    'Campos do Jordão',
    'SP',
    'unverified',
    'Não verificado',
    -22.734990,
    -45.606766,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Guarujá — fonte: OpenStreetMap BEACH/316680493; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-014',
    'Praia das Astúrias',
    'Parque & Lazer',
    'parque',
    'Pitangueiras, Guarujá - SP',
    'Pitangueiras',
    'Guarujá',
    'SP',
    'unverified',
    'Não verificado',
    -24.007509,
    -46.268581,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Guarujá — fonte: OpenStreetMap BEACH/316680488; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-015',
    'Praia do Tombo',
    'Parque & Lazer',
    'parque',
    'Pitangueiras, Guarujá - SP',
    'Pitangueiras',
    'Guarujá',
    'SP',
    'unverified',
    'Não verificado',
    -24.014368,
    -46.273278,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Guarulhos — fonte: OpenStreetMap AERODROME/5141542; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-016',
    'Aeroporto Internacional Governador André Franco Montoro',
    'Transporte Público',
    'transporte',
    'Rua Particular, Presidente Dutra, Guarulhos - SP, 07173-141',
    'Presidente Dutra',
    'Guarulhos',
    'SP',
    'unverified',
    'Não verificado',
    -23.437859,
    -46.480556,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/routes.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Guarulhos — fonte: OpenStreetMap WOOD/406341632; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-017',
    'Bosque Maia',
    'Parque & Lazer',
    'parque',
    'Maia, Guarulhos - SP',
    'Maia',
    'Guarulhos',
    'SP',
    'unverified',
    'Não verificado',
    -23.458576,
    -46.528651,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Guarulhos — fonte: OpenStreetMap PLACE_OF_WORSHIP/516916673; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-018',
    'Catedral Nossa Senhora da Conceição',
    'Outros',
    'outro',
    'Praça Tereza Cristina, 1, Centro, Guarulhos - SP, 07023-070',
    'Centro',
    'Guarulhos',
    'SP',
    'unverified',
    'Não verificado',
    -23.470262,
    -46.531246,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Guarulhos — fonte: OpenStreetMap TOWNHALL/531495050; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-019',
    'Prefeitura Municipal de Guarulhos',
    'Órgão Público',
    'publico',
    'Avenida Bom Clima, Bom Clima, Guarulhos - SP, 07196-000',
    'Bom Clima',
    'Guarulhos',
    'SP',
    'unverified',
    'Não verificado',
    -23.455360,
    -46.517575,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Guarulhos — fonte: OpenStreetMap PARKING/300969542; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-020',
    'Shopping Internacional',
    'Comércio & Serviços',
    'comercio',
    'Rua José Sarraceni, Itapegica, Guarulhos - SP, 07034-911',
    'Itapegica',
    'Guarulhos',
    'SP',
    'unverified',
    'Não verificado',
    -23.487064,
    -46.547555,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Guarulhos — fonte: OpenStreetMap BUS_STATION/1274218856; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-021',
    'Terminal Rodoviário de Guarulhos',
    'Transporte Público',
    'transporte',
    'Alameda dos Lírios, CECAP, Guarulhos - SP, 07190-012',
    'CECAP',
    'Guarulhos',
    'SP',
    'unverified',
    'Não verificado',
    -23.448497,
    -46.496495,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/routes.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Jundiaí — fonte: OpenStreetMap PLACE_OF_WORSHIP/860670079; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-022',
    'Catedral Nossa Senhora do Desterro',
    'Outros',
    'outro',
    'Rua Barão de Jundiaí, Vila Pacheco, Jundiaí - SP, 13201-060',
    'Vila Pacheco',
    'Jundiaí',
    'SP',
    'unverified',
    'Não verificado',
    -23.189181,
    -46.884218,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Jundiaí — fonte: OpenStreetMap PARK/675997690; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-023',
    'Parque da Represa',
    'Parque & Lazer',
    'parque',
    'Torres de São José, Jundiaí - SP',
    'Torres de São José',
    'Jundiaí',
    'SP',
    'unverified',
    'Não verificado',
    -23.159571,
    -46.912184,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Mogi das Cruzes — fonte: OpenStreetMap MALL/234158865; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-024',
    'Mogi Shopping',
    'Comércio & Serviços',
    'comercio',
    'Avenida Vereador Narciso Yague Guimarães, 1001, Centro Cívico, Mogi das Cruzes - SP, 08780-560',
    'Centro Cívico',
    'Mogi das Cruzes',
    'SP',
    'unverified',
    'Não verificado',
    -23.515775,
    -46.179687,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Mogi das Cruzes — fonte: OpenStreetMap TOWNHALL/555992340; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-025',
    'Prefeitura Municipal de Mogi das Cruzes',
    'Órgão Público',
    'publico',
    'Avenida Vereador Narciso Yague Guimarães, 277, Centro Cívico, Mogi das Cruzes - SP, 08780-900',
    'Centro Cívico',
    'Mogi das Cruzes',
    'SP',
    'unverified',
    'Não verificado',
    -23.519142,
    -46.185504,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Mogi das Cruzes — fonte: OpenStreetMap UNIVERSITY/323846216; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-026',
    'UMC - Universidade de Mogi das Cruzes',
    'Educação',
    'educacao',
    'Avenida Vereador Narciso Yague Guimarães, 200, Centro Cívico, Mogi das Cruzes - SP, 08780-911',
    'Centro Cívico',
    'Mogi das Cruzes',
    'SP',
    'unverified',
    'Não verificado',
    -23.516890,
    -46.182470,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Piracicaba — fonte: OpenStreetMap PARK/543872043; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-027',
    'Engenho Central',
    'Outros',
    'outro',
    'Parque da Rua do Porto, Piracicaba - SP, 13405-123',
    'Parque da Rua do Porto',
    'Piracicaba',
    'SP',
    'unverified',
    'Não verificado',
    -22.720352,
    -47.656775,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Ribeirão Preto — fonte: OpenStreetMap STADIUM/206835061; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-028',
    'Arena Eurobike',
    'Outros',
    'outro',
    'Avenida Sebastião Venturi, Santa Cruz, Ribeirão Preto - SP, 14096-070',
    'Santa Cruz',
    'Ribeirão Preto',
    'SP',
    'unverified',
    'Não verificado',
    -21.202392,
    -47.790141,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Ribeirão Preto — fonte: OpenStreetMap PLACE_OF_WORSHIP/423450968; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-029',
    'Catedral Metropolitana de São Sebastião',
    'Outros',
    'outro',
    'Rua Florêncio de Abreu, Centro, Ribeirão Preto - SP, 14015-068',
    'Centro',
    'Ribeirão Preto',
    'SP',
    'unverified',
    'Não verificado',
    -21.178191,
    -47.810863,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Ribeirão Preto — fonte: OpenStreetMap MALL/220945813; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-030',
    'Shopping Iguatemi',
    'Comércio & Serviços',
    'comercio',
    'Avenida Doutor Ângelo Genaro Gallo, Vila do Golf, Ribeirão Preto - SP, 14027-020',
    'Vila do Golf',
    'Ribeirão Preto',
    'SP',
    'unverified',
    'Não verificado',
    -21.225621,
    -47.835263,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Ribeirão Preto — fonte: OpenStreetMap BUS_STATION/397181155; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-031',
    'Terminal Rodoviário',
    'Transporte Público',
    'transporte',
    'Rua Augusto Severo, Centro, Ribeirão Preto - SP, 14050-350',
    'Centro',
    'Ribeirão Preto',
    'SP',
    'unverified',
    'Não verificado',
    -21.173843,
    -47.814458,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/routes.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Ribeirão Preto — fonte: OpenStreetMap THEATRE/438532846; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-032',
    'Theatro Pedro II',
    'Outros',
    'outro',
    'Rua Álvares Cabral, 370, Centro, Ribeirão Preto - SP, 14010-080',
    'Centro',
    'Ribeirão Preto',
    'SP',
    'unverified',
    'Não verificado',
    -21.174361,
    -47.809806,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Ribeirão Preto — fonte: OpenStreetMap COLLEGE/8799164; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-033',
    'USP Ribeirão Preto',
    'Educação',
    'educacao',
    'Avenida Bandeirantes, 3900, Jardim Recreio, Ribeirão Preto - SP, 14040-900',
    'Jardim Recreio',
    'Ribeirão Preto',
    'SP',
    'unverified',
    'Não verificado',
    -21.159001,
    -47.856698,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Santo André — fonte: OpenStreetMap STADIUM/350999502; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-034',
    'Estádio Municipal Bruno José Daniel',
    'Outros',
    'outro',
    'Rua 24 de Maio, Vila América, Santo André - SP, 09110-150',
    'Vila América',
    'Santo André',
    'SP',
    'unverified',
    'Não verificado',
    -23.669567,
    -46.509319,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Santo André — fonte: OpenStreetMap HERITAGE/730049763; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-035',
    'Paço Municipal de Santo André',
    'Órgão Público',
    'publico',
    'Viaduto Acisa, Centro, Santo André - SP, 09015-000',
    'Centro',
    'Santo André',
    'SP',
    'unverified',
    'Não verificado',
    -23.657384,
    -46.532644,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Santo André — fonte: OpenStreetMap THEATRE/405474235; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-036',
    'Teatro Municipal Maestro Flavio Florence',
    'Outros',
    'outro',
    'Praça IV Centenário, Centro, Santo André - SP, 09015-080',
    'Centro',
    'Santo André',
    'SP',
    'unverified',
    'Não verificado',
    -23.656535,
    -46.532288,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Santos — fonte: OpenStreetMap AQUARIUM/305498450; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-037',
    'Aquário Municipal de Santos',
    'Outros',
    'outro',
    'Praça Vereador Luiz La Scala, Ponta da Praia, Santos - SP, 11030-600',
    'Ponta da Praia',
    'Santos',
    'SP',
    'unverified',
    'Não verificado',
    -23.986274,
    -46.308375,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Santos — fonte: OpenStreetMap FUNICULAR/48460604; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-038',
    'Bondinho do Monte Serrat',
    'Outros',
    'outro',
    'Entrada para o Bondinho do Monte Serrat, Jabaquara, Santos - SP, 11013-210',
    'Jabaquara',
    'Santos',
    'SP',
    'unverified',
    'Não verificado',
    -23.938361,
    -46.329748,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Santos — fonte: OpenStreetMap PLACE_OF_WORSHIP/1365381126; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-039',
    'Catedral de Santos',
    'Outros',
    'outro',
    'Rua Amador Bueno, Centro, Santos - SP, 11013-151',
    'Centro',
    'Santos',
    'SP',
    'unverified',
    'Não verificado',
    -23.936899,
    -46.324100,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Santos — fonte: OpenStreetMap UNIVERSITY/2817059508; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-040',
    'Chancelaria Universidade Santa Cecília',
    'Educação',
    'educacao',
    'Rua Doutor Oswaldo Cruz, Boqueirão, Santos - SP, 11045-003',
    'Boqueirão',
    'Santos',
    'SP',
    'unverified',
    'Não verificado',
    -23.965694,
    -46.321985,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Santos — fonte: OpenStreetMap STADIUM/1013950391; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-041',
    'Estádio Urbano Caldeira',
    'Outros',
    'outro',
    'Rua Princesa Isabel, Vila Belmiro, Santos - SP, 11075-501',
    'Vila Belmiro',
    'Santos',
    'SP',
    'unverified',
    'Não verificado',
    -23.951116,
    -46.338991,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Santos — fonte: OpenStreetMap GARDEN/108236065; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-042',
    'Jardim Botânico Municipal de Santos Chico Mendes',
    'Parque & Lazer',
    'parque',
    'Chico de Paula, Santos - SP',
    'Chico de Paula',
    'Santos',
    'SP',
    'unverified',
    'Não verificado',
    -23.937164,
    -46.372114,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Santos — fonte: OpenStreetMap MUSEUM/235153134; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-043',
    'Museu do Café',
    'Outros',
    'outro',
    'Rua Quinze de Novembro, 95, Centro, Santos - SP, 11010-151',
    'Centro',
    'Santos',
    'SP',
    'unverified',
    'Não verificado',
    -23.932232,
    -46.330052,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Santos — fonte: OpenStreetMap ZOO/5420291; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-044',
    'Parque Zoobotânico Orquidário Municipal de Santos',
    'Parque & Lazer',
    'parque',
    'Praça Washington, Pompéia, Santos - SP, 11065-600',
    'Pompéia',
    'Santos',
    'SP',
    'unverified',
    'Não verificado',
    -23.965891,
    -46.349648,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Santos — fonte: OpenStreetMap BEACH/5353928; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-045',
    'Praia do Gonzaga',
    'Parque & Lazer',
    'parque',
    'Gonzaga, Santos - SP',
    'Gonzaga',
    'Santos',
    'SP',
    'unverified',
    'Não verificado',
    -23.970543,
    -46.335465,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Santos — fonte: OpenStreetMap TOWNHALL/213509626; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-046',
    'Prefeitura de Santos',
    'Órgão Público',
    'publico',
    'Rua Cidade de Toledo, Centro, Santos - SP, 11010-040',
    'Centro',
    'Santos',
    'SP',
    'unverified',
    'Não verificado',
    -23.933612,
    -46.328057,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Santos — fonte: OpenStreetMap STATION/13743115215; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-047',
    'Valongo',
    'Transporte Público',
    'transporte',
    'Rua Visconde de Embaré, Centro, Santos - SP, 11082-460',
    'Centro',
    'Santos',
    'SP',
    'unverified',
    'Não verificado',
    -23.934681,
    -46.334071,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/routes.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Sorocaba — fonte: OpenStreetMap MALL/11518717; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-048',
    'Iguatemi Esplanada',
    'Comércio & Serviços',
    'comercio',
    'Avenida José da Silva Moura, 401, Jardim Maria Jose, Sorocaba - SP, 18110-698',
    'Jardim Maria Jose',
    'Sorocaba',
    'SP',
    'unverified',
    'Não verificado',
    -23.534395,
    -47.462948,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Sorocaba — fonte: OpenStreetMap TOWNHALL/12350014; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-049',
    'Palácio dos Tropeiros',
    'Órgão Público',
    'publico',
    'Avenida Engenheiro Carlos Reinaldo Mendes, 3041, Alto da Boa Vista, Sorocaba - SP, 18013-280',
    'Alto da Boa Vista',
    'Sorocaba',
    'SP',
    'unverified',
    'Não verificado',
    -23.478540,
    -47.424109,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Sorocaba — fonte: OpenStreetMap PARK/48448685; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-050',
    'Parque das Águas do Abaeté Maria Barbosa Silva',
    'Parque & Lazer',
    'parque',
    'Jardim das Acácias (proposto), Sorocaba - SP',
    'Jardim das Acácias (proposto)',
    'Sorocaba',
    'SP',
    'unverified',
    'Não verificado',
    -23.470175,
    -47.447245,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- Sorocaba — fonte: OpenStreetMap THEATRE/393069044; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-051',
    'Teatro Municipal Teotonio Vilela',
    'Outros',
    'outro',
    'Pista de caminhada Odilon Araújo, Boa Vista, Sorocaba - SP, 18013-550',
    'Boa Vista',
    'Sorocaba',
    'SP',
    'unverified',
    'Não verificado',
    -23.477026,
    -47.423046,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Bernardo do Campo — fonte: OpenStreetMap STADIUM/274473110; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-052',
    'Estádio 1º de Maio',
    'Outros',
    'outro',
    'Rua Olavo Bilac, 240, Centro, São Bernardo do Campo - SP, 09725-570',
    'Centro',
    'São Bernardo do Campo',
    'SP',
    'unverified',
    'Não verificado',
    -23.699834,
    -46.556356,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Bernardo do Campo — fonte: OpenStreetMap PARK/13098479162; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-053',
    'Paço Municipal',
    'Órgão Público',
    'publico',
    ', 50, Centro, São Bernardo do Campo - SP, 09760-000',
    'Centro',
    'São Bernardo do Campo',
    'SP',
    'unverified',
    'Não verificado',
    -23.696372,
    -46.551498,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Bernardo do Campo — fonte: OpenStreetMap MALL/268453699; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-054',
    'Shopping Metrópole',
    'Comércio & Serviços',
    'comercio',
    'Praça Samuel Sabatini, 200, Centro, São Bernardo do Campo - SP, 09751-040',
    'Centro',
    'São Bernardo do Campo',
    'SP',
    'unverified',
    'Não verificado',
    -23.692685,
    -46.550333,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Carlos — fonte: OpenStreetMap UNIVERSITY/8117245; município por campo municipal do provedor, wheelchair=limited
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-055',
    'Universidade Federal de São Carlos',
    'Educação',
    'educacao',
    'Rodovia Washington Luís, Jardim Santa Helena, São Carlos - SP, 13565-905',
    'Jardim Santa Helena',
    'São Carlos',
    'SP',
    'unverified',
    'Não verificado',
    -21.983442,
    -47.880664,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São José dos Campos — fonte: OpenStreetMap PLACE_OF_WORSHIP/523821813; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-056',
    'Catedral de São Dimas',
    'Outros',
    'outro',
    'Rua Ezra, 01, Jardim São Dimas, São José dos Campos - SP, 12245-440',
    'Jardim São Dimas',
    'São José dos Campos',
    'SP',
    'unverified',
    'Não verificado',
    -23.198505,
    -45.888177,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São José dos Campos — fonte: OpenStreetMap PARK/541801799; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-057',
    'Parque Vicentina Aranha',
    'Parque & Lazer',
    'parque',
    ', 302, Vila Adyana, São José dos Campos - SP, 12243-750',
    'Vila Adyana',
    'São José dos Campos',
    'SP',
    'unverified',
    'Não verificado',
    -23.198256,
    -45.895341,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São José dos Campos — fonte: OpenStreetMap PARK/16145599; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-058',
    'Parque da Cidade Roberto Burle Marx',
    'Parque & Lazer',
    'parque',
    ', 100, Vila Terezinha, São José dos Campos - SP',
    'Vila Terezinha',
    'São José dos Campos',
    'SP',
    'unverified',
    'Não verificado',
    -23.164767,
    -45.885294,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São José dos Campos — fonte: OpenStreetMap TOWNHALL/238081362; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-059',
    'Prefeitura Municipal de São José dos Campos',
    'Órgão Público',
    'publico',
    'Rua José de Alencar, 123, Monte Castelo, São José dos Campos - SP, 12209-904',
    'Monte Castelo',
    'São José dos Campos',
    'SP',
    'unverified',
    'Não verificado',
    -23.184845,
    -45.878297,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São José dos Campos — fonte: OpenStreetMap TAXI/2222836350; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-060',
    'Shopping Colinas',
    'Comércio & Serviços',
    'comercio',
    'Avenida Doutor Eduardo Cury, Jardim Colinas, São José dos Campos - SP, 12242-001',
    'Jardim Colinas',
    'São José dos Campos',
    'SP',
    'unverified',
    'Não verificado',
    -23.205111,
    -45.907638,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São José dos Campos — fonte: OpenStreetMap BUS_STATION/217887896; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-061',
    'Terminal Rodoviário de São José dos Campos - Frederico Ozanan',
    'Transporte Público',
    'transporte',
    'Rua Itororó, Vila Piratininga, São José dos Campos - SP, 12223-110',
    'Vila Piratininga',
    'São José dos Campos',
    'SP',
    'unverified',
    'Não verificado',
    -23.193323,
    -45.877965,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/routes.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap STATION/698751459; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-062',
    'Aeroporto de Congonhas',
    'Transporte Público',
    'transporte',
    'Avenida Washington Luís, 6401, Parque Jabaquara, São Paulo - SP, 04615-050',
    'Parque Jabaquara',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.626926,
    -46.661873,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/routes.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap SPORTS_CENTRE/2560355; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-063',
    'Autódromo de Interlagos',
    'Outros',
    'outro',
    'Avenida Senador Teotônio Vilela, 261, Interlagos, São Paulo - SP, 04801-010',
    'Interlagos',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.701819,
    -46.696924,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap PRIMARY/230783567; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-064',
    'Avenida Paulista',
    'Vias Públicas',
    'via',
    'Avenida Paulista, Consolação, São Paulo - SP, 01414-000',
    'Consolação',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.556860,
    -46.661412,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'red',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap LIBRARY/48661210; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-065',
    'Biblioteca Mário de Andrade',
    'Educação',
    'educacao',
    'Rua da Consolação, 94, República, São Paulo - SP, 01302-000',
    'República',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.547390,
    -46.642105,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap LIBRARY/226745315; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-066',
    'Biblioteca de São Paulo',
    'Educação',
    'educacao',
    'Avenida Cruzeiro do Sul, 2630, Santana, São Paulo - SP, 02030-100',
    'Santana',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.507431,
    -46.623554,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap MUSEUM/75604816; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-067',
    'Catavento Cultural e Educacional',
    'Outros',
    'outro',
    'Avenida Mercúrio, Glicério, São Paulo - SP, 03007-000',
    'Glicério',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.544107,
    -46.627883,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap PLACE_OF_WORSHIP/8093737; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-068',
    'Catedral Metropolitana de São Paulo',
    'Outros',
    'outro',
    'Praça da Sé, Glicério, São Paulo - SP, 01001-000',
    'Glicério',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.551269,
    -46.634371,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap GRASSLAND/75806696; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-069',
    'Centro Cultural São Paulo',
    'Outros',
    'outro',
    ', 1000, Paraíso, São Paulo - SP, 01504-000',
    'Paraíso',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.570642,
    -46.640198,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap ATTRACTION/191607149; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-070',
    'Edifício Altino Arantes',
    'Outros',
    'outro',
    'Rua João Brícola, 24, Glicério, São Paulo - SP, 01014-900',
    'Glicério',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.545776,
    -46.634099,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap ATTRACTION/251211619; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-071',
    'Edifício Itália',
    'Outros',
    'outro',
    'Avenida Ipiranga, República, São Paulo - SP, 01043-000',
    'República',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.545530,
    -46.643577,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap BUILDING/191607150; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-072',
    'Edifício Martinelli',
    'Outros',
    'outro',
    'Avenida São João, 35, Glicério, São Paulo - SP, 01011-100',
    'Glicério',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.545524,
    -46.635237,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap RESTAURANT/4990243751; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-073',
    'Estação Consolação',
    'Transporte Público',
    'transporte',
    'Rua Antônio Carlos, 656, Consolação, São Paulo - SP, 01309-010',
    'Consolação',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.554308,
    -46.660365,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/routes.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap MUSEUM/191591077; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-074',
    'Estação Pinacoteca',
    'Outros',
    'outro',
    'Largo General Osório, 66, Santa Ifigênia, São Paulo - SP, 01213-010',
    'Santa Ifigênia',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.534823,
    -46.638789,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap STATION/8105832; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-075',
    'Estação da Luz',
    'Transporte Público',
    'transporte',
    'Praça da Luz, 1, Bairro da Luz, São Paulo - SP, 01120-010',
    'Bairro da Luz',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.535116,
    -46.635391,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/routes.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap HOSPITAL/51387571; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-076',
    'Hospital das Clínicas',
    'Saúde & Emergência',
    'saude',
    'Avenida Doutor Eneas Carvalho de Aguiar, 225, Cerqueira César, São Paulo - SP, 05403-000',
    'Cerqueira César',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.557032,
    -46.670026,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'red',
    'assets/icons/support.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap PLACE_OF_WORSHIP/205804028; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-077',
    'Igreja do Carmo',
    'Outros',
    'outro',
    'Rua do Carmo, Glicério, São Paulo - SP, 01019-020',
    'Glicério',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.550598,
    -46.631078,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap MALL/96124441; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-078',
    'Iguatemi São Paulo',
    'Comércio & Serviços',
    'comercio',
    'Avenida Brigadeiro Faria Lima, 2232, Jardim Europa, São Paulo - SP, 01489-900',
    'Jardim Europa',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.577199,
    -46.688049,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap RESEARCH_INSTITUTE/74924310; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-079',
    'Instituto Butantan',
    'Outros',
    'outro',
    'Avenida Vital Brasil, 1500, Caxingui, São Paulo - SP, 05503-000',
    'Caxingui',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.566640,
    -46.719995,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap ARTS_CENTRE/4470687916; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-080',
    'Instituto Tomie Ohtake',
    'Outros',
    'outro',
    'Rua Coropé, 88, Vila Madalena, São Paulo - SP, 05426-010',
    'Vila Madalena',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.561130,
    -46.694140,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap CAFE/10102653095; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-081',
    'Japan House',
    'Outros',
    'outro',
    'Avenida Paulista, Morro dos Ingleses, São Paulo - SP, 01327-902',
    'Morro dos Ingleses',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.570664,
    -46.644608,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap STATION/5369277070; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-082',
    'Japão - Liberdade',
    'Outros',
    'outro',
    'Avenida da Liberdade, Glicério, São Paulo - SP, 01503-000',
    'Glicério',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.555229,
    -46.635862,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap GARDEN/5349780; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-083',
    'Jardim Botânico de São Paulo',
    'Parque & Lazer',
    'parque',
    ', 3031, Cursino, São Paulo - SP, 04301-905',
    'Cursino',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.641497,
    -46.625662,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap ZOO/75354058; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-084',
    'Jardim Zoológico de São Paulo',
    'Parque & Lazer',
    'parque',
    'Avenida Miguel Estefno, 4241, Cursino, São Paulo - SP, 04301-905',
    'Cursino',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.652689,
    -46.616391,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap STATION/4985618204; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-085',
    'Júlio Prestes',
    'Transporte Público',
    'transporte',
    'Alameda Cleveland, Campos Elísios, São Paulo - SP, 01218-000',
    'Campos Elísios',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.533217,
    -46.640565,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/routes.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap MUSEUM/4466370; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-086',
    'Memorial da América Latina',
    'Outros',
    'outro',
    'Avenida Auro Soares de Moura Andrade, 664, Barra Funda, São Paulo - SP, 01156-001',
    'Barra Funda',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.527541,
    -46.665440,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap STADIUM/666148829; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-087',
    'Mercado Livre Arena Pacaembu',
    'Outros',
    'outro',
    'Praça Charles Miller, Pacaembu, São Paulo - SP, 01234-010',
    'Pacaembu',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.548564,
    -46.665352,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap GREENGROCER/4602996; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-088',
    'Mercado Municipal de São Paulo',
    'Comércio & Serviços',
    'comercio',
    'Rua da Cantareira, 306, Glicério, São Paulo - SP, 01024-900',
    'Glicério',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.541733,
    -46.629524,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap RECREATION_GROUND/1068372310; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-089',
    'Monumento às Bandeiras',
    'Outros',
    'outro',
    'Moema, São Paulo - SP',
    'Moema',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.580052,
    -46.660687,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap STADIUM/715674760; município por campo municipal do provedor, wheelchair=limited
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-090',
    'MorumBIS',
    'Outros',
    'outro',
    'Praça Roberto Gomes Pedrosa, 1, Morumbi, São Paulo - SP, 05653-070',
    'Morumbi',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.600102,
    -46.720126,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap MONASTERY/4611189; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-091',
    'Mosteiro de São Bento',
    'Outros',
    'outro',
    'Largo São Bento, Glicério, São Paulo - SP, 01029-010',
    'Glicério',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.543433,
    -46.634129,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap BICYCLE_RENTAL/5764635192; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-092',
    'Mube',
    'Outros',
    'outro',
    'Rua Alemanha, Jardim Europa, São Paulo - SP, 01446-020',
    'Jardim Europa',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.573610,
    -46.677039,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap MUSEUM/166784137; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-093',
    'Museu Paulista',
    'Outros',
    'outro',
    'Avenida Nazaré, Ipiranga, São Paulo - SP, 04218-970',
    'Ipiranga',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.585581,
    -46.609716,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap MUSEUM/191591082; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-094',
    'Museu da Língua Portuguesa',
    'Outros',
    'outro',
    'Praça da Luz, Bairro da Luz, São Paulo - SP, 01120-010',
    'Bairro da Luz',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.534966,
    -46.635099,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap MUSEUM/2812210894; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-095',
    'Museu de Arte Moderna de São Paulo',
    'Outros',
    'outro',
    'Alameda do Café, Moema, São Paulo - SP, 04094-000',
    'Moema',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.588016,
    -46.655484,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap MUSEUM/6711205; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-096',
    'Museu de Arte Sacra de São Paulo',
    'Outros',
    'outro',
    'Avenida Tiradentes, Bairro da Luz, São Paulo - SP, 01102-000',
    'Bairro da Luz',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.530297,
    -46.631314,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap MUSEUM/1370081491; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-097',
    'Museu de Arte de São Paulo',
    'Outros',
    'outro',
    'Avenida Paulista, 1578, Cerqueira César, São Paulo - SP, 01310-200',
    'Cerqueira César',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.561496,
    -46.655968,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap MUSEUM/1770102280; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-098',
    'Museu do Futebol',
    'Outros',
    'outro',
    'Praça Charles Miller, Pacaembu, São Paulo - SP, 01234-010',
    'Pacaembu',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.547423,
    -46.665470,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap STADIUM/218678632; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-099',
    'Neo Química Arena',
    'Outros',
    'outro',
    'Avenida Miguel Inácio Curi, 111, Cidade Antônio Estêvão de Carvalho, São Paulo - SP, 08295-005',
    'Cidade Antônio Estêvão de Carvalho',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.545307,
    -46.474338,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap STADIUM/231044849; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-100',
    'Nubank Parque',
    'Outros',
    'outro',
    'Rua Palestra Itália, 1840, Pompéia, São Paulo - SP, 05005-000',
    'Pompéia',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.527497,
    -46.678528,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap STATION/4593540897; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-101',
    'Palmeiras-Barra Funda',
    'Transporte Público',
    'transporte',
    'Rua Jornalista Aloysio Biondi, Barra Funda, São Paulo - SP, 01140-070',
    'Barra Funda',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.525462,
    -46.667513,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/routes.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap GOVERNMENT/75490085; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-102',
    'Palácio dos Bandeirantes',
    'Órgão Público',
    'publico',
    'Avenida Morumbi, 4500, Cidade Jardim, São Paulo - SP, 05650-905',
    'Cidade Jardim',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.601562,
    -46.712383,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap PARK/199276067; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-103',
    'Parque Anhanguera',
    'Parque & Lazer',
    'parque',
    ', 1000, Jardim Britânia, São Paulo - SP, 05181-070',
    'Jardim Britânia',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.424709,
    -46.777416,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap NATURE_RESERVE/51423302; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-104',
    'Parque Ecológico do Tietê',
    'Parque & Lazer',
    'parque',
    ', 8055, Guarulhos, São Paulo - SP, 03719-000',
    'Guarulhos',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.489455,
    -46.521452,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap PROTECTED_AREA/19566651; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-105',
    'Parque Estadual Alberto Löfgren',
    'Parque & Lazer',
    'parque',
    'Mandaqui, São Paulo - SP',
    'Mandaqui',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.464881,
    -46.642433,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap PARK/125450993; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-106',
    'Parque Ibirapuera',
    'Parque & Lazer',
    'parque',
    'Moema, São Paulo - SP, 04002-010',
    'Moema',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.587713,
    -46.658521,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap PARK/7398292; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-107',
    'Parque Siqueira Campos',
    'Parque & Lazer',
    'parque',
    'Cerqueira César, São Paulo - SP',
    'Cerqueira César',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.562798,
    -46.657835,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap PARK/23268044; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-108',
    'Parque Villa-Lobos',
    'Parque & Lazer',
    'parque',
    'Alto de Pinheiros, São Paulo - SP, 05461-010',
    'Alto de Pinheiros',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.546284,
    -46.723728,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap PARK/231159197; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-109',
    'Parque da Água Branca',
    'Parque & Lazer',
    'parque',
    ', 455, Pompéia, São Paulo - SP, 05001-900',
    'Pompéia',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.530418,
    -46.669476,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap PEDESTRIAN/9734636; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-110',
    'Pateo do Collegio',
    'Outros',
    'outro',
    'Pateo do Collegio, Glicério, São Paulo - SP, 01016-040',
    'Glicério',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.548020,
    -46.633023,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap UNIVERSITY/226756679; município por campo municipal do provedor, wheelchair=limited
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-111',
    'Pontifícia Universidade Católica',
    'Educação',
    'educacao',
    'Rua Monte Alegre, 984, Perdizes, São Paulo - SP, 05014-901',
    'Perdizes',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.537965,
    -46.671244,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap PARK/75488634; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-112',
    'Praça da Sé',
    'Parque & Lazer',
    'parque',
    'Glicério, São Paulo - SP, 01001-000',
    'Glicério',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.550390,
    -46.633081,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap RESIDENTIAL/424586854; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-113',
    'Rua Vinte e Cinco de Março',
    'Vias Públicas',
    'via',
    'Rua Vinte e Cinco de Março, Glicério, São Paulo - SP, 01021-200',
    'Glicério',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.541275,
    -46.632136,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'red',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap COMMUNITY_CENTRE/5045600293; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-114',
    'SESC 24 de Maio',
    'Outros',
    'outro',
    'Rua Vinte e Quatro de Maio, 109, República, São Paulo - SP, 01041-001',
    'República',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.544117,
    -46.640002,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap STATION/826549219; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-115',
    'SESC-Pompeia',
    'Outros',
    'outro',
    'Avenida Pompeia, 242, Pompéia, São Paulo - SP, 05024-030',
    'Pompéia',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.527222,
    -46.683293,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap MALL/17448129; município por campo municipal do provedor, wheelchair=limited
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-116',
    'Shopping Center Norte',
    'Comércio & Serviços',
    'comercio',
    'Travessa Casalbuono, 120, Bairro da Coroa, São Paulo - SP, 02089-900',
    'Bairro da Coroa',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.515812,
    -46.618219,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap THEATRE/46927931; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-117',
    'Teatro Municipal de São Paulo',
    'Outros',
    'outro',
    'Praça Ramos de Azevedo, República, São Paulo - SP, 01037-000',
    'República',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.545280,
    -46.638638,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap PLACE_OF_WORSHIP/511530064; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-118',
    'Templo de Salomão',
    'Outros',
    'outro',
    'Avenida Celso Garcia, 605, Belém, São Paulo - SP, 03015-000',
    'Belém',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.537609,
    -46.606415,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap BUS_STATION/8546826; município por campo municipal do provedor, wheelchair=yes
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-119',
    'Terminal Rodoviário Tietê',
    'Transporte Público',
    'transporte',
    'Avenida Cruzeiro do Sul, 1800, Carandiru, São Paulo - SP, 02031-050',
    'Carandiru',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.516597,
    -46.624100,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/routes.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap GUARDHOUSE/396570189; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-120',
    'Universidade de São Paulo (Portaria 1)',
    'Educação',
    'educacao',
    'Avenida Afrânio Peixoto, Caxingui, São Paulo - SP, 05507-000',
    'Caxingui',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.565220,
    -46.712583,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'blue',
    'assets/icons/shield.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap PARK/48511928; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-121',
    'Vale do Anhangabaú',
    'Parque & Lazer',
    'parque',
    'Sé, São Paulo - SP',
    'Sé',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.545519,
    -46.636738,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap ATTRACTION/2634256028; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-122',
    'Viaduto do Chá',
    'Outros',
    'outro',
    'Viaduto do Chá, República, São Paulo - SP, 01002-020',
    'República',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.546582,
    -46.637954,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'orange',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;

-- São Paulo — fonte: OpenStreetMap PROTECTED_AREA/13096274; município por campo municipal do provedor
INSERT INTO locais (`local_code`, `name`, `category`, `category_key`, `address`, `bairro`, `municipio`, `uf`, `access_status`, `access_text`, `lat`, `lng`, `features`, `opening_hours`, `bus_lines`, `last_audit`, `image`, `avatar_color`, `avatar_icon`)
VALUES (
    'LOC-SP-123',
    'Área de Proteção Ambiental Parque e Fazenda do Carmo',
    'Parque & Lazer',
    'parque',
    ', 951, Parque do Carmo, São Paulo - SP',
    'Parque do Carmo',
    'São Paulo',
    'SP',
    'unverified',
    'Não verificado',
    -23.581535,
    -46.472935,
    NULL,
    NULL,
    NULL,
    NULL,
    'assets/images/map-background.jpg',
    'green',
    'assets/icons/pin.svg'
)
ON DUPLICATE KEY UPDATE `id` = `id`;
