<?php
$ent = get('e') === 'unidade' ? 'unidade' : 'usuario';
$id = (int)get('id');
if ($ent === 'usuario') {
    $alvo = linha('SELECT nome FROM usuarios WHERE id = ?', [$id]);
    $nome = $alvo['nome'] ?? 'Cadastro'; $volta = url('usuario', ['id' => $id]);
} else {
    $alvo = linha('SELECT bloco, numero FROM unidades WHERE id = ?', [$id]);
    $nome = $alvo ? nome_unidade($alvo) : 'Unidade'; $volta = url('unidade', ['id' => $id]);
}
if (!$alvo) redirect('mais');
$rows = linhas('SELECT * FROM historico_alteracoes WHERE entidade = ? AND entidade_id = ? ORDER BY criado_em DESC, id', [$ent, $id]);
$lotes = [];
foreach ($rows as $r) $lotes[$r['lote']][] = $r;
pagina_inicio('Histórico', ['voltar' => $volta]);
?>
<p class="muted">Alterações de <strong><?= e($nome) ?></strong>: o que mudou, como era e como ficou.</p>
<?php foreach ($lotes as $itens): $p = $itens[0]; ?>
  <div class="card">
    <p class="rotulo"><?= e(date('d/m/Y H:i', strtotime($p['criado_em']))) ?> · <?= e($p['autor_nome'] ?? 'Sistema') ?></p>
    <?php foreach ($itens as $r): ?>
      <div class="dif"><span class="dif-campo"><?= e($r['campo']) ?></span>
        <span class="dif-antes"><?= e($r['valor_antigo'] ?? '—') ?></span><span class="dif-seta">→</span><span class="dif-depois"><?= e($r['valor_novo'] ?? '—') ?></span></div>
    <?php endforeach; ?>
  </div>
<?php endforeach;
if (!$lotes) echo '<div class="card vazio">Nenhuma alteração registrada ainda.</div>';
pagina_fim();
