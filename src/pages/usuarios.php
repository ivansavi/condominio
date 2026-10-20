<?php
pagina_inicio('Moradores e acessos');
$busca = get('b'); $tipo = get('t');
$sql = 'SELECT u.*, un.bloco, un.numero FROM usuarios u LEFT JOIN unidades un ON un.id = u.unidade_id WHERE 1=1';
$par = [];
if ($busca !== '') { $sql .= ' AND (u.nome LIKE ? OR u.email LIKE ? OR un.numero LIKE ?)'; $par = ["%$busca%", "%$busca%", "%$busca%"]; }
if ($tipo === 'equipe') $sql .= " AND u.perfil <> 'condomino'";
$sql .= ' ORDER BY u.ativo DESC, un.bloco, CAST(un.numero AS UNSIGNED), u.nome LIMIT 300';
$us = linhas($sql, $par);
?>
<a class="btn" href="<?= e(url('usuario')) ?>">➕ Cadastrar morador / usuário</a>
<form method="get" class="busca"><input type="hidden" name="p" value="usuarios">
  <input type="search" name="b" value="<?= e($busca) ?>" placeholder="Buscar por nome, e-mail ou apto"></form>
<div class="abas">
  <a class="aba <?= $tipo === '' ? 'ativa' : '' ?>" href="<?= e(url('usuarios')) ?>">Todos</a>
  <a class="aba <?= $tipo === 'equipe' ? 'ativa' : '' ?>" href="<?= e(url('usuarios', ['t' => 'equipe'])) ?>">Síndico e gestão</a>
</div>
<?php foreach ($us as $x): ?>
  <a class="card item <?= $x['ativo'] ? '' : 'inativo' ?>" href="<?= e(url('usuario', ['id' => $x['id']])) ?>">
    <div><strong><?= e($x['nome']) ?></strong>
      <span class="muted pequeno d-bloco"><?= $x['unidade_id'] ? e(nome_unidade($x)) : e(perfil_label($x)) ?> · <?= e($x['email']) ?></span></div>
    <?php if (!$x['ativo']): ?><span class="selo selo-cancelado">Inativo</span><?php elseif ($x['perfil'] !== 'condomino'): ?><span class="selo selo-aberto"><?= $x['perfil'] === 'gestao' ? 'Gestão' : 'Síndico' ?></span><?php endif; ?>
  </a>
<?php endforeach;
if (!$us) echo '<div class="card vazio">Nenhum usuário encontrado.</div>';
pagina_fim();
