<?php
/**
 * Passe Livre Suzano — Endpoint de Relatórios Gerenciais e Indicadores
 * KPIs, listagem, emissão real de arquivos (PDF/CSV/XLSX) e download autenticado.
 */

require_once __DIR__ . '/_db_restore.php';

$admin  = require_admin();
$action = $_GET['action'] ?? 'kpis';
$db     = get_db();
$data   = get_request_data();

// =========================================================================
// HELPERS DE GERAÇÃO DE ARQUIVO
// =========================================================================
function rel_date(?string $value, string $fallback): string
{
    $value = trim((string)$value);
    $d = DateTime::createFromFormat('!Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : $fallback;
}

function rel_periodo_label(string $inicio, string $fim): string
{
    return date('d/m/Y', strtotime($inicio)) . ' a ' . date('d/m/Y', strtotime($fim));
}

function rel_escape_xml(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function rel_csv(array $matrix): string
{
    $fh = fopen('php://temp', 'r+');
    foreach ($matrix as $row) {
        fputcsv($fh, $row, ';', '"', '\\');
    }
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);
    return "\xEF\xBB\xBF" . $csv;
}

function rel_pdf_escape(string $text): string
{
    $text = preg_replace('/[\r\n]+/', ' ', $text);
    $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);
    if ($converted !== false) {
        $text = $converted;
    }
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

/** Gera um PDF 1.4 simples (texto monoespaçado por linha, sem dependências externas). */
function rel_pdf(array $matrix): string
{
    $lines = [];
    foreach ($matrix as $row) {
        if (!$row) {
            $lines[] = str_repeat('-', 100);
            continue;
        }
        $line = implode(' | ', array_filter($row, static fn($c) => $c !== '' && $c !== null));
        $lines[] = mb_substr($line, 0, 110);
    }

    $chunks = array_chunk($lines, 46);
    if (!$chunks) {
        $chunks = [['(sem dados)']];
    }

    $kids = [];
    $pageObj = 4;
    foreach ($chunks as $_) {
        $kids[] = $pageObj . ' 0 R';
        $pageObj += 2;
    }

    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($chunks) . ' >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

    $pageObj = 4;
    foreach ($chunks as $chunk) {
        $stream = '';
        $y = 800;
        $first = true;
        foreach ($chunk as $line) {
            $size = $first ? 13 : 9.5;
            $stream .= 'BT /F1 ' . $size . ' Tf 45 ' . $y . ' Td (' . rel_pdf_escape($line) . ") Tj ET\n";
            $y -= $first ? 20 : 14;
            $first = false;
        }
        $contentObj = $pageObj + 1;
        $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
            . '/Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObj . ' 0 R >>';
        $objects[$contentObj] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . 'endstream';
        $pageObj += 2;
    }

    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [];
    foreach ($objects as $num => $body) {
        $offsets[$num] = strlen($pdf);
        $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
    }

    $xref = strlen($pdf);
    $max = max(array_keys($objects));
    $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $max; $i++) {
        $pdf .= isset($offsets[$i])
            ? sprintf("%010d 00000 n \n", $offsets[$i])
            : "0000000000 65535 f \n";
    }
    $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

    return $pdf;
}

/** Empacota arquivos em um ZIP sem compressão (método store), sem extensão zip. */
function rel_zip(array $files): string
{
    $localData = '';
    $central   = '';
    $offset    = 0;

    foreach ($files as $name => $content) {
        $crc = crc32($content);
        $len = strlen($content);
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $len, $len, strlen($name), 0)
            . $name . $content;
        $localData .= $local;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50, 20, 20, 0, 0, 0, 0, $crc, $len, $len,
            strlen($name), 0, 0, 0, 0, 0, $offset
        ) . $name;

        $offset += strlen($local);
    }

    return $localData . $central . pack(
        'VvvvvVVv',
        0x06054b50, 0, 0, count($files), count($files), strlen($central), strlen($localData), 0
    );
}

function rel_col_letter(int $index): string
{
    $letter = '';
    $index++;
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letter = chr(65 + $mod) . $letter;
        $index = (int)(($index - $mod) / 26);
    }
    return $letter;
}

function rel_xlsx(array $matrix): string
{
    $rowsXml = '';
    foreach ($matrix as $r => $row) {
        $cells = '';
        foreach ($row as $c => $value) {
            $value = (string)$value;
            if ($value === '') {
                continue;
            }
            $ref = rel_col_letter($c) . ($r + 1);
            $cells .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                . rel_escape_xml($value) . '</t></is></c>';
        }
        $rowsXml .= '<row r="' . ($r + 1) . '">' . $cells . '</row>';
    }

    $prefix = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';

    $contentTypes = $prefix
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>';

    $rootRels = $prefix
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbook = $prefix
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Relatorio" sheetId="1" r:id="rId1"/></sheets></workbook>';

    $workbookRels = $prefix
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>';

    $sheet = $prefix
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $rowsXml . '</sheetData></worksheet>';

    return rel_zip([
        '[Content_Types].xml'          => $contentTypes,
        '_rels/.rels'                  => $rootRels,
        'xl/workbook.xml'              => $workbook,
        'xl/_rels/workbook.xml.rels'   => $workbookRels,
        'xl/worksheets/sheet1.xml'     => $sheet,
    ]);
}

function rel_build_matrix(string $titulo, string $tipo, string $inicio, string $fim, array $admin, array $totais, array $rows, ?string $modulo): array
{
    $total = (int)($totais['total'] ?? 0);
    $aprov = (int)($totais['aprovadas'] ?? 0);
    $recus = (int)($totais['recusadas'] ?? 0);
    $pend  = (int)($totais['pendentes'] ?? 0);
    $taxa  = $total > 0 ? round(($aprov / $total) * 100, 1) : 0;
    $media = $totais['media_avaliacoes'] !== null ? round((float)$totais['media_avaliacoes'], 2) : 0;

    $matrix = [
        ['Passe Livre Suzano — Relatório Gerencial'],
        ['Título', $titulo],
        ['Tipo de relatório', $tipo],
        ['Módulo', $modulo ?: 'Geral (todos os módulos)'],
        ['Período', rel_periodo_label($inicio, $fim)],
        ['Emitido por', $admin['name'] ?? '—'],
        ['Emitido em', date('d/m/Y H:i:s')],
        [],
        ['INDICADOR', 'VALOR'],
        ['Solicitações no período', (string)$total],
        ['Deferidas', (string)$aprov],
        ['Indeferidas', (string)$recus],
        ['Pendentes', (string)$pend],
        ['Taxa de deferimento', $taxa . '%'],
        ['Média das avaliações', $media > 0 ? number_format($media, 2, ',', '.') : 'Sem avaliações'],
        [],
        ['Protocolo', 'Tipo', 'Status', 'Prioridade', 'Criado em', 'Cidadão', 'Região'],
    ];

    foreach ($rows as $r) {
        $matrix[] = [
            (string)($r['protocol'] ?? ''),
            (string)($r['type_name'] ?? ''),
            (string)($r['status_name'] ?? $r['status'] ?? ''),
            (string)($r['priority_name'] ?? ''),
            !empty($r['created_at']) ? date('d/m/Y H:i', strtotime($r['created_at'])) : '',
            (string)($r['citizen_name'] ?? ''),
            (string)($r['region_name'] ?? $r['region'] ?? ''),
        ];
    }

    if (!$rows) {
        $matrix[] = ['Nenhuma solicitação encontrada no período informado.'];
    }

    return $matrix;
}

switch ($action) {

    // =========================================================================
    // 1. CARREGAR KPIS E GRÁFICOS
    // =========================================================================
    case 'kpis':
        $stmtTot = $db->query("
            SELECT 
              COUNT(*) AS total,
              SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS aprovadas,
              SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS recusadas,
              SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pendentes
            FROM solicitacoes
        ");
        $tot = $stmtTot->fetch();
        $totalSols = (int)($tot['total'] ?? 0);
        $aprovadas = (int)($tot['aprovadas'] ?? 0);
        $taxaDefer = ($totalSols > 0) ? round(($aprovadas / $totalSols) * 100, 1) : 0;

        $tempoMedio = (float)$db->query("
            SELECT AVG(DATEDIFF(updated_at, created_at))
            FROM solicitacoes
            WHERE status IN ('approved', 'rejected') AND updated_at IS NOT NULL
        ")->fetchColumn();
        $tempoMedio = $tempoMedio > 0 ? round($tempoMedio, 1) : 0;

        $stmtNps = $db->query("SELECT AVG(rating) AS media_nota FROM avaliacoes");
        $avgRating = (float)$stmtNps->fetchColumn();
        $npsScore = round($avgRating * 18);

        $totalLocais   = (int)$db->query("SELECT COUNT(*) FROM locais WHERE is_archived = 0")->fetchColumn();
        $totalUsuarios = (int)$db->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();

        $stmtMes = $db->query("
            SELECT 
              DATE_FORMAT(created_at, '%b/%y') AS mes,
              COUNT(*) AS total,
              SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS deferidos
            FROM solicitacoes
            GROUP BY DATE_FORMAT(created_at, '%Y-%m'), mes
            ORDER BY DATE_FORMAT(created_at, '%Y-%m') ASC
        ");
        $graficoMes = $stmtMes->fetchAll();

        $stmtDef = $db->query("
            SELECT COALESCE(categoria_pcd, 'Física/motora') AS deficiencia, COUNT(*) AS total
            FROM usuario_necessidades
            GROUP BY deficiencia
            ORDER BY total DESC
        ");
        $graficoDeficiencia = $stmtDef->fetchAll();

        $stmtSetor = $db->query("
            SELECT COALESCE(type_name, 'Outros') AS setor, COUNT(*) AS total
            FROM solicitacoes
            GROUP BY setor
            ORDER BY total DESC
        ");
        $graficoSetor = $stmtSetor->fetchAll();

        json_response([
            'success' => true,
            'kpis'    => [
                'total_solicitacoes' => $totalSols,
                'taxa_deferimento'   => $taxaDefer,
                'tempo_medio_dias'   => $tempoMedio,
                'nps_score'          => $npsScore,
                'total_locais'       => $totalLocais,
                'total_usuarios'     => $totalUsuarios
            ],
            'grafico_mes'          => $graficoMes,
            'grafico_deficiencia'  => $graficoDeficiencia,
            'grafico_setor'        => $graficoSetor
        ]);
        break;

    // =========================================================================
    // 2. LISTAR RELATÓRIOS GERADOS
    // =========================================================================
    case 'list':
        $stmt = $db->query("
            SELECT rg.*, u.name AS gestor_nome,
                   a.caminho AS arquivo_url, a.tamanho_bytes, a.nome_arquivo
            FROM relatorios_gerados rg
            LEFT JOIN usuarios u ON u.id = rg.gestor_id
            LEFT JOIN arquivos a ON a.id = rg.arquivo_id
            ORDER BY rg.created_at DESC
        ");
        $rows = $stmt->fetchAll();

        $baseDir = realpath(__DIR__ . '/../assets/relatorios') ?: '';
        foreach ($rows as &$row) {
            $abs = $row['arquivo_url'] ? realpath(__DIR__ . '/../' . $row['arquivo_url']) : false;
            $row['arquivo_existe'] = (bool)($abs && $baseDir && strpos($abs, $baseDir) === 0 && is_file($abs));
            if ($row['arquivo_existe']) {
                $row['tamanho_bytes'] = filesize($abs);
            }
        }
        unset($row);

        json_response([
            'success' => true,
            'rows'    => $rows
        ]);
        break;

    // =========================================================================
    // 3. EMITIR RELATÓRIO E GERAR O ARQUIVO REAL
    // =========================================================================
    case 'generate':
        $titulo  = trim($data['titulo'] ?? '');
        $tipo    = trim($data['tipo'] ?? 'Sintético Mensal');
        $modulo  = trim($data['modulo'] ?? '');
        $formato = strtoupper(trim($data['formato'] ?? 'PDF'));
        $inicio  = rel_date($data['periodo_inicio'] ?? null, date('Y-m-01'));
        $fim     = rel_date($data['periodo_fim'] ?? null, date('Y-m-d'));

        if (!in_array($formato, ['PDF', 'CSV', 'XLSX'], true)) {
            $formato = 'PDF';
        }
        if ($titulo === '') {
            json_error('Informe o título do relatório.');
        }
        if ($inicio > $fim) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        $stmtTot = $db->prepare("
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS aprovadas,
                   SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS recusadas,
                   SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pendentes
            FROM solicitacoes
            WHERE DATE(created_at) BETWEEN ? AND ?
        ");
        $stmtTot->execute([$inicio, $fim]);
        $totais = $stmtTot->fetch() ?: [];

        $stmtAvg = $db->prepare("SELECT AVG(rating) FROM avaliacoes WHERE DATE(created_at) BETWEEN ? AND ?");
        $stmtAvg->execute([$inicio, $fim]);
        $totais['media_avaliacoes'] = $stmtAvg->fetchColumn();

        $stmtRows = $db->prepare("
            SELECT protocol, type_name, status, status_name, priority_name,
                   created_at, citizen_name, region, region_name
            FROM solicitacoes
            WHERE DATE(created_at) BETWEEN ? AND ?
            ORDER BY created_at ASC
            LIMIT 5000
        ");
        $stmtRows->execute([$inicio, $fim]);
        $rows = $stmtRows->fetchAll();

        $matrix = rel_build_matrix($titulo, $tipo, $inicio, $fim, $admin, $totais, $rows, $modulo);

        $ext  = strtolower($formato);
        $mime = [
            'PDF'  => 'application/pdf',
            'CSV'  => 'text/csv; charset=utf-8',
            'XLSX' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ][$formato];

        if ($formato === 'PDF') {
            $content = rel_pdf($matrix);
        } elseif ($formato === 'CSV') {
            $content = rel_csv($matrix);
        } else {
            $content = rel_xlsx($matrix);
        }

        $slugBase = strtr(mb_strtolower($tipo, 'UTF-8'), [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n'
        ]);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slugBase);
        $nomeArquivo = 'relatorio_' . trim($slug, '_') . '_' . date('Ymd_His') . '.' . $ext;
        $caminhoRel  = 'assets/relatorios/' . $nomeArquivo;
        $dirAbs      = __DIR__ . '/../assets/relatorios';
        $abs         = $dirAbs . '/' . $nomeArquivo;

        if (!is_dir($dirAbs) && !mkdir($dirAbs, 0775, true) && !is_dir($dirAbs)) {
            json_error('Não foi possível criar o diretório de relatórios.', 500);
        }
        if (!is_file($dirAbs . '/.htaccess')) {
            file_put_contents(
                $dirAbs . '/.htaccess',
                "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n"
            );
        }
        if (file_put_contents($abs, $content) === false) {
            json_error('Não foi possível gravar o arquivo do relatório.', 500);
        }

        try {
            $db->beginTransaction();

            $stmtArq = $db->prepare("
                INSERT INTO arquivos (user_id, nome_original, nome_arquivo, caminho, tipo_mime, tamanho_bytes, tipo_origem)
                VALUES (?, ?, ?, ?, ?, ?, 'relatorio')
            ");
            $stmtArq->execute([$admin['id'], $nomeArquivo, $nomeArquivo, $caminhoRel, $mime, strlen($content)]);
            $arquivoId = (int)$db->lastInsertId();

            $stmtRg = $db->prepare("
                INSERT INTO relatorios_gerados
                  (gestor_id, titulo, tipo_relatorio, periodo_inicio, periodo_fim, parametros, arquivo_id, formato)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtRg->execute([
                $admin['id'],
                $titulo,
                $tipo,
                $inicio,
                $fim,
                json_encode([
                    'inicio' => $inicio,
                    'fim' => $fim,
                    'modulo' => $modulo,
                    'gerado_por' => $admin['name'],
                    'total_registros' => count($rows)
                ], JSON_UNESCAPED_UNICODE),
                $arquivoId,
                $formato
            ]);
            $relatorioId = (int)$db->lastInsertId();

            log_audit(
                'relatorios',
                "Emissão de Relatório: {$titulo}",
                "Relatório #{$relatorioId} ({$formato}) emitido por {$admin['name']} com " . count($rows) . " registro(s)",
                'medio',
                $admin['id']
            );

            $db->commit();

            json_response([
                'success'      => true,
                'message'      => 'Relatório emitido e arquivo gerado com sucesso!',
                'relatorio_id' => $relatorioId,
                'arquivo_url'  => $caminhoRel,
                'download_url' => 'api/relatorios.php?action=download&id=' . $relatorioId
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            @unlink($abs);
            json_error('Erro ao gerar relatório: ' . $e->getMessage(), 500);
        }
        break;

    // =========================================================================
    // 4. DOWNLOAD AUTENTICADO DO ARQUIVO GERADO
    // =========================================================================
    case 'download':
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            json_error('ID do relatório não informado.');
        }

        $stmt = $db->prepare("
            SELECT rg.id, rg.titulo, rg.formato, a.caminho, a.tipo_mime
            FROM relatorios_gerados rg
            LEFT JOIN arquivos a ON a.id = rg.arquivo_id
            WHERE rg.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $rel = $stmt->fetch();

        if (!$rel || empty($rel['caminho'])) {
            json_error('Relatório não encontrado.', 404);
        }

        $baseDir = realpath(__DIR__ . '/../assets/relatorios');
        $abs     = realpath(__DIR__ . '/../' . $rel['caminho']);
        if (!$baseDir || !$abs || strpos($abs, $baseDir) !== 0 || !is_file($abs)) {
            json_error('Arquivo do relatório indisponível. Emita o relatório novamente.', 404);
        }

        header('Content-Type: ' . ($rel['tipo_mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . basename($abs) . '"');
        header('Content-Length: ' . filesize($abs));
        header('Cache-Control: private, max-age=0, must-revalidate');
        readfile($abs);
        exit;

    default:
        json_error('Ação de relatório não suportada.', 404);
}
