<?php
$u = usuario();
$c = linha('SELECT c.*, un.bloco, un.numero FROM cobrancas c JOIN unidades un ON un.id = c.unidade_id WHERE c.id = ? AND un.condominio_id = ?', [(int)get('id'), cid()]);
$pode_ver = $c && (in_array((int)$c['unidade_id'], ids_unidades_do_usuario(), true) || pode('financeiro_ver') || pode('cobrancas') || pode('moradores'));
if (!$pode_ver) { http_response_code(404); pagina_inicio('Boleto'); echo '<div class="card">Boleto não encontrado.</div>'; pagina_fim(); return; }
$at = cobranca_atualizada($c); $sit = cobranca_situacao($c);
$resp = linha("SELECT nome FROM unidade_vinculos WHERE unidade_id = ? AND (data_fim IS NULL OR data_fim >= CURDATE()) ORDER BY tipo = 'proprietario' DESC, id LIMIT 1", [$c['unidade_id']]);
pagina_inicio('Boleto', ['voltar' => url(pode('cobrancas') ? 'cobrancas' : 'boletos')]);
?>
<article class="card boleto">
  <header>
    <div><p class="rotulo"><?= e(conf('condominio_nome', 'Condomínio')) ?></p>
    <p class="muted pequeno"><?= e(conf('condominio_cnpj')) ? 'CNPJ ' . e(conf('condominio_cnpj')) : '' ?></p></div>
    <?= selo($c) ?>
  </header>
  <dl>
    <dt>Referência</dt><dd><?= e($c['descricao']) ?> — <?= e(mes_ano($c['competencia'])) ?></dd>
    <dt>Unidade</dt><dd><?= e(nome_unidade($c)) ?><?= $resp ? ' · ' . e($resp['nome']) : '' ?></dd>
    <dt>Vencimento</dt><dd><strong><?= data_br($c['vencimento']) ?></strong></dd>
    <dt>Valor do documento</dt><dd><?= dinheiro($c['valor']) ?></dd>
    <?php if ($sit === 'vencido'): ?>
      <dt>Multa (<?= e(conf('multa_pct', '2')) ?>%)</dt><dd><?= dinheiro($at['multa']) ?></dd>
      <dt>Juros (<?= $at['dias'] ?> dias)</dt><dd><?= dinheiro($at['juros']) ?></dd>
      <dt>Total atualizado hoje</dt><dd class="vermelho"><strong><?= dinheiro($at['total']) ?></strong></dd>
    <?php endif; ?>
    <?php if ($c['status'] === 'pago'): ?>
      <dt>Pago em</dt><dd><?= data_br($c['data_pagamento']) ?> · <?= dinheiro($c['valor_pago'] ?? $c['valor']) ?><?= $c['forma_pagamento'] ? ' (' . e($c['forma_pagamento']) . ')' : '' ?></dd>
    <?php endif; ?>
  </dl>

  <?php if ($c['status'] === 'aberto'): ?>
    <?php if ($c['linha_digitavel']): ?>
      <div class="copiavel"><p class="rotulo">Linha digitável</p><code id="linha"><?= e($c['linha_digitavel']) ?></code>
      <button class="btn btn-sec" type="button" data-copiar="#linha">Copiar código</button></div>
    <?php endif; ?>
    <?php if ($c['pix_copia_cola']): ?>
      <div class="copiavel"><p class="rotulo">Pix copia e cola</p><code id="pix"><?= e($c['pix_copia_cola']) ?></code>
      <button class="btn btn-sec" type="button" data-copiar="#pix">Copiar Pix</button></div>
    <?php elseif (conf('pix_chave')): ?>
      <div class="copiavel"><p class="rotulo">Chave Pix do condomínio</p><code id="pixchave"><?= e(conf('pix_chave')) ?></code>
      <button class="btn btn-sec" type="button" data-copiar="#pixchave">Copiar chave</button></div>
    <?php endif; ?>
    <?php if ($c['arquivo_id']): ?>
      <a class="btn" href="<?= e(url('arquivo', ['id' => $c['arquivo_id']])) ?>" target="_blank" rel="noopener">📄 Abrir boleto (PDF)</a>
    <?php elseif (!$c['linha_digitavel'] && !$c['pix_copia_cola'] && !conf('pix_chave')): ?>
      <p class="muted">O código de pagamento deste boleto ainda não foi disponibilizado. Fale com a administração.</p>
    <?php endif; ?>
  <?php endif; ?>
  <?php if ($c['observacao']): ?><p class="muted pequeno"><?= e($c['observacao']) ?></p><?php endif; ?>
  <button class="btn btn-sec nao-imprimir" type="button" data-imprimir>🖨️ Imprimir</button>
  <?php if (pode('cobrancas')): ?><a class="btn btn-sec nao-imprimir" href="<?= e(url('cobranca', ['id' => $c['id']])) ?>">✏️ Editar / dar baixa</a><?php endif; ?>
</article>
<?php pagina_fim();
