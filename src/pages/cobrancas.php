<?php
pagina_inicio('Cobranças');
$comp = competencia_valida(get('c', date('Y-m'))) ?? date('Y-m-01');
$st = get('s');
$sql = 'SELECT c.*, un.bloco, un.numero FROM cobrancas c JOIN unidades un ON un.id = c.unidade_id WHERE c.competencia = ?';
if ($st === 'aberto') $sql .= " AND c.status = 'aberto' AND c.vencimento >= CURDATE()";
elseif ($st === 'vencido') $sql .= " AND c.status = 'aberto' AND c.vencimento < CURDATE()";
elseif ($st === 'pago') $sql .= " AND c.status = 'pago'";
else $sql .= " AND c.status <> 'cancelado'";
$sql .= ' ORDER BY un.bloco, CAST(un.numero AS UNSIGNED), c.id';
$cobs = linhas($sql, [$comp]);
$tot = linha("SELECT COALESCE(SUM(valor),0) AS emitido, COALESCE(SUM(CASE WHEN status='pago' THEN COALESCE(valor_pago,valor) END),0) AS recebido FROM cobrancas WHERE competencia = ? AND status <> 'cancelado'", [$comp]);
$ym = substr($comp, 0, 7);
$ant = date('Y-m', strtotime($comp . ' -1 month')); $prox = date('Y-m', strtotime($comp . ' +1 month'));
?>
<div class="mes-nav">
  <a href="<?= e(url('cobrancas', ['c' => $ant, 's' => $st])) ?>" aria-label="Mês anterior">‹</a>
  <strong><?= e(mes_ano($comp)) ?></strong>
  <a href="<?= e(url('cobrancas', ['c' => $prox, 's' => $st])) ?>" aria-label="Próximo mês">›</a>
</div>
<div class="grade-2">
  <div class="card kpi"><p class="rotulo">Emitido</p><p class="grande"><?= dinheiro($tot['emitido']) ?></p></div>
  <div class="card kpi"><p class="rotulo">Recebido</p><p class="grande verde"><?= dinheiro($tot['recebido']) ?></p></div>
</div>
<div class="botoes">
  <a class="btn" href="<?= e(url('cobranca_gerar', ['c' => $ym])) ?>">🧾 Gerar boletos do mês</a>
  <a class="btn btn-sec" href="<?= e(url('cobranca')) ?>">➕ Cobrança avulsa</a>
</div>
<div class="abas">
  <?php foreach (['' => 'Todos', 'aberto' => 'A vencer', 'vencido' => 'Vencidos', 'pago' => 'Pagos'] as $k => $r): ?>
    <a class="aba <?= $st === $k ? 'ativa' : '' ?>" href="<?= e(url('cobrancas', ['c' => $ym, 's' => $k])) ?>"><?= $r ?></a>
  <?php endforeach; ?>
</div>
<?php foreach ($cobs as $c): ?>
  <a class="card item cobranca" href="<?= e(url('cobranca', ['id' => $c['id']])) ?>">
    <div><strong><?= e(nome_unidade($c)) ?></strong><span class="muted pequeno d-bloco"><?= e($c['descricao']) ?> · venc. <?= data_br($c['vencimento']) ?></span></div>
    <div class="dir"><strong><?= dinheiro($c['valor']) ?></strong><?= selo($c) ?></div>
  </a>
<?php endforeach;
if (!$cobs) echo '<div class="card vazio">Nenhuma cobrança neste filtro.</div>';
pagina_fim();
