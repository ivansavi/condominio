<?php
$id = (int)get('id');
$c = $id ? linha('SELECT c.*, un.bloco, un.numero FROM cobrancas c JOIN unidades un ON un.id = c.unidade_id WHERE c.id = ?', [$id]) : null;
if ($id && !$c) { flash('Cobrança não encontrada.', 'erro'); redirect('cobrancas'); }
$unidades = linhas('SELECT * FROM unidades WHERE ativo = 1 ORDER BY bloco, CAST(numero AS UNSIGNED), numero');

if (eh_post()) {
    $acao = post('acao', 'salvar');
    try {
        if ($c && $acao === 'baixa') {
            $data = post('data_pagamento'); $vp = ler_dinheiro(post('valor_pago'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || $vp <= 0) throw new RuntimeException('Informe a data e o valor recebido.');
            q("UPDATE cobrancas SET status='pago', data_pagamento=?, valor_pago=?, forma_pagamento=? WHERE id=?", [$data, $vp, post('forma_pagamento') ?: null, $id]);
            flash('Pagamento registrado.'); redirect('cobranca', ['id' => $id]);
        }
        if ($c && $acao === 'estornar') {
            q("UPDATE cobrancas SET status='aberto', data_pagamento=NULL, valor_pago=NULL, forma_pagamento=NULL WHERE id=?", [$id]);
            flash('Baixa desfeita.'); redirect('cobranca', ['id' => $id]);
        }
        if ($c && $acao === 'cancelar') {
            q("UPDATE cobrancas SET status='cancelado' WHERE id=?", [$id]);
            flash('Cobrança cancelada.'); redirect('cobrancas', ['c' => substr($c['competencia'], 0, 7)]);
        }
        $comp = competencia_valida(post('competencia')); $venc = post('vencimento'); $valor = ler_dinheiro(post('valor'));
        $unidade = $c ? (int)$c['unidade_id'] : (int)post('unidade_id');
        if (!$comp || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $venc) || $valor <= 0 || !$unidade) throw new RuntimeException('Preencha unidade, mês, vencimento e valor.');
        $arq = salvar_upload('arquivo');
        $campos = [$comp, post('descricao') ?: 'Taxa de condomínio', $valor, $venc, post('linha_digitavel') ?: null, post('pix_copia_cola') ?: null, post('observacao') ?: null];
        if ($c) {
            q('UPDATE cobrancas SET competencia=?, descricao=?, valor=?, vencimento=?, linha_digitavel=?, pix_copia_cola=?, observacao=? WHERE id=?', [...$campos, $id]);
            if ($arq) q('UPDATE cobrancas SET arquivo_id=? WHERE id=?', [$arq, $id]);
        } else {
            q('INSERT INTO cobrancas (competencia, descricao, valor, vencimento, linha_digitavel, pix_copia_cola, observacao, unidade_id, arquivo_id) VALUES (?,?,?,?,?,?,?,?,?)', [...$campos, $unidade, $arq]);
            $id = (int)db()->lastInsertId();
        }
        flash('Cobrança salva.'); redirect('cobranca', ['id' => $id]);
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'erro'); }
}
pagina_inicio($c ? 'Cobrança' : 'Cobrança avulsa', ['voltar' => url('cobrancas')]);
?>
<?php if ($c): ?>
  <div class="card item"><div><strong><?= e(nome_unidade($c)) ?></strong><span class="muted pequeno d-bloco"><?= e(mes_ano($c['competencia'])) ?></span></div><?= selo($c) ?></div>
  <a class="btn btn-sec" href="<?= e(url('boleto', ['id' => $c['id']])) ?>">👁️ Ver como o morador vê</a>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form card"><?= csrf_campo() ?>
  <?php if (!$c): ?>
    <label>Unidade<select name="unidade_id" required><option value="">Escolha…</option>
      <?php foreach ($unidades as $un): ?><option value="<?= $un['id'] ?>"><?= e(nome_unidade($un)) ?></option><?php endforeach; ?></select></label>
  <?php endif; ?>
  <label>Mês de referência<input type="month" name="competencia" value="<?= e(substr($c['competencia'] ?? date('Y-m-01'), 0, 7)) ?>" required></label>
  <label>Descrição<input name="descricao" value="<?= e($c['descricao'] ?? 'Taxa de condomínio') ?>"></label>
  <label>Valor (R$)<input name="valor" inputmode="decimal" value="<?= $c ? number_format((float)$c['valor'], 2, ',', '.') : '' ?>" required></label>
  <label>Vencimento<input type="date" name="vencimento" value="<?= e($c['vencimento'] ?? '') ?>" required></label>
  <h3>Dados de pagamento</h3>
  <label>PDF/imagem do boleto (do seu banco)<input type="file" name="arquivo" accept="application/pdf,image/jpeg,image/png"></label>
  <?php if (!empty($c['arquivo_id'])): ?><p class="pequeno"><a href="<?= e(url('arquivo', ['id' => $c['arquivo_id']])) ?>" target="_blank" rel="noopener">📄 Arquivo atual anexado</a></p><?php endif; ?>
  <label>Linha digitável<input name="linha_digitavel" value="<?= e($c['linha_digitavel'] ?? '') ?>" inputmode="numeric"></label>
  <label>Pix copia e cola<textarea name="pix_copia_cola" rows="3"><?= e($c['pix_copia_cola'] ?? '') ?></textarea></label>
  <label>Observação para o morador<input name="observacao" value="<?= e($c['observacao'] ?? '') ?>"></label>
  <button class="btn btn-grande">Salvar</button>
</form>

<?php if ($c && $c['status'] === 'aberto'): ?>
<form method="post" class="form card"><?= csrf_campo() ?><input type="hidden" name="acao" value="baixa">
  <h3>Registrar pagamento (dar baixa)</h3>
  <label>Data do pagamento<input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>" required></label>
  <label>Valor recebido (R$)<input name="valor_pago" inputmode="decimal" value="<?= number_format(cobranca_atualizada($c)['total'], 2, ',', '.') ?>" required></label>
  <label>Forma<select name="forma_pagamento"><option>Boleto</option><option>Pix</option><option>Dinheiro</option><option>Transferência</option></select></label>
  <button class="btn">Confirmar pagamento</button>
</form>
<?php elseif ($c && $c['status'] === 'pago'): ?>
<form method="post" data-confirmar="Desfazer a baixa deste pagamento?"><?= csrf_campo() ?><input type="hidden" name="acao" value="estornar"><button class="btn btn-sec">↩️ Desfazer baixa</button></form>
<?php endif;
if ($c && $c['status'] !== 'cancelado'): ?>
<form method="post" data-confirmar="Cancelar esta cobrança?"><?= csrf_campo() ?><input type="hidden" name="acao" value="cancelar"><button class="btn btn-perigo">Cancelar cobrança</button></form>
<?php endif;
pagina_fim();
