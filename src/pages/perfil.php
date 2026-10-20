<?php
$u = usuario();
if (eh_post()) {
    $nome = post('nome'); $email = strtolower(post('email'));
    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('Informe seu nome e um e-mail válido.', 'erro');
    elseif (valor('SELECT id FROM usuarios WHERE email = ? AND id <> ?', [$email, $u['id']])) flash('Este e-mail já está em uso por outro usuário.', 'erro');
    else {
        q('UPDATE usuarios SET nome=?, email=?, telefone=?, whatsapp=?, contato_emergencia=? WHERE id=?',
            [$nome, $email, post('telefone') ?: null, post('whatsapp') ?: null, post('contato_emergencia') ?: null, $u['id']]);
        flash('Dados atualizados!');
        redirect('perfil');
    }
}
pagina_inicio('Meu perfil');
?>
<div class="card perfil-topo">
  <div class="avatar"><?= e(mb_strtoupper(mb_substr($u['nome'], 0, 1))) ?></div>
  <div><strong><?= e($u['nome']) ?></strong><br>
  <span class="muted pequeno"><?= e(perfil_label($u)) ?><?= $u['unidade_id'] ? ' · ' . e(nome_unidade($u)) : '' ?></span></div>
</div>
<form method="post" class="form card"><?= csrf_campo() ?>
  <h3>Meus dados de contato</h3>
  <label>Nome completo<input name="nome" value="<?= e($u['nome']) ?>" required></label>
  <label>E-mail (também é seu login)<input type="email" name="email" value="<?= e($u['email']) ?>" inputmode="email" required></label>
  <label>Telefone<input type="tel" name="telefone" value="<?= e($u['telefone']) ?>" inputmode="tel" placeholder="(11) 3333-4444"></label>
  <label>WhatsApp<input type="tel" name="whatsapp" value="<?= e($u['whatsapp']) ?>" inputmode="tel" placeholder="(11) 99999-8888"></label>
  <label>Contato de emergência<input name="contato_emergencia" value="<?= e($u['contato_emergencia']) ?>" placeholder="Nome e telefone"></label>
  <button class="btn btn-grande">Salvar</button>
</form>
<a class="card item" href="<?= e(url('senha')) ?>"><span>🔒 Alterar minha senha</span><span class="seta">›</span></a>
<a class="card item" href="<?= e(url('sair')) ?>"><span>🚪 Sair</span><span class="seta">›</span></a>
<?php pagina_fim();
