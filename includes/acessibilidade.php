<?php
/**
 * Passe Livre Suzano — Normalização dos dados de acessibilidade
 *
 * O cadastro grava rótulos ("Física/motora", "Intelectual/TEA") enquanto o
 * Editar Perfil usa chaves curtas ("motora", "intelectual"). Estas funções
 * convertem ambos os formatos para um conjunto único, sem alterar o schema.
 */

if (!function_exists('pl_acess_norm')) {
    function pl_acess_norm(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $value = preg_replace('/[_\-\s]+/', ' ', $value) ?? $value;
        return strtr($value, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
    }
}

if (!function_exists('pl_pcd_key')) {
    /**
     * Converte um rótulo/chave de deficiência para a chave canônica do perfil.
     * Retorna null para "prefiro não informar" e valores desconhecidos.
     */
    function pl_pcd_key(string $value): ?string
    {
        $c = pl_acess_norm($value);
        if ($c === '' || str_contains($c, 'nao informar') || str_contains($c, 'prefiro nao')) {
            return null;
        }
        if (str_contains($c, 'psicossocial')) {
            return 'psicossocial';
        }
        if (str_contains($c, 'intelectual') || str_contains($c, 'tea') || str_contains($c, 'cognitiv')) {
            return 'intelectual';
        }
        if (str_contains($c, 'visual') || str_contains($c, 'ceg')) {
            return 'visual';
        }
        if (str_contains($c, 'auditiv') || str_contains($c, 'surdo')) {
            return 'auditiva';
        }
        if (str_contains($c, 'mobilidade')) {
            return 'mobilidade';
        }
        if (str_contains($c, 'fisic') || str_contains($c, 'motor') || str_contains($c, 'cadeir')) {
            return 'motora';
        }
        if (str_contains($c, 'outra') || str_contains($c, 'outro')) {
            return 'outra';
        }
        return null;
    }
}

if (!function_exists('pl_pcd_label')) {
    function pl_pcd_label(string $key): string
    {
        $labels = [
            'motora'       => 'Física/motora',
            'visual'       => 'Visual',
            'auditiva'     => 'Auditiva',
            'intelectual'  => 'Intelectual/TEA',
            'psicossocial' => 'Psicossocial',
            'mobilidade'   => 'Mobilidade Reduzida',
            'outra'        => 'Outra',
        ];
        return $labels[$key] ?? $key;
    }
}

if (!function_exists('pl_pcd_keys')) {
    /**
     * Normaliza uma lista (rótulos ou chaves) para chaves canônicas,
     * sem duplicatas e na ordem usada pelo formulário.
     */
    function pl_pcd_keys(array $values): array
    {
        $found = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }
            $key = pl_pcd_key($value);
            if ($key !== null) {
                $found[$key] = true;
            }
        }
        $order = ['motora', 'visual', 'auditiva', 'intelectual', 'psicossocial', 'mobilidade', 'outra'];
        return array_values(array_filter($order, static fn(string $key): bool => isset($found[$key])));
    }
}

if (!function_exists('pl_pcd_labels')) {
    /**
     * Converte rótulos ou chaves para os rótulos gravados no cadastro.
     */
    function pl_pcd_labels(array $values): array
    {
        return array_map('pl_pcd_label', pl_pcd_keys($values));
    }
}

if (!function_exists('pl_pref_key')) {
    /**
     * Converte preferências antigas (saved_prefs) para as chaves atuais
     * do formulário "Preferências de Acessibilidade Urbana".
     */
    function pl_pref_key(string $value): ?string
    {
        $c = pl_acess_norm($value);
        if ($c === '') {
            return null;
        }
        if (str_contains($c, 'piso tatil') || $c === 'tatil' || str_contains($c, 'podotatil')) {
            return 'tatil';
        }
        if (str_contains($c, 'cadeira') || str_contains($c, 'cadeirante')
            || str_contains($c, 'embarque') || str_contains($c, 'plataforma')) {
            return 'cadeira-integral';
        }
        if (str_contains($c, 'sem escada') || $c === 'escadas') {
            return 'escadas';
        }
        if (str_contains($c, 'elevador')) {
            return 'elevadores';
        }
        if (str_contains($c, 'banheiro')) {
            return 'banheiros';
        }
        if (str_contains($c, 'rampa') || str_contains($c, 'calcada')) {
            return 'rampas';
        }
        if (str_contains($c, 'vaga') || str_contains($c, 'estacionamento')) {
            return 'vagas';
        }
        if (str_contains($c, 'libras') || str_contains($c, 'auditiv') || str_contains($c, 'sinalizacao visual')) {
            return 'auditiva';
        }
        if (str_contains($c, 'visual') || str_contains($c, 'braille') || str_contains($c, 'semaforo sonoro')) {
            return 'visual';
        }
        return null;
    }
}

if (!function_exists('pl_pref_keys')) {
    function pl_pref_keys(array $values): array
    {
        $found = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }
            $key = pl_pref_key($value);
            if ($key !== null) {
                $found[$key] = true;
            }
        }
        $order = ['escadas', 'rampas', 'elevadores', 'banheiros', 'tatil', 'visual', 'auditiva', 'vagas', 'cadeira-integral'];
        return array_values(array_filter($order, static fn(string $key): bool => isset($found[$key])));
    }
}
