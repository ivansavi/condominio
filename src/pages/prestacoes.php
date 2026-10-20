<?php
pagina_inicio('Prestação de contas');
$ms = linhas('SELECT competencia FROM prestacoes WHERE publicada = 1 ORDER BY competencia DESC');
?>
<p class="muted">Veja para onde vai o dinheiro do condomínio: receitas, despesas e saldo de cada mês.</p>
<?php foreach ($ms as $m):
    $r = resumo_mes($m['competencia']); ?>
  <a class="card item cobranca" href="<?= e(url('prestacao', ['c' => substr($m['competencia'], 0, 7)])) ?>">
    <div><strong><?= e(mes_ano($m['competencia'])) ?></strong><span class="muted pequeno d-bloco">Despesas <?= dinheiro($r['despesas']) ?></span></div>
    <div class="dir"><span class="muted pequeno">Saldo final</span><strong><?= dinheiro($r['saldo_final']) ?></strong></div>
  </a>
<?php endforeach;
if (!$ms) echo '<div class="card vazio">Nenhuma prestação de contas publicada ainda.</div>';
if (eh_gestao()) echo '<a class="btn" href="' . e(url('contas_gestao')) . '">Gerenciar prestações</a>';
pagina_fim();
