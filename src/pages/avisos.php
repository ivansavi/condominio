<?php
$u = usuario();
pagina_inicio('Avisos e comunicados');
$av = linhas("SELECT c.*, (SELECT COUNT(*) FROM comunicado_leituras l WHERE l.comunicado_id = c.id AND l.usuario_id = ?) AS lido
              FROM comunicados c WHERE c.ativo = 1 AND c.condominio_id = ? ORDER BY c.publicado_em DESC LIMIT 100", [$u['id'], cid()]);
if (pode('comunicados')) echo '<a class="btn" href="' . e(url('aviso_form')) . '">📣 Novo comunicado</a>';
foreach ($av as $a): ?>
  <a class="card item <?= $a['lido'] ? '' : 'nao-lido' ?>" href="<?= e(url('aviso', ['id' => $a['id']])) ?>">
    <div><strong><?= $a['importante'] ? '⚠️ ' : '' ?><?= e($a['titulo']) ?></strong>
    <span class="muted pequeno d-bloco"><?= e(mb_strimwidth(strip_tags($a['corpo']), 0, 90, '…')) ?></span></div>
    <span class="muted pequeno"><?= data_br($a['publicado_em']) ?></span>
  </a>
<?php endforeach;
if (!$av) echo '<div class="card vazio">Nenhum comunicado por enquanto.</div>';
pagina_fim();
