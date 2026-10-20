<?php
$me = usuario();
$id = (int)get('id');
$x = $id ? linha('SELECT * FROM comunicados WHERE id = ? AND ativo = 1', [$id]) : null;
if ($id && !$x) redirect('avisos');
if (eh_post()) {
    $t = post('titulo'); $c = post('corpo'); $imp = post('importante') ? 1 : 0;
    if ($t === '' || $c === '') flash('Preencha o título e a mensagem.', 'erro');
    else {
        if ($x) { q('UPDATE comunicados SET titulo=?, corpo=?, importante=? WHERE id=?', [$t, $c, $imp, $id]); $novo = $id; }
        else {
            q('INSERT INTO comunicados (titulo, corpo, importante, autor_id) VALUES (?,?,?,?)', [$t, $c, $imp, $me['id']]);
            $novo = (int)db()->lastInsertId();
            if (post('email')) {
                [$ok, $falha] = enviar_para_condominos($t, $c, ['tipo' => 'comunicado', 'botao' => ['Ver no aplicativo', link_app('aviso', ['id' => $novo])]]);
                flash("$ok e-mail(s) enviado(s)" . ($falha ? ", $falha falha(s)" : '') . '.', $falha ? 'aviso' : 'ok');
            }
        }
        flash('Comunicado publicado!'); redirect('aviso', ['id' => $novo]);
    }
}
pagina_inicio($x ? 'Editar comunicado' : 'Novo comunicado', ['voltar' => url('avisos')]);
?>
<form method="post" class="form card"><?= csrf_campo() ?>
  <label>Título<input name="titulo" value="<?= e($x['titulo'] ?? '') ?>" maxlength="160" required></label>
  <label>Mensagem<textarea name="corpo" rows="8" required><?= e($x['corpo'] ?? '') ?></textarea></label>
  <label class="check"><input type="checkbox" name="importante" value="1" <?= !empty($x['importante']) ? 'checked' : '' ?>> Marcar como importante ⚠️</label>
  <?php if (!$x): ?><label class="check"><input type="checkbox" name="email" value="1"> Também enviar por e-mail aos moradores</label><?php endif; ?>
  <button class="btn btn-grande">Publicar</button>
</form>
<?php pagina_fim();
