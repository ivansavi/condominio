<?php
$u = usuario();
// Admin pode consultar o histórico de uma unidade: ?unidade=ID
$uid = $u['unidade_id'];
$un = null;
if (eh_admin() && get('unidade')) { $uid = (int)get('unidade'); }
if ($uid) $un = linha('SELECT * FROM unidades WHERE id = ?', [$uid]);
pagina_inicio('Histórico financeiro', eh_admin() ? ['voltar' => url('unidades')] : []);
if (!$un) { echo '<div class="card"><p>Seu cadastro ainda não está ligado a uma unidade. Fale com o síndico ou a administração.</p></div>'; pagina_fim(); return; }

$cobs = linhas('SELECT * FROM cobrancas WHERE unidade_id = ? AND status <> "cancelado" ORDER BY vencimento DESC', [$uid]);
$aberto = 0; $pago = 0; $venc = 0;
foreach ($cobs as $c) {
    if ($c['status'] === 'pago') $pago += (float)($c['valor_pago'] ?? $c['valor']);
    else { $t = cobranca_atualizada($c)['total']; $aberto += $t; if (cobranca_situacao($c) === 'vencido') $venc += $t; }
}
$filtro = get('f');
?>
<p class="muted"><?= e(nome_unidade($un)) ?></p>
<div class="grade-2">
  <div class="card kpi"><p class="rotulo">Em aberto</p><p class="grande <?= $venc ? 'vermelho' : '' ?>"><?= dinheiro($aberto) ?></p></div>
  <div class="card kpi"><p class="rotulo">Total já pago</p><p class="grande verde"><?= dinheiro($pago) ?></p></div>
</div>
<div class="abas">
  <?php foreach (['' => 'Todos', 'aberto' => 'Em aberto', 'pago' => 'Pagos'] as $k => $r): ?>
    <a class="aba <?= $filtro === $k ? 'ativa' : '' ?>" href="<?= e(url('boletos', ['f' => $k, 'unidade' => $uid])) ?>"><?= $r ?></a>
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
      <span class="muted pequeno d-bloco"><?= e($c['descricao']) ?> · venc. <?= data_br($c['vencimento']) ?></span>
      <?php if ($c['status'] === 'pago'): ?><span class="muted pequeno d-bloco">Pago em <?= data_br($c['data_pagamento']) ?></span><?php endif; ?></div>
    <div class="dir"><strong><?= dinheiro($c['status'] === 'pago' ? ($c['valor_pago'] ?? $c['valor']) : $c['valor']) ?></strong><?= selo($c) ?></div>
  </a>
<?php }
if (!$n) echo '<div class="card vazio">Nenhum boleto por aqui ainda.</div>';
pagina_fim();
