<?php
// Dados de demonstração (somente para ambiente de teste). Exige banco já instalado e vazio de unidades.
function carregar_demo(): void {
    if ((int)valor('SELECT COUNT(*) FROM unidades') > 0) return;
    conf_set('condominio_cnpj', '12.345.678/0001-90'); conf_set('pix_chave', 'pix@residencialdemo.com.br'); conf_set('saldo_inicial', '15000');
    $hash = password_hash('demo1234', PASSWORD_DEFAULT);
    foreach ([['A', '101'], ['A', '102'], ['A', '201'], ['A', '202'], ['B', '101'], ['B', '102']] as [$b, $n]) {
        q('INSERT INTO unidades (bloco, numero, valor_condominio) VALUES (?,?,?)', [$b, $n, 480]);
    }
    $us = [
        ['Maria Souza', 'condomino@demo.com', 'condomino', 1, 'proprietario'], ['Carlos Lima', 'carlos@demo.com', 'condomino', 2, 'inquilino'],
        ['Ana Ribeiro', 'ana@demo.com', 'condomino', 3, 'proprietario'], ['Paulo Nunes', 'paulo@demo.com', 'condomino', 4, 'proprietario'],
        ['Roberto Alves (Síndico)', 'sindico@demo.com', 'sindico', null, null],
    ];
    foreach ($us as [$nome, $email, $perf, $un, $tipo]) {
        q('INSERT INTO usuarios (nome,email,perfil,unidade_id,tipo_morador,senha_hash,telefone,whatsapp) VALUES (?,?,?,?,?,?,?,?)', [$nome, $email, $perf, $un, $tipo, $hash, '(11) 3333-4444', '(11) 99999-8888']);
    }
    q("UPDATE usuarios SET senha_hash = ? WHERE perfil = 'gestao'", [$hash]);
    q("INSERT IGNORE INTO usuarios (nome,email,perfil,senha_hash) VALUES ('Gestão Demo','gestao@demo.com','gestao',?)", [$hash]);
    $hoje = date('Y-m-01');
    foreach ([-2, -1, 0] as $off) {
        $comp = date('Y-m-01', strtotime("$hoje $off month")); $venc = date('Y-m-10', strtotime($comp));
        foreach (linhas('SELECT id FROM unidades') as $u) {
            $pago = $off < 0 && $u['id'] != 4 || ($off == 0 && $u['id'] <= 2);
            q('INSERT INTO cobrancas (unidade_id,competencia,valor,vencimento,status,data_pagamento,valor_pago,forma_pagamento,linha_digitavel,pix_copia_cola) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$u['id'], $comp, 480, $venc, $pago ? 'pago' : 'aberto', $pago ? date('Y-m-08', strtotime($comp)) : null, $pago ? 480 : null, $pago ? 'Pix' : null,
                 '34191.09008 61207.727308 71444.640008 8 ' . random_int(10000000000000, 99999999999999), '00020126580014BR.GOV.BCB.PIX0136pix@residencialdemo.com.br5204000053039865406480.00']);
        }
        q('INSERT INTO prestacoes (competencia, publicada, publicada_em, observacao) VALUES (?,?,NOW(),?)', [$comp, $off < 0 ? 1 : 0, $off < 0 ? 'Prezados, segue a prestação de contas do mês. Qualquer dúvida procurem a administração.' : null]);
        foreach ([['Folha e encargos', 'Salários porteiros e zelador', 6200], ['Energia elétrica', 'Conta de luz - áreas comuns', 1350.4], ['Água e esgoto', 'Conta de água', 2480.9], ['Limpeza', 'Material de limpeza', 420], ['Manutenção', 'Manutenção do portão eletrônico', 690], ['Elevadores', 'Contrato de manutenção', 980]] as [$cat, $d, $v]) {
            q("INSERT INTO lancamentos (competencia,tipo,categoria,descricao,valor,data) VALUES (?, 'despesa', ?, ?, ?, ?)", [$comp, $cat, $d, $v, date('Y-m-05', strtotime($comp))]);
        }
    }
    q('INSERT INTO comunicados (titulo,corpo,importante,publicado_em) VALUES (?,?,?,NOW() - INTERVAL 1 DAY)', ['Assembleia geral ordinária', "Convocamos todos os condôminos para a assembleia no dia 30, às 19h, no salão de festas.\nPauta: prestação de contas e eleição de síndico.", 1]);
    q('INSERT INTO comunicados (titulo,corpo,importante,publicado_em) VALUES (?,?,?,NOW() - INTERVAL 5 DAY)', ['Manutenção da caixa d\'água', 'Na terça-feira o fornecimento de água será interrompido das 9h às 12h.', 0]);
}
