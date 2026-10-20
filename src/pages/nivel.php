<?php
$id = (int)get('id');
$n = $id ? linha('SELECT * FROM niveis_acesso WHERE id = ? AND condominio_id = ?', [$id, cid()]) : null;
if ($id && !$n) redirect('niveis');
$travado = $n && $n['sistema']; // "Gestor principal" sempre tem todas as permissões
$tem = $n ? array_filter(explode(',', $n['permissoes'])) : [];
if (eh_post()) {
    $pessoas = $n ? (int)valor('SELECT COUNT(*) FROM acessos WHERE nivel_id = ?', [$n['id']]) : 0;
    if ($n && post('acao') === 'excluir') {
        if ($travado) flash('O nível padrão não pode ser excluído.', 'erro');
        elseif ($pessoas) flash('Há pessoas com este nível. Troque o nível delas antes de excluir.', 'erro');
        else { q('DELETE FROM niveis_acesso WHERE id = ?', [$n['id']]); flash('Nível excluído.'); redirect('niveis'); }
        redirect('nivel', ['id' => $n['id']]);
    }
    $nome = post('nome');
    $perms = $travado ? array_keys(PERMISSOES) : array_values(array_intersect(array_keys(PERMISSOES), (array)($_POST['perm'] ?? [])));
    if ($nome === '') flash('Dê um nome ao nível.', 'erro');
    elseif (valor('SELECT id FROM niveis_acesso WHERE condominio_id = ? AND nome = ? AND id <> ?', [cid(), $nome, $id])) flash('Já existe um nível com este nome.', 'erro');
    elseif (!$perms) flash('Marque pelo menos uma permissão.', 'erro');
    else {
        if ($n) q('UPDATE niveis_acesso SET nome=?, descricao=?, permissoes=? WHERE id=?', [$travado ? $n['nome'] : $nome, post('descricao') ?: null, implode(',', $perms), $n['id']]);
        else q('INSERT INTO niveis_acesso (condominio_id, nome, descricao, permissoes) VALUES (?,?,?,?)', [cid(), $nome, post('descricao') ?: null, implode(',', $perms)]);
        flash('Nível salvo.'); redirect('niveis');
    }
    $tem = $perms;
}
pagina_inicio($n ? 'Editar nível' : 'Novo nível', ['voltar' => url('niveis')]);
?>
<form method="post" class="form card"><?= csrf_campo() ?>
  <label>Nome do nível<input name="nome" value="<?= e($n['nome'] ?? '') ?>" <?= $travado ? 'readonly' : 'required' ?> placeholder="Ex.: Conselho fiscal"></label>
  <label>Descrição (opcional)<input name="descricao" value="<?= e($n['descricao'] ?? '') ?>"></label>
  <h3>O que este nível pode fazer</h3>
  <?php foreach (PERMISSOES as $k => $r): ?>
    <label class="check"><input type="checkbox" name="perm[]" value="<?= e($k) ?>" <?= in_array($k, $tem, true) ? 'checked' : '' ?> <?= $travado ? 'disabled' : '' ?>> <?= e($r) ?></label>
  <?php endforeach; ?>
  <?php if ($travado): ?><p class="muted pequeno">O nível padrão tem acesso total e não pode ser reduzido.</p><?php endif; ?>
  <button class="btn btn-grande">Salvar nível</button>
</form>
<?php if ($n && !$travado): ?>
  <form method="post" data-confirmar="Excluir este nível?"><?= csrf_campo() ?><input type="hidden" name="acao" value="excluir"><button class="btn btn-perigo">Excluir nível</button></form>
<?php endif;
pagina_fim();
