<?php
pagina_inicio('Moradores e acessos');
$busca = get('b'); $tipo = get('t');
$sql = "SELECT u.*,
    (SELECT n.nome FROM acessos a JOIN niveis_acesso n ON n.id = a.nivel_id WHERE a.usuario_id = u.id AND a.condominio_id = :c1) AS nivel,
    (SELECT GROUP_CONCAT(CONCAT(IF(un.bloco <> '', CONCAT(un.bloco, '-'), ''), un.numero) ORDER BY un.bloco, CAST(un.numero AS UNSIGNED) SEPARATOR ', ')
       FROM unidade_vinculos v JOIN unidades un ON un.id = v.unidade_id
       WHERE v.usuario_id = u.id AND un.condominio_id = :c2 AND (v.data_fim IS NULL OR v.data_fim >= CURDATE())) AS unids
  FROM usuarios u
  WHERE (EXISTS (SELECT 1 FROM acessos a WHERE a.usuario_id = u.id AND a.condominio_id = :c3)
      OR EXISTS (SELECT 1 FROM unidade_vinculos v JOIN unidades un ON un.id = v.unidade_id WHERE v.usuario_id = u.id AND un.condominio_id = :c4 AND (v.data_fim IS NULL OR v.data_fim >= CURDATE())))";
$par = [':c1' => cid(), ':c2' => cid(), ':c3' => cid(), ':c4' => cid()];
if ($busca !== '') {
    $sql .= " AND (u.nome LIKE :b1 OR u.email LIKE :b2 OR EXISTS (SELECT 1 FROM unidade_vinculos v JOIN unidades un ON un.id = v.unidade_id WHERE v.usuario_id = u.id AND un.condominio_id = :c5 AND un.numero LIKE :b3))";
    $par += [':b1' => "%$busca%", ':b2' => "%$busca%", ':b3' => "%$busca%", ':c5' => cid()];
}
if ($tipo === 'equipe') { $sql .= ' AND EXISTS (SELECT 1 FROM acessos a WHERE a.usuario_id = u.id AND a.condominio_id = :c6)'; $par[':c6'] = cid(); }
$sql .= ' ORDER BY u.ativo DESC, u.nome LIMIT 300';
$us = linhas($sql, $par);
?>
<a class="btn" href="<?= e(url('usuario')) ?>"><?= icone('plus', 20) ?> Cadastrar morador / usuário</a>
<form method="get" class="busca"><input type="hidden" name="p" value="usuarios">
  <input type="search" name="b" value="<?= e($busca) ?>" placeholder="Buscar por nome, e-mail ou apto"></form>
<div class="abas">
  <a class="aba <?= $tipo === '' ? 'ativa' : '' ?>" href="<?= e(url('usuarios')) ?>">Todos</a>
  <a class="aba <?= $tipo === 'equipe' ? 'ativa' : '' ?>" href="<?= e(url('usuarios', ['t' => 'equipe'])) ?>">Equipe com acesso</a>
</div>
<?php foreach ($us as $x): ?>
  <a class="card item <?= $x['ativo'] ? '' : 'inativo' ?>" href="<?= e(url('usuario', ['id' => $x['id']])) ?>">
    <span class="item-ic"><?= avatar($x, 44) ?>
      <span><strong><?= e($x['nome']) ?></strong>
        <span class="muted pequeno d-bloco"><?= $x['unids'] ? 'Unid. ' . e($x['unids']) . ' · ' : '' ?><?= e($x['email']) ?></span></span></span>
    <?php if (!$x['ativo']): ?><span class="selo selo-cancelado">Inativo</span><?php elseif ($x['nivel']): ?><span class="selo selo-aberto"><?= e($x['nivel']) ?></span><?php endif; ?>
  </a>
<?php endforeach;
if (!$us) echo '<div class="card vazio">Nenhum usuário encontrado.</div>';
pagina_fim();
