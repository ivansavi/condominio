<?php
$u = usuario();
$param = (int)get('unidade');
if ($param && (pode('financeiro_ver') || pode('cobrancas') || pode('moradores'))) {
    $un = unidade_do_condominio($param);
    $unidades = $un ? [$un] : [];
    $voltar = url('unidades');
} else {
    $unidades = unidades_do_usuario();
    $voltar = '';
}
pagina_inicio('Histórico financeiro', $voltar ? ['voltar' => $voltar] : []);
if (!$unidades) { echo '<div class="card"><p>Você ainda não tem unidade neste condomínio. Fale com o síndico ou a administração.</p></div>'; pagina_fim(); return; }
$ids = implode(',', array_map('intval', array_column($unidades, 'id')));
$nomes = array_column($unidades, null, 'id');
$cobs = linhas("SELECT * FROM cobrancas WHERE unidade_id IN ($ids) AND status <> 'cancelado' ORDER BY vencimento DESC");
$aberto = 0; $pago = 0; $venc = 0;
foreach ($cobs as $c) {
    if ($c['status'] === 'pago') $pago += (float)($c['valor_pago'] ?? $c['valor']);
    else { $t = cobranca_atualizada($c)['total']; $aberto += $t; if (cobranca_situacao($c) === 'vencido') $venc += $t; }
}
$filtro = get('f');
?>
<p class="muted"><?= e(implode(' · ', array_map('nome_unidade', $unidades))) ?></p>
<div class="grade-2">
  <div class="card kpi"><p class="rotulo">Em aberto</p><p class="grande <?= $venc ? 'vermelho' : '' ?>"><?= dinheiro($aberto) ?></p></div>
  <div class="card kpi"><p class="rotulo">Total já pago</p><p class="grande verde"><?= dinheiro($pago) ?></p></div>
</div>
<div class="abas">
  <?php foreach (['' => 'Todos', 'aberto' => 'Em aberto', 'pago' => 'Pagos'] as $k => $r): ?>
    <a class="aba <?= $filtro === $k ? 'ativa' : '' ?>" href="<?= e(url('boletos', array_filter(['f' => $k, 'unidade' => $param ?: null]))) ?>"><?= $r ?></a>
  <?php endforeach; ?>
</div>
<?php
$n = 0;
foreach ($cobs as $c) {
    if ($filtro === 'pago' && $c['status'] !== 'pago') continue;
    if ($filtro === 'aberto' && $c['status'] !== 'aberto') continue;
    $n++; ?>
  <a class="card item cobranca" href="<?= e(url('boleto', ['id' => $c['id']])) ?>">
    <div><strong><?= e(mes_ano($c['competencia'])) ?></strong>
      <span class="muted pequeno d-bloco"><?= e($c['descricao']) ?><?= count($unidades) > 1 ? ' · ' . e(nome_unidade($nomes[$c['unidade_id']])) : '' ?> · venc. <?= data_br($c['vencimento']) ?></span>
      <?php if ($c['status'] === 'pago'): ?><span class="muted pequeno d-bloco">Pago em <?= data_br($c['data_pagamento']) ?></span><?php endif; ?></div>
    <div class="dir"><strong><?= dinheiro($c['status'] === 'pago' ? ($c['valor_pago'] ?? $c['valor']) : $c['valor']) ?></strong><?= selo($c) ?></div>
  </a>
<?php }
if (!$n) echo '<div class="card vazio">Nenhum boleto por aqui ainda.</div>';
pagina_fim();
