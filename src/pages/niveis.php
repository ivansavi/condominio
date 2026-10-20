<?php
pagina_inicio('Níveis de acesso', ['voltar' => url('mais')]);
$niveis = linhas('SELECT n.*, (SELECT COUNT(*) FROM acessos a WHERE a.nivel_id = n.id) AS pessoas FROM niveis_acesso n WHERE n.condominio_id = ? ORDER BY n.sistema DESC, n.nome', [cid()]);
?>
<p class="muted">Cada nível define o que a pessoa pode fazer neste condomínio. Associe o nível ao cadastrar ou editar um usuário.</p>
<a class="btn" href="<?= e(url('nivel')) ?>"><?= icone('plus', 20) ?> Criar novo nível</a>
<?php foreach ($niveis as $n): $perms = array_filter(explode(',', $n['permissoes'])); ?>
  <a class="card item" href="<?= e(url('nivel', ['id' => $n['id']])) ?>">
    <div><strong><?= e($n['nome']) ?></strong>
      <span class="muted pequeno d-bloco"><?= e($n['descricao'] ?: count($perms) . ' permissões') ?> · <?= (int)$n['pessoas'] ?> pessoa(s)</span></div>
    <?= $n['sistema'] ? '<span class="selo selo-aberto">Padrão</span>' : '<span class="seta">›</span>' ?>
  </a>
<?php endforeach;
pagina_fim();
