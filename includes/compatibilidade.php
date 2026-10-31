<?php
/**
 * Passe Livre Suzano — Compatibilidade entre o perfil PcD e os locais
 *
 * A pontuação parte exclusivamente das necessidades cadastradas pelo usuário
 * autenticado. Cada necessidade tem critérios ponderados; um recurso ausente
 * do banco é tratado como desconhecido (não soma pontos), nunca como garantia.
 */

require_once __DIR__ . '/acessibilidade.php';

if (!function_exists('pl_compat_catalog')) {
    function pl_compat_catalog(): array
    {
        static $catalog = null;
        if ($catalog !== null) {
            return $catalog;
        }

        $catalog = [
            'motora' => [
                ['key' => 'entrada', 'label' => 'Entrada sem degrau', 'weight' => 25, 'match' => ['embarque_nivel', 'entrada_nivel', 'entrada_sem_degrau', 'acesso_nivel', 'acesso_plano']],
                ['key' => 'rampas', 'label' => 'Rampas de acesso', 'weight' => 20, 'match' => ['rampas', 'rampa_acesso', 'rampas_normatizadas', 'rampa']],
                ['key' => 'elevador', 'label' => 'Elevador acessível', 'weight' => 15, 'match' => ['elevador', 'elevador_maca', 'elevador_acessivel']],
                ['key' => 'banheiro', 'label' => 'Banheiro adaptado', 'weight' => 20, 'match' => ['banheiro_adaptado', 'sanitario_adaptado', 'banheiro_acessivel']],
                ['key' => 'circulacao', 'label' => 'Circulação nivelada', 'weight' => 10, 'match' => ['pistas_niveladas', 'circulacao_livre', 'corredores_largos']],
                ['key' => 'vagas', 'label' => 'Vagas PcD', 'weight' => 10, 'match' => ['vagas_pcd', 'estacionamento_pcd', 'vaga_pcd']],
            ],
            'mobilidade' => [
                ['key' => 'entrada', 'label' => 'Entrada sem degrau', 'weight' => 25, 'match' => ['embarque_nivel', 'entrada_nivel', 'entrada_sem_degrau', 'acesso_nivel', 'acesso_plano']],
                ['key' => 'rampas', 'label' => 'Rampas de acesso', 'weight' => 20, 'match' => ['rampas', 'rampa_acesso', 'rampas_normatizadas', 'rampa']],
                ['key' => 'elevador', 'label' => 'Elevador acessível', 'weight' => 10, 'match' => ['elevador', 'elevador_maca', 'elevador_acessivel']],
                ['key' => 'banheiro', 'label' => 'Banheiro adaptado', 'weight' => 15, 'match' => ['banheiro_adaptado', 'sanitario_adaptado', 'banheiro_acessivel']],
                ['key' => 'circulacao', 'label' => 'Circulação nivelada', 'weight' => 15, 'match' => ['pistas_niveladas', 'circulacao_livre', 'corredores_largos']],
                ['key' => 'vagas', 'label' => 'Vagas PcD', 'weight' => 10, 'match' => ['vagas_pcd', 'estacionamento_pcd', 'vaga_pcd']],
                ['key' => 'atendimento', 'label' => 'Atendimento prioritário', 'weight' => 5, 'match' => ['atendimento_prioritario', 'acompanhamento_prioritario']],
            ],
            'visual' => [
                ['key' => 'piso_tatil', 'label' => 'Piso tátil', 'weight' => 25, 'match' => ['piso_tatil', 'piso_tatil_direcional', 'piso_tatil_alerta', 'mapa_tatil']],
                ['key' => 'sinalizacao_sonora', 'label' => 'Sinalização sonora', 'weight' => 20, 'match' => ['sinalizacao_sonora', 'semaforo_sonoro', 'aviso_sonoro']],
                ['key' => 'braille', 'label' => 'Sinalização em Braille', 'weight' => 20, 'match' => ['braille', 'sinalizacao_braille', 'placa_braille']],
                ['key' => 'acesso', 'label' => 'Acesso sem barreiras', 'weight' => 20, 'match' => ['embarque_nivel', 'entrada_nivel', 'acesso_nivel', 'corrimao']],
                ['key' => 'atendimento', 'label' => 'Atendimento prioritário', 'weight' => 15, 'match' => ['atendimento_prioritario', 'acompanhamento_prioritario']],
            ],
            'auditiva' => [
                ['key' => 'sinalizacao_visual', 'label' => 'Sinalização visual/luminosa', 'weight' => 25, 'match' => ['sinalizacao_visual', 'sinalizacao_luminosa', 'aviso_visual']],
                ['key' => 'libras', 'label' => 'Atendimento em Libras', 'weight' => 25, 'match' => ['libras', 'interprete_libras', 'atendimento_libras']],
                ['key' => 'legendas', 'label' => 'Legendas e textos', 'weight' => 20, 'match' => ['legendas', 'closed_caption', 'legenda']],
                ['key' => 'aro', 'label' => 'Aro de indução', 'weight' => 15, 'match' => ['aro_inducao', 'implante_coclear', 'sistema_fm']],
                ['key' => 'atendimento', 'label' => 'Atendimento prioritário', 'weight' => 15, 'match' => ['atendimento_prioritario', 'acompanhamento_prioritario']],
            ],
            'intelectual' => [
                ['key' => 'pictogramas', 'label' => 'Sinalização com pictogramas', 'weight' => 25, 'match' => ['sinalizacao_pictogramas', 'pictogramas', 'comunicacao_alternativa', 'prancha_comunicacao']],
                ['key' => 'equipe_treinada', 'label' => 'Equipe treinada', 'weight' => 20, 'match' => ['equipe_treinada', 'atendimento_especializado', 'acompanhamento_especializado']],
                ['key' => 'ambiente_calmo', 'label' => 'Ambiente calmo', 'weight' => 20, 'match' => ['ambiente_calmo', 'sala_calma', 'espaco_sensorial']],
                ['key' => 'rotina', 'label' => 'Rotina previsível', 'weight' => 15, 'match' => ['visita_guiada', 'roteiro_guiado', 'sinalizacao_simples']],
                ['key' => 'atendimento', 'label' => 'Atendimento prioritário', 'weight' => 20, 'match' => ['atendimento_prioritario', 'acompanhamento_prioritario']],
            ],
            'psicossocial' => [
                ['key' => 'ambiente_calmo', 'label' => 'Ambiente calmo', 'weight' => 30, 'match' => ['ambiente_calmo', 'sala_calma', 'espaco_sensorial']],
                ['key' => 'equipe_treinada', 'label' => 'Equipe treinada', 'weight' => 25, 'match' => ['equipe_treinada', 'atendimento_especializado', 'acompanhamento_especializado']],
                ['key' => 'atendimento', 'label' => 'Atendimento prioritário', 'weight' => 20, 'match' => ['atendimento_prioritario', 'acompanhamento_prioritario']],
                ['key' => 'rotina', 'label' => 'Rotina previsível', 'weight' => 25, 'match' => ['visita_guiada', 'roteiro_guiado', 'sinalizacao_simples']],
            ],
            'outra' => [
                ['key' => 'rampas', 'label' => 'Rampas de acesso', 'weight' => 20, 'match' => ['rampas', 'rampa_acesso', 'rampas_normatizadas', 'rampa']],
                ['key' => 'elevador', 'label' => 'Elevador acessível', 'weight' => 20, 'match' => ['elevador', 'elevador_maca', 'elevador_acessivel']],
                ['key' => 'banheiro', 'label' => 'Banheiro adaptado', 'weight' => 20, 'match' => ['banheiro_adaptado', 'sanitario_adaptado', 'banheiro_acessivel']],
                ['key' => 'piso_tatil', 'label' => 'Piso tátil', 'weight' => 15, 'match' => ['piso_tatil', 'piso_tatil_direcional', 'piso_tatil_alerta']],
                ['key' => 'atendimento', 'label' => 'Atendimento prioritário', 'weight' => 15, 'match' => ['atendimento_prioritario', 'acompanhamento_prioritario']],
                ['key' => 'vagas', 'label' => 'Vagas PcD', 'weight' => 10, 'match' => ['vagas_pcd', 'estacionamento_pcd', 'vaga_pcd']],
            ],
        ];

        return $catalog;
    }
}

if (!function_exists('pl_compat_key')) {
    function pl_compat_key(string $value): string
    {
        return str_replace(' ', '_', pl_acess_norm($value));
    }
}

if (!function_exists('pl_compat_user_needs')) {
    /**
     * Necessidades PcD do usuário autenticado, vindas do cadastro.
     * Se o cadastro estiver vazio, usa a tabela normalizada usuario_necessidades.
     */
    function pl_compat_user_needs(?array $user, ?PDO $db = null): array
    {
        if (!$user) {
            return [];
        }

        $raw = $user['pcd_categories'] ?? [];
        if (!is_array($raw)) {
            $decoded = json_decode((string)$raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        $needs = pl_pcd_keys($raw);
        if ($needs || !$db || empty($user['id'])) {
            return $needs;
        }

        try {
            $stmt = $db->prepare("SELECT categoria_pcd FROM usuario_necessidades WHERE user_id = ?");
            $stmt->execute([(int)$user['id']]);
            return pl_pcd_keys($stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('pl_compat_score')) {
    /**
     * Calcula a compatibilidade do local com as necessidades informadas.
     * Retorna null quando o usuário não tem necessidades cadastradas.
     *
     * @param array  $needs        chaves canônicas (motora, visual, ...)
     * @param array  $features     lista de recursos do local (locais.features)
     * @param array  $recursos     linhas de local_recursos ou mapa chave => status
     * @param string $accessStatus access_status do local
     */
    function pl_compat_score(array $features, array $recursos, string $accessStatus, array $needs): ?array
    {
        $needs = array_values(array_intersect($needs, array_keys(pl_compat_catalog())));
        if (!$needs) {
            return null;
        }

        $featureSet = [];
        foreach ($features as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $raw = is_string($key) ? $key : $value;
            if (is_string($raw) && $raw !== '') {
                $featureSet[pl_compat_key($raw)] = true;
            }
        }

        $resourceMap = [];
        foreach ($recursos as $key => $value) {
            if (is_array($value)) {
                $raw = (string)($value['recurso_key'] ?? $value['key'] ?? '');
                $status = (string)($value['status'] ?? '');
            } else {
                $raw = (string)$key;
                $status = (string)$value;
            }
            if ($raw !== '') {
                $resourceMap[pl_compat_key($raw)] = pl_compat_key($status);
            }
        }

        $status = strtolower(trim($accessStatus));
        $featureFactor = ($status === 'verified' || $status === 'full')
            ? 1.0
            : (($status === 'partial') ? 0.5 : 0.0);

        $catalog = pl_compat_catalog();
        $totalWeight = 0.0;
        $earned = 0.0;
        $known = 0;
        $criteria = [];

        foreach ($needs as $need) {
            foreach ($catalog[$need] ?? [] as $criterion) {
                $weight = (float)$criterion['weight'];
                $totalWeight += $weight;

                $explicit = null;
                $inFeatures = false;
                foreach ($criterion['match'] as $matchKey) {
                    $matchKey = pl_compat_key($matchKey);
                    if (array_key_exists($matchKey, $resourceMap)) {
                        $resStatus = $resourceMap[$matchKey];
                        $value = ($resStatus === 'disponivel')
                            ? 1.0
                            : (($resStatus === 'parcial') ? 0.5 : 0.0);
                        $explicit = ($explicit === null) ? $value : max($explicit, $value);
                    }
                    if (isset($featureSet[$matchKey])) {
                        $inFeatures = true;
                    }
                }

                if ($explicit !== null) {
                    $value = $explicit;
                    $state = ($value >= 1.0) ? 'disponivel' : (($value > 0.0) ? 'parcial' : 'indisponivel');
                    $known++;
                } elseif ($inFeatures && $featureFactor > 0.0) {
                    $value = $featureFactor;
                    $state = ($value >= 1.0) ? 'disponivel' : 'parcial';
                    $known++;
                } elseif ($inFeatures) {
                    $value = 0.0;
                    $state = 'nao_confirmado';
                } else {
                    $value = 0.0;
                    $state = 'nao_informado';
                }

                $earned += $weight * $value;
                $criteria[] = [
                    'need'   => $need,
                    'key'    => $criterion['key'],
                    'label'  => $criterion['label'],
                    'weight' => (int)$weight,
                    'status' => $state,
                    'score'  => (int)round($weight * $value),
                ];
            }
        }

        $pct = $totalWeight > 0 ? (int)round(($earned / $totalWeight) * 100) : 0;
        $nivel = $known === 0
            ? 'sem_dados'
            : (($pct >= 70) ? 'alta' : (($pct >= 40) ? 'media' : 'baixa'));

        return [
            'pct'      => max(0, min(100, $pct)),
            'nivel'    => $nivel,
            'dados'    => $known,
            'total'    => count($criteria),
            'criterios' => $criteria,
        ];
    }
}

if (!function_exists('pl_compat_applied_criteria')) {
    function pl_compat_applied_criteria(array $needs): array
    {
        $catalog = pl_compat_catalog();
        $labels = [];
        foreach ($needs as $need) {
            foreach ($catalog[$need] ?? [] as $criterion) {
                $labels[$criterion['label']] = true;
            }
        }
        return array_keys($labels);
    }
}
