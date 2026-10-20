<?php
// Só o gestor principal: cadastra novos condomínios.
$u = usuario();
if (eh_post()) {
    $nome = post('nome');
    if ($nome === '') flash('Informe o nome do condomínio.', 'erro');
    else {
        q('INSERT INTO condominios (nome, cnpj) VALUES (?,?)', [$nome, post('cnpj') ?: null]);
        $novo = (int)db()->lastInsertId();
        $nivel = semear_niveis($novo);
        q('INSERT INTO acessos (usuario_id, condominio_id, nivel_id) VALUES (?,?,?)', [$u['id'], $novo, $nivel]);
        $_SESSION['cond'] = $novo; q('UPDATE usuarios SET ultimo_condominio_id = ? WHERE id = ?', [$novo, $u['id']]);
        flash('Condomínio criado! Complete os dados e cadastre as unidades.');
        redirect('config');
    }
}
$todos = linhas('SELECT c.*, (SELECT COUNT(*) FROM unidades un WHERE un.condominio_id = c.id) AS unidades FROM condominios c ORDER BY c.nome');
pagina_inicio('Condomínios', ['voltar' => url('meus_condominios')]);
?>
<?php foreach ($todos as $c): ?>
  <div class="card item"><span class="item-ic"><span class="chip"><?= icone('predio', 22) ?></span><span><strong><?= e($c['nome']) ?></strong><span class="muted pequeno d-bloco"><?= (int)$c['unidades'] ?> unidade(s)<?= $c['cnpj'] ? ' · ' . e($c['cnpj']) : '' ?></span></span></span></div>
<?php endforeach; ?>
<form method="post" class="form card"><?= csrf_campo() ?>
  <h3>Novo condomínio</h3>
  <label>Nome do condomínio<input name="nome" required></label>
  <label>CNPJ (opcional)<input name="cnpj" inputmode="numeric"></label>
  <p class="muted pequeno">Você vira Gestor principal do novo condomínio. Os níveis de acesso padrão são criados automaticamente.</p>
  <button class="btn btn-grande">Criar condomínio</button>
</form>
<?php pagina_fim();
