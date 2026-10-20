<?php
$u = usuario();
$a = linha('SELECT c.*, u.nome AS autor FROM comunicados c LEFT JOIN usuarios u ON u.id = c.autor_id WHERE c.id = ? AND c.ativo = 1', [(int)get('id')]);
if (!$a) { http_response_code(404); pagina_inicio('Aviso', ['voltar' => url('avisos')]); echo '<div class="card">Comunicado não encontrado.</div>'; pagina_fim(); return; }
if (eh_post() && eh_admin() && post('acao') === 'remover') {
    q('UPDATE comunicados SET ativo = 0 WHERE id = ?', [$a['id']]);
    flash('Comunicado removido.'); redirect('avisos');
}
q('INSERT IGNORE INTO comunicado_leituras (comunicado_id, usuario_id) VALUES (?, ?)', [$a['id'], $u['id']]);
pagina_inicio('Comunicado', ['voltar' => url('avisos')]);
?>
<article class="card">
  <?php if ($a['importante']): ?><span class="selo selo-vencido">Importante</span><?php endif; ?>
  <h2><?= e($a['titulo']) ?></h2>
  <p class="muted pequeno"><?= data_br($a['publicado_em']) ?><?= $a['autor'] ? ' · ' . e($a['autor']) : '' ?></p>
  <div class="texto"><?= paragrafos($a['corpo']) ?></div>
</article>
<?php if (eh_admin()): ?>
  <a class="btn btn-sec" href="<?= e(url('aviso_form', ['id' => $a['id']])) ?>">✏️ Editar</a>
  <form method="post" data-confirmar="Remover este comunicado?"><?= csrf_campo() ?><input type="hidden" name="acao" value="remover"><button class="btn btn-perigo">Remover</button></form>
<?php endif;
pagina_fim();
