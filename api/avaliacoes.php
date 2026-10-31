<?php
/**
 * Passe Livre Suzano — Endpoint de Avaliações e Reporte de Barreiras
 * Grava em `avaliacoes`, `avaliacao_criterios`, `arquivos`, `avaliacao_fotos` e `ocorrencias`.
 * Valida geolocalização das fotos quando disponível.
 */

require_once __DIR__ . '/_db_restore.php';
require_once __DIR__ . '/../includes/geo_centro.php';

$action = $_GET['action'] ?? 'create';
$db     = get_db();
$data   = get_request_data();

switch ($action) {

    // =========================================================================
    // 1. GRAVAR NOVA AVALIAÇÃO + CRITÉRIOS + FOTOS + OCORRÊNCIA
    // =========================================================================
    case 'create':
        $user = require_auth();
        $userId   = (int)$user['id'];
        $userName = $user['name'];

        $localId   = (int)($data['local_id'] ?? 0);
        $localCode = trim($data['local_code'] ?? '');
        $rating    = max(1, min(5, (int)round((float)($data['rating'] ?? 5))));

        $entrada        = $data['entrada_status'] ?? 'disponivel';
        $rampas         = $data['rampas_status'] ?? 'disponivel';
        $elevadores     = $data['elevadores_status'] ?? 'disponivel';
        $banheiros      = $data['banheiros_status'] ?? 'disponivel';
        $calcadas       = $data['calcadas_status'] ?? 'disponivel';
        $estacionamento = $data['estacionamento_status'] ?? 'disponivel';
        $comment        = trim((string)($data['comment'] ?? ($data['comentario'] ?? '')));
        $barrierDesc    = trim((string)($data['barrier_desc'] ?? ''));
        $criterios      = is_array($data['criterios'] ?? null) ? $data['criterios'] : [];
        $photos         = is_array($data['photos'] ?? null) ? $data['photos'] : [];

        if (mb_strlen($comment) > 1000) {
            $comment = mb_substr($comment, 0, 1000);
        }
        if (mb_strlen($barrierDesc) > 500) {
            $barrierDesc = mb_substr($barrierDesc, 0, 500);
        }

        // Metadados de geolocalização das fotos
        $fotoLat = isset($data['foto_lat']) ? (float)$data['foto_lat'] : null;
        $fotoLng = isset($data['foto_lng']) ? (float)$data['foto_lng'] : null;

        // O vínculo com o local é validado no servidor, nunca apenas pelo cliente.
        $localRow = null;
        if ($localId > 0) {
            $stmtL = $db->prepare("SELECT id, local_code FROM locais WHERE id = ? AND is_archived = 0 LIMIT 1");
            $stmtL->execute([$localId]);
            $localRow = $stmtL->fetch();
        }
        if (!$localRow && $localCode !== '') {
            $stmtL = $db->prepare("SELECT id, local_code FROM locais WHERE local_code = ? AND is_archived = 0 LIMIT 1");
            $stmtL->execute([$localCode]);
            $localRow = $stmtL->fetch();
        }
        if (!$localRow) {
            json_error('Local inválido ou não encontrado para avaliar.', 422);
        }
        $localId   = (int)$localRow['id'];
        $localCode = (string)$localRow['local_code'];

        try {
            $db->beginTransaction();

            // 1. Grava em `avaliacoes`
            $stmtAval = $db->prepare("
                INSERT INTO avaliacoes
                  (local_id, local_code, user_id, user_name, entrada_status, rampas_status, elevadores_status, banheiros_status, calcadas_status, estacionamento_status, barrier_desc, comment, photos, rating, verified)
                VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmtAval->execute([
                $localId,
                $localCode,
                $userId,
                $userName,
                $entrada,
                $rampas,
                $elevadores,
                $banheiros,
                $calcadas,
                $estacionamento,
                $barrierDesc !== '' ? $barrierDesc : null,
                $comment !== '' ? $comment : null,
                json_encode($photos, JSON_UNESCAPED_UNICODE),
                $rating
            ]);
            $avaliacaoId = (int)$db->lastInsertId();

            // 2. Grava em `avaliacao_criterios`
            $stmtCrit = $db->prepare("INSERT INTO avaliacao_criterios (avaliacao_id, criterio_key, nota, comentario) VALUES (?, ?, ?, ?)");
            if (!empty($criterios)) {
                foreach ($criterios as $key => $crit) {
                    $nota = is_array($crit) ? ($crit['nota'] ?? 5) : (int)$crit;
                    $coment = is_array($crit) ? ($crit['comentario'] ?? null) : null;
                    $stmtCrit->execute([$avaliacaoId, $key, $nota, $coment]);
                }
            } else {
                // Critérios padrão se não informados
                $stmtCrit->execute([$avaliacaoId, 'entrada', $entrada === 'disponivel' ? 5 : ($entrada === 'parcial' ? 3 : 1), null]);
                $stmtCrit->execute([$avaliacaoId, 'rampas', $rampas === 'disponivel' ? 5 : ($rampas === 'parcial' ? 3 : 1), null]);
                $stmtCrit->execute([$avaliacaoId, 'banheiros', $banheiros === 'disponivel' ? 5 : ($banheiros === 'parcial' ? 3 : 1), null]);
            }

            // 3. Grava fotos em `arquivos` e `avaliacao_fotos`
            $stmtArq = $db->prepare("
                INSERT INTO arquivos (user_id, nome_original, nome_arquivo, caminho, tipo_origem, referencia_id)
                VALUES (?, ?, ?, ?, 'avaliacao', ?)
            ");
            $stmtAf = $db->prepare("
                INSERT INTO avaliacao_fotos (avaliacao_id, arquivo_id, url_foto, foto_lat, foto_lng)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($photos as $photoUrl) {
                if (empty($photoUrl)) continue;
                $nomeArq = basename($photoUrl);
                $stmtArq->execute([$userId, $nomeArq, $nomeArq, $photoUrl, $avaliacaoId]);
                $arquivoId = (int)$db->lastInsertId();

                // Coordenada da foto: mantém o ponto quando ele é uma posição
                // geográfica plausível. A caixa antiga era o perímetro de Suzano
                // e apagava a geolocalização de qualquer avaliação feita em outra
                // cidade do estado.
                $posFoto = pl_geo_centro_valido($fotoLat, $fotoLng);

                $stmtAf->execute([
                    $avaliacaoId,
                    $arquivoId,
                    $photoUrl,
                    $posFoto['ok'] ? $fotoLat : null,
                    $posFoto['ok'] ? $fotoLng : null
                ]);
            }

            // 4. Se houver reporte de barreira, gera automaticamente em `ocorrencias`
            if (!empty($barrierDesc)) {
                $protoOcorr = 'OCO-' . date('Y') . '-' . str_pad((string)mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
                $stmtOcorr = $db->prepare("
                    INSERT INTO ocorrencias
                      (user_id, local_id, protocolo, titulo, descricao, categoria, status, gravidade, endereco, foto_url, reporter_nome)
                    VALUES
                      (?, ?, ?, ?, ?, 'Barreira Arquitetônica', 'ativa', 'media', ?, ?, ?)
                ");

                // Busca o endereço real do local. O texto fixo 'Suzano - SP'
                // rotulava de Suzano uma barreira reportada em qualquer cidade.
                $localEnd = '';
                if ($localId > 0) {
                    $stLoc = $db->prepare("SELECT address, bairro, municipio FROM locais WHERE id = ?");
                    $stLoc->execute([$localId]);
                    $rowLoc = $stLoc->fetch(PDO::FETCH_ASSOC);
                    if ($rowLoc) {
                        $localEnd = trim(implode(', ', array_filter([
                            $rowLoc['address'] ?? '',
                            $rowLoc['bairro'] ?? '',
                            $rowLoc['municipio'] ?? ''
                        ], static fn($v) => trim((string)$v) !== '')));
                    }
                }

                $primeiraFoto = !empty($photos) ? $photos[0] : null;
                $stmtOcorr->execute([
                    $userId,
                    $localId ?: null,
                    $protoOcorr,
                    'Barreira reportada em avaliação',
                    $barrierDesc,
                    $localEnd,
                    $primeiraFoto,
                    $userName
                ]);
            }

            // 5. Mantém a média e a contagem do local alinhadas com as avaliações reais
            $stmtStats = $db->prepare("SELECT AVG(rating) AS media, COUNT(*) AS total FROM avaliacoes WHERE local_id = ?");
            $stmtStats->execute([$localId]);
            $stats = $stmtStats->fetch() ?: ['media' => 0, 'total' => 0];
            $stmtUpdLocal = $db->prepare("UPDATE locais SET rating = ?, reviews_count = ? WHERE id = ?");
            $stmtUpdLocal->execute([round((float)$stats['media'], 1), (int)$stats['total'], $localId]);

            log_audit('avaliacoes', 'Nova Avaliação Registrada', "Avaliação #{$avaliacaoId} registrada para o local ID {$localId} por {$userName}", 'baixo', $userId);

            $db->commit();

            json_response([
                'success'      => true,
                'message'      => 'Sua avaliação foi registrada com sucesso no banco de dados!',
                'avaliacao_id' => $avaliacaoId,
                'local_id'     => $localId,
                'rating'       => $rating,
                'comment'      => $comment
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            json_error('Erro ao salvar avaliação: ' . $e->getMessage(), 500);
        }
        break;

    // =========================================================================
    // 2. LISTAR AVALIAÇÕES DE UM LOCAL
    // =========================================================================
    case 'list':
        $localId = (int)($_GET['local_id'] ?? 0);
        if ($localId <= 0) {
            json_error('ID do local é obrigatório.');
        }

        $stmt = $db->prepare("
            SELECT a.*, u.avatar AS user_avatar, u.role AS user_role
            FROM avaliacoes a
            LEFT JOIN usuarios u ON u.id = a.user_id
            WHERE a.local_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$localId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['photos'] = is_string($r['photos']) ? json_decode($r['photos'], true) : ($r['photos'] ?: []);
        }

        json_response([
            'success'    => true,
            'avaliacoes' => $rows
        ]);
        break;

    default:
        json_error('Ação de avaliação não suportada.', 404);
}
