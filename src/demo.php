<?php
// Dados de demonstração (somente para ambiente de teste). Exige banco instalado e sem unidades.
function carregar_demo(): void {
    if ((int)valor('SELECT COUNT(*) FROM unidades') > 0) return;
    conf_set('condominio_cnpj', '12.345.678/0001-90'); conf_set('condominio_endereco', 'Rua das Palmeiras, 100 - Centro - São Paulo/SP'); conf_set('condominio_telefone', '(11) 3333-4444');
    conf_set('pix_chave', 'pix@residencialdemo.com.br'); conf_set('saldo_inicial', '15000');
    $hash = password_hash('demo1234', PASSWORD_DEFAULT);
    q('UPDATE usuarios SET senha_hash = ? WHERE super_admin = 1', [$hash]);
    $nivel = fn(int $cond, string $nome) => (int)valor('SELECT id FROM niveis_acesso WHERE condominio_id = ? AND nome = ?', [$cond, $nome]);
    $novo_user = function (string $nome, string $email) use ($hash): int {
        q('INSERT INTO usuarios (nome, email, senha_hash, telefone, whatsapp) VALUES (?,?,?,?,?)', [$nome, $email, $hash, '(11) 3333-4444', '(11) 99999-8888']);
        return (int)db()->lastInsertId();
    };
    $unidade = function (int $cond, string $bloco, string $num, float $valor): int {
        q('INSERT INTO unidades (condominio_id, bloco, numero, valor_condominio) VALUES (?,?,?,?)', [$cond, $bloco, $num, $valor]);
        return (int)db()->lastInsertId();
    };
    $vincular = function (int $uid, int $un, string $tipo, ?string $ini) {
        $u = linha('SELECT * FROM usuarios WHERE id = ?', [$uid]);
        q('INSERT INTO unidade_vinculos (unidade_id, tipo, nome, cpf, telefone, email, data_inicio, usuario_id) VALUES (?,?,?,?,?,?,?,?)', [$un, $tipo, $u['nome'], $u['cpf'], $u['telefone'], $u['email'], $ini, $uid]);
    };

    // Condomínio 1 (criado na instalação)
    $u1 = []; foreach ([['A', '101'], ['A', '102'], ['A', '201'], ['A', '202'], ['B', '101'], ['B', '102']] as [$b, $n]) $u1[] = $unidade(1, $b, $n, 480);
    $maria = $novo_user('Maria Souza', 'condomino@demo.com');
    $vincular($maria, $u1[0], 'proprietario', '2020-03-15');
    $vincular($novo_user('Carlos Lima', 'carlos@demo.com'), $u1[1], 'locatario', '2024-02-01');
    $vincular($novo_user('Ana Ribeiro', 'ana@demo.com'), $u1[2], 'proprietario', '2018-06-10');
    $vincular($novo_user('Paulo Nunes', 'paulo@demo.com'), $u1[3], 'proprietario', '2021-09-01');
    q('INSERT INTO unidade_vinculos (unidade_id, tipo, nome, data_inicio, data_fim) VALUES (?,?,?,?,?)', [$u1[0], 'proprietario', 'Antônio Pereira (proprietário anterior)', '2010-01-10', '2020-03-14']);
    q('INSERT INTO acessos (usuario_id, condominio_id, nivel_id) VALUES (?,?,?)', [$novo_user('Roberto Alves', 'sindico@demo.com'), 1, $nivel(1, 'Síndico / Administrador')]);
    q('INSERT INTO acessos (usuario_id, condominio_id, nivel_id) VALUES (?,?,?)', [$novo_user('Gestão Demo', 'gestao@demo.com'), 1, $nivel(1, 'Gestão')]);

    $hoje = date('Y-m-01');
    foreach ([-2, -1, 0] as $off) {
        $comp = date('Y-m-01', strtotime("$hoje $off month")); $venc = date('Y-m-10', strtotime($comp));
        foreach ($u1 as $i => $uid) {
            $pago = $off < 0 && $i != 3 || ($off == 0 && $i <= 1);
            q('INSERT INTO cobrancas (unidade_id,competencia,valor,vencimento,status,data_pagamento,valor_pago,forma_pagamento,linha_digitavel,pix_copia_cola) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$uid, $comp, 480, $venc, $pago ? 'pago' : 'aberto', $pago ? date('Y-m-08', strtotime($comp)) : null, $pago ? 480 : null, $pago ? 'Pix' : null,
                 '34191.09008 61207.727308 71444.640008 8 ' . random_int(10000000000000, 99999999999999), '00020126580014BR.GOV.BCB.PIX0136pix@residencialdemo.com.br5204000053039865406480.00']);
        }
        q('INSERT INTO prestacoes (condominio_id, competencia, publicada, publicada_em, observacao) VALUES (1,?,?,NOW(),?)', [$comp, $off < 0 ? 1 : 0, $off < 0 ? 'Prezados, segue a prestação de contas do mês. Qualquer dúvida procurem a administração.' : null]);
        foreach ([['Folha e encargos', 'Salários porteiros e zelador', 6200], ['Energia elétrica', 'Conta de luz - áreas comuns', 1350.4], ['Água e esgoto', 'Conta de água', 2480.9], ['Limpeza', 'Material de limpeza', 420], ['Manutenção', 'Manutenção do portão eletrônico', 690], ['Elevadores', 'Contrato de manutenção', 980]] as [$cat, $d, $v]) {
            q("INSERT INTO lancamentos (condominio_id,competencia,tipo,categoria,descricao,valor,data) VALUES (1, ?, 'despesa', ?, ?, ?, ?)", [$comp, $cat, $d, $v, date('Y-m-05', strtotime($comp))]);
        }
    }
    q('INSERT INTO comunicados (condominio_id,titulo,corpo,importante,publicado_em) VALUES (1,?,?,?,NOW() - INTERVAL 1 DAY)', ['Assembleia geral ordinária', "Convocamos todos os condôminos para a assembleia no dia 30, às 19h, no salão de festas.\nPauta: prestação de contas e eleição de síndico.", 1]);
    q('INSERT INTO comunicados (condominio_id,titulo,corpo,importante,publicado_em) VALUES (1,?,?,?,NOW() - INTERVAL 5 DAY)', ['Manutenção da caixa d\'água', 'Na terça-feira o fornecimento de água será interrompido das 9h às 12h.', 0]);

    // Condomínio 2: a mesma Maria tem uma unidade aqui, com o mesmo login
    q('INSERT INTO condominios (nome, cnpj, endereco, pix_chave) VALUES (?,?,?,?)', ['Residencial Jardins', '98.765.432/0001-10', 'Av. dos Ipês, 500 - Campinas/SP', 'jardins@demo.com']);
    $c2 = (int)db()->lastInsertId(); semear_niveis($c2);
    $u2 = [$unidade($c2, '', '11', 390), $unidade($c2, '', '12', 390), $unidade($c2, '', '21', 390)];
    $vincular($maria, $u2[0], 'proprietario', '2022-05-20');
    $vincular($novo_user('Julia Campos', 'julia@demo.com'), $u2[1], 'proprietario', '2019-01-15');
    q('INSERT INTO acessos (usuario_id, condominio_id, nivel_id) SELECT id, ?, ? FROM usuarios WHERE email = ?', [$c2, $nivel($c2, 'Gestão'), 'gestao@demo.com']);
    foreach ([-1, 0] as $off) foreach ($u2 as $i => $uid) {
        $comp = date('Y-m-01', strtotime("$hoje $off month"));
        q('INSERT INTO cobrancas (unidade_id,competencia,valor,vencimento,status,data_pagamento,valor_pago,forma_pagamento) VALUES (?,?,?,?,?,?,?,?)',
            [$uid, $comp, 390, date('Y-m-10', strtotime($comp)), $off < 0 ? 'pago' : 'aberto', $off < 0 ? date('Y-m-09', strtotime($comp)) : null, $off < 0 ? 390 : null, $off < 0 ? 'Boleto' : null]);
    }
    q('INSERT INTO comunicados (condominio_id,titulo,corpo,importante) VALUES (?,?,?,0)', [$c2, 'Bem-vindos ao Residencial Jardins', 'Este é o canal oficial de avisos do condomínio.']);
}
