<?php
pagina_inicio('Unidades');
$un = linhas('SELECT un.*, (SELECT COUNT(*) FROM usuarios u WHERE u.unidade_id = un.id AND u.ativo = 1) AS moradores FROM unidades un ORDER BY un.ativo DESC, un.bloco, CAST(un.numero AS UNSIGNED), un.numero');
?>
<a class="btn" href="<?= e(url('unidade')) ?>">➕ Nova unidade</a>
<?php foreach ($un as $x): ?>
  <a class="card item <?= $x['ativo'] ? '' : 'inativo' ?>" href="<?= e(url('unidade', ['id' => $x['id']])) ?>">
    <div><strong><?= e(nome_unidade($x)) ?></strong><span class="muted pequeno d-bloco"><?= (int)$x['moradores'] ?> morador(es) com acesso</span></div>
    <strong><?= dinheiro($x['valor_condominio']) ?></strong>
  </a>
<?php endforeach;
if (!$un) echo '<div class="card vazio">Cadastre as unidades do condomínio para começar.</div>';
pagina_fim();
