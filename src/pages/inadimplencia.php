<?php
pagina_inicio('Inadimplência', ['voltar' => url('mais')]);
$rows = linhas("SELECT c.*, un.bloco, un.numero FROM cobrancas c JOIN unidades un ON un.id = c.unidade_id
                WHERE un.condominio_id = ? AND c.status = 'aberto' AND c.vencimento < CURDATE() ORDER BY un.bloco, CAST(un.numero AS UNSIGNED), c.vencimento", [cid()]);
$por = [];
foreach ($rows as $c) {
    $k = $c['unidade_id'];
    $por[$k] ??= ['un' => $c, 'qtd' => 0, 'total' => 0.0, 'mais_antigo' => $c['vencimento']];
    $por[$k]['qtd']++; $por[$k]['total'] += cobranca_atualizada($c)['total'];
}
uasort($por, fn($a, $b) => $b['total'] <=> $a['total']);
$geral = array_sum(array_column($por, 'total'));
?>
<div class="grade-2">
  <div class="card kpi"><p class="rotulo">Unidades em atraso</p><p class="grande"><?= count($por) ?></p></div>
  <div class="card kpi"><p class="rotulo">Total atualizado</p><p class="grande vermelho"><?= dinheiro($geral) ?></p></div>
</div>
<?php foreach ($por as $uid => $d): ?>
  <a class="card item cobranca" href="<?= e(url('boletos', ['unidade' => $uid])) ?>">
    <div><strong><?= e(nome_unidade($d['un'])) ?></strong><span class="muted pequeno d-bloco"><?= $d['qtd'] ?> boleto(s) · desde <?= data_br($d['mais_antigo']) ?></span></div>
    <strong class="vermelho"><?= dinheiro($d['total']) ?></strong>
  </a>
<?php endforeach;
if (!$por) echo '<div class="card vazio">🎉 Nenhuma unidade inadimplente.</div>';
pagina_fim();
