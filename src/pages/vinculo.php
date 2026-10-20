<?php
$un = linha('SELECT * FROM unidades WHERE id = ?', [(int)get('unidade')]);
if (!$un) redirect('unidades');
$id = (int)get('id');
$v = $id ? linha('SELECT * FROM unidade_vinculos WHERE id = ? AND unidade_id = ?', [$id, $un['id']]) : null;
if ($id && !$v) redirect('unidade', ['id' => $un['id']]);
$tipo = $v['tipo'] ?? (get('tipo') === 'locatario' ? 'locatario' : 'proprietario');
$rotulo = $tipo === 'locatario' ? 'locatário' : 'proprietário';
$volta = url('unidade', ['id' => $un['id']]);

if (eh_post()) {
    if ($v && post('acao') === 'excluir') {
        q('DELETE FROM unidade_vinculos WHERE id = ?', [$v['id']]);
        registrar_historico('unidade', (int)$un['id'], [ucfirst($rotulo) => [$v['nome'], 'Removido do histórico']]);
        flash(ucfirst($rotulo) . ' removido.'); redirect('unidade', ['id' => $un['id']]);
    }
    $nome = post('nome'); $ini = data_valida(post('data_inicio')); $fim = data_valida(post('data_fim'));
    if ($nome === '') flash('Informe o nome.', 'erro');
    elseif ($ini && $fim && $fim < $ini) flash('A data final não pode ser anterior à data inicial.', 'erro');
    else {
        $dados = [$nome, post('cpf') ?: null, post('telefone') ?: null, strtolower(post('email')) ?: null, $ini, $fim, post('observacao') ?: null];
        if ($v) {
            q('UPDATE unidade_vinculos SET nome=?, cpf=?, telefone=?, email=?, data_inicio=?, data_fim=?, observacao=? WHERE id=?', [...$dados, $v['id']]);
            $dif = diferencas(
                ['Nome' => $v['nome'], 'Início' => $v['data_inicio'] ? data_br($v['data_inicio']) : '', 'Fim' => $v['data_fim'] ? data_br($v['data_fim']) : ''],
                ['Nome' => $nome, 'Início' => $ini ? data_br($ini) : '', 'Fim' => $fim ? data_br($fim) : '']);
            registrar_historico('unidade', (int)$un['id'], array_combine(array_map(fn($k) => ucfirst($rotulo) . ' · ' . $k, array_keys($dif)), array_values($dif)));
        } else {
            if (!$fim && post('encerrar_atual') && $ini) q('UPDATE unidade_vinculos SET data_fim = ? WHERE unidade_id = ? AND tipo = ? AND data_fim IS NULL', [$ini, $un['id'], $tipo]);
            q('INSERT INTO unidade_vinculos (tipo, nome, cpf, telefone, email, data_inicio, data_fim, observacao, unidade_id) VALUES (?,?,?,?,?,?,?,?,?)', [$tipo, ...$dados, $un['id']]);
            registrar_historico('unidade', (int)$un['id'], [ucfirst($rotulo) => ['', "Adicionado: $nome" . ($ini ? ' (desde ' . data_br($ini) . ')' : '')]]);
        }
        flash('Dados salvos.'); redirect('unidade', ['id' => $un['id']]);
    }
    $v = array_merge($v ?? [], ['nome' => $nome, 'cpf' => post('cpf'), 'telefone' => post('telefone'), 'email' => post('email'), 'data_inicio' => $ini, 'data_fim' => $fim, 'observacao' => post('observacao')]);
}
pagina_inicio(($v && $id ? 'Editar ' : 'Novo ') . $rotulo, ['voltar' => $volta]);
?>
<p class="muted"><?= e(nome_unidade($un)) ?></p>
<form method="post" class="form card"><?= csrf_campo() ?>
  <label>Nome completo<input name="nome" value="<?= e($v['nome'] ?? '') ?>" required></label>
  <div class="duas">
    <label><?= $tipo === 'locatario' ? 'Início da locação' : 'Data da compra' ?><input type="date" name="data_inicio" value="<?= e($v['data_inicio'] ?? '') ?>"></label>
    <label><?= $tipo === 'locatario' ? 'Fim da locação' : 'Data da venda' ?><input type="date" name="data_fim" value="<?= e($v['data_fim'] ?? '') ?>"></label>
  </div>
  <label>CPF<input name="cpf" value="<?= e($v['cpf'] ?? '') ?>" inputmode="numeric"></label>
  <label>Telefone<input type="tel" name="telefone" value="<?= e($v['telefone'] ?? '') ?>" inputmode="tel"></label>
  <label>E-mail<input type="email" name="email" value="<?= e($v['email'] ?? '') ?>" inputmode="email"></label>
  <label>Observação<input name="observacao" value="<?= e($v['observacao'] ?? '') ?>"></label>
  <?php if (!$id): ?><label class="check"><input type="checkbox" name="encerrar_atual" value="1" checked> Encerrar o <?= $rotulo ?> atual na data de início deste</label><?php endif; ?>
  <p class="muted pequeno">Deixe a data final vazia para quem ainda é o <?= $rotulo ?> atual. Para dar acesso ao aplicativo, cadastre a pessoa em Moradores.</p>
  <button class="btn btn-grande">Salvar</button>
</form>
<?php if ($id): ?>
  <form method="post" data-confirmar="Remover este registro do histórico?"><?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><button class="btn btn-perigo">Remover registro</button></form>
<?php endif;
pagina_fim();
