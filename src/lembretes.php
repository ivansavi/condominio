<?php
declare(strict_types=1);

/** Cobranças que ainda não receberam cada tipo de lembrete. */
function regras_lembrete(): array {
    $dias = max(1, (int)conf('lembrete_dias_antes', '3'));
    return [
        'a_vencer' => ["c.vencimento >= CURDATE() AND c.vencimento <= CURDATE() + INTERVAL $dias DAY", 'Lembrete: boleto vence em breve'],
        'vencido_1' => ['c.vencimento < CURDATE() AND c.vencimento >= CURDATE() - INTERVAL 14 DAY', 'Boleto vencido'],
        'vencido_15' => ['c.vencimento < CURDATE() - INTERVAL 14 DAY AND c.vencimento >= CURDATE() - INTERVAL 60 DAY', 'Boleto vencido há mais de 15 dias'],
    ];
}

function enviar_aviso_cobranca(array $c, string $tipo): int {
    usar_condominio((int)valor('SELECT condominio_id FROM unidades WHERE id = ?', [$c['unidade_id']]));
    $at = cobranca_atualizada($c);
    $ref = mes_ano($c['competencia']);
    $linhas = [
        'novo' => ["Novo boleto: $ref", "Seu boleto de $ref ({$c['descricao']}) já está disponível.\n\nValor: " . dinheiro($c['valor']) . "\nVencimento: " . data_br($c['vencimento'])],
        'a_vencer' => ["Lembrete: boleto de $ref vence em " . data_br($c['vencimento']), "Este é um lembrete amigável: o boleto de $ref ({$c['descricao']}) vence em " . data_br($c['vencimento']) . ".\n\nValor: " . dinheiro($c['valor'])],
        'vencido_1' => ["Boleto de $ref vencido", "O boleto de $ref ({$c['descricao']}) venceu em " . data_br($c['vencimento']) . " e ainda consta em aberto.\n\nValor atualizado hoje (multa e juros): " . dinheiro($at['total']) . "\n\nSe já pagou, desconsidere esta mensagem."],
        'vencido_15' => ["Boleto de $ref em atraso", "O boleto de $ref ({$c['descricao']}) está em atraso desde " . data_br($c['vencimento']) . ".\n\nValor atualizado hoje (multa e juros): " . dinheiro($at['total']) . "\n\nPara regularizar, acesse o boleto no aplicativo. Se já pagou, desconsidere."],
    ][$tipo];
    $enviados = 0;
    foreach (emails_condominos((int)$c['unidade_id']) as $d) {
        if (enviar_email($d['email'], $linhas[0], "Olá, {$d['nome']}!\n\n" . $linhas[1], ['tipo' => 'cobranca_' . $tipo, 'botao' => ['Ver boleto', link_app('boleto', ['id' => $c['id']])]])) $enviados++;
    }
    return $enviados;
}

/** Envia os lembretes pendentes (uma vez por cobrança e tipo), de um condomínio ou de todos. Retorna contagens por tipo. */
function processar_lembretes(?int $condominio = null): array {
    @set_time_limit(300);
    $res = [];
    foreach (regras_lembrete() as $tipo => [$filtro]) {
        $cobs = linhas("SELECT c.* FROM cobrancas c WHERE c.status = 'aberto' AND $filtro AND NOT EXISTS (SELECT 1 FROM lembretes_enviados l WHERE l.cobranca_id = c.id AND l.tipo = ?)"
            . ($condominio ? ' AND c.unidade_id IN (SELECT id FROM unidades WHERE condominio_id = ' . (int)$condominio . ')' : ''), [$tipo]);
        $env = 0; $sem = 0;
        foreach ($cobs as $c) {
            if (!emails_condominos((int)$c['unidade_id'])) { $sem++; continue; }
            if (enviar_aviso_cobranca($c, $tipo) > 0) q('INSERT IGNORE INTO lembretes_enviados (cobranca_id, tipo) VALUES (?,?)', [$c['id'], $tipo]);
            $env++;
        }
        $res[$tipo] = ['cobrancas' => $env, 'sem_destinatario' => $sem];
    }
    return $res;
}
