<?php
$u = usuario();
$lista = condominios_acessiveis();
if (eh_post()) {
    $novo = (int)post('id');
    if (in_array($novo, array_map(fn($c) => (int)$c['id'], $lista), true)) { $_SESSION['cond'] = $novo; q('UPDATE usuarios SET ultimo_condominio_id = ? WHERE id = ?', [$novo, $u['id']]); redirect('inicio'); }
    flash('Condomínio inválido.', 'erro');
}
pagina_inicio('Meus condomínios', ['voltar' => url('inicio')]);
?>
<p class="muted">Um único cadastro acessa todos os condomínios em que você tem unidade ou função. Escolha qual abrir.</p>
<?php foreach ($lista as $c):
    $atual = (int)$c['id'] === cid();
    $unids = linhas('SELECT un.* FROM unidade_vinculos v JOIN unidades un ON un.id = v.unidade_id WHERE v.usuario_id = ? AND un.condominio_id = ? AND (v.data_fim IS NULL OR v.data_fim >= CURDATE()) ORDER BY un.bloco, CAST(un.numero AS UNSIGNED)', [$u['id'], $c['id']]);
    $nivel = linha('SELECT n.nome FROM acessos a JOIN niveis_acesso n ON n.id = a.nivel_id WHERE a.usuario_id = ? AND a.condominio_id = ?', [$u['id'], $c['id']]); ?>
  <form method="post"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= $c['id'] ?>">
    <button class="card item cond-card <?= $atual ? 'cond-atual' : '' ?>">
      <span class="item-ic"><?php if ($c['logo_arquivo_id']): ?><img class="logo-lista" src="<?= e(url('arquivo', ['id' => $c['logo_arquivo_id']])) ?>" alt=""><?php else: ?><span class="chip"><?= icone('predio', 22) ?></span><?php endif; ?>
        <span><strong><?= e($c['nome']) ?></strong>
        <span class="muted pequeno d-bloco"><?= $unids ? e(implode(' · ', array_map('nome_unidade', $unids))) : ($u['super_admin'] ? 'Gestor principal' : '') ?><?= $nivel ? ' · ' . e($nivel['nome']) : '' ?></span></span></span>
      <?= $atual ? '<span class="selo selo-pago">Aberto</span>' : '<span class="seta">›</span>' ?>
    </button></form>
<?php endforeach;
if ($u['super_admin']) echo '<a class="btn btn-sec" href="' . e(url('condominios')) . '">', icone('predio', 20), ' Gerenciar condomínios</a>';
pagina_fim();
