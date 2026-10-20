<?php
$comp = competencia_valida(get('c'));
$pr = $comp ? linha('SELECT * FROM prestacoes WHERE competencia = ?', [$comp]) : null;
if (!$pr || (!$pr['publicada'] && !eh_gestao())) { http_response_code(404); pagina_inicio('Prestação de contas', ['voltar' => url('prestacoes')]); echo '<div class="card">Prestação não encontrada.</div>'; pagina_fim(); return; }
$r = resumo_mes($comp);
$anexos = linhas('SELECT * FROM prestacao_anexos WHERE competencia = ? ORDER BY id', [$comp]);
pagina_inicio(mes_ano($comp), ['voltar' => url('prestacoes')]);
if (!$pr['publicada']): ?><div class="alerta alerta-aviso">Rascunho: os condôminos ainda não veem esta prestação.</div><?php endif; ?>
<section class="card resumo">
  <div class="linha-r"><span>Saldo anterior</span><strong><?= dinheiro($r['saldo_anterior']) ?></strong></div>
  <div class="linha-r"><span>Receitas</span><strong class="verde">+ <?= dinheiro($r['receitas']) ?></strong></div>
  <div class="linha-r"><span>Despesas</span><strong class="vermelho">− <?= dinheiro($r['despesas']) ?></strong></div>
  <div class="linha-r total"><span>Saldo final</span><strong><?= dinheiro($r['saldo_final']) ?></strong></div>
</section>
<?php if ($pr['observacao']): ?><div class="card"><h3>Mensagem do síndico</h3><p><?= paragrafos($pr['observacao']) ?></p></div><?php endif; ?>

<h3 class="sec">Para onde foi o dinheiro</h3>
<?php if ($r['por_categoria']): $max = max($r['por_categoria']); ?>
  <div class="card">
  <?php foreach ($r['por_categoria'] as $cat => $v): ?>
    <div class="barra-item"><div class="barra-topo"><span><?= e($cat) ?></span><strong><?= dinheiro($v) ?></strong></div>
    <div class="barra"><i style="width:<?= max(2, round($v / $max * 100)) ?>%"></i></div></div>
  <?php endforeach; ?></div>
<?php else: echo '<div class="card vazio">Sem despesas lançadas.</div>'; endif; ?>

<h3 class="sec">Receitas do mês</h3>
<div class="card">
  <div class="linha-r"><span>Taxas de condomínio recebidas (<?= count($r['pagas']) ?> pagamentos)</span><strong><?= dinheiro($r['rec_condominio']) ?></strong></div>
  <?php foreach ($r['lancamentos'] as $l) if ($l['tipo'] === 'receita'): ?>
    <div class="linha-r"><span><?= e($l['descricao']) ?> <small class="muted">(<?= e($l['categoria']) ?>)</small></span><strong><?= dinheiro($l['valor']) ?></strong></div>
  <?php endif; ?>
</div>

<h3 class="sec">Despesas detalhadas</h3>
<div class="card">
<?php $tem = false; foreach ($r['lancamentos'] as $l) if ($l['tipo'] === 'despesa'): $tem = true; ?>
  <div class="linha-r"><span><?= e($l['descricao']) ?><small class="muted d-bloco"><?= data_br($l['data']) ?> · <?= e($l['categoria']) ?></small></span><strong><?= dinheiro($l['valor']) ?></strong></div>
<?php endif; if (!$tem) echo '<p class="muted">Nenhuma despesa.</p>'; ?>
</div>

<?php if ($anexos): ?>
  <h3 class="sec">Documentos</h3>
  <?php foreach ($anexos as $a): ?>
    <a class="card item" href="<?= e(url('arquivo', ['id' => $a['arquivo_id']])) ?>" target="_blank" rel="noopener"><span>📎 <?= e($a['titulo']) ?></span><span class="seta">›</span></a>
  <?php endforeach;
endif;
pagina_fim();
