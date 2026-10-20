<?php
if (usuario()) redirect('inicio');
$email = '';
if (eh_post()) {
    $email = strtolower(post('email')); $senha = (string)($_POST['senha'] ?? '');
    if (login_bloqueado($email)) {
        flash('Muitas tentativas. Aguarde 15 minutos e tente de novo.', 'erro');
    } else {
        $u = linha('SELECT * FROM usuarios WHERE email = ? AND ativo = 1', [$email]);
        if ($u && password_verify($senha, $u['senha_hash'])) {
            logar($u);
            if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT)) q('UPDATE usuarios SET senha_hash = ? WHERE id = ?', [password_hash($senha, PASSWORD_DEFAULT), $u['id']]);
            redirect('inicio');
        }
        login_falhou($email);
        flash('E-mail ou senha incorretos.', 'erro');
    }
}
pagina_inicio('Entrar');
?>
<div class="login">
  <div class="login-logo"><?= icone('inicio') ?></div>
  <h1><?= e(conf('condominio_nome', 'Condomínio')) ?></h1>
  <p class="muted">Acesse com o e-mail e a senha que o síndico ou a administração enviou para você.</p>
  <form method="post" class="form card">
    <?= csrf_campo() ?>
    <label>E-mail
      <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" inputmode="email" required autofocus>
    </label>
    <label>Senha
      <span class="senha-wrap"><input type="password" name="senha" autocomplete="current-password" required>
      <button type="button" class="ver-senha" data-ver-senha>Mostrar</button></span>
    </label>
    <button class="btn btn-grande">Entrar</button>
    <a class="link-centro" href="<?= e(url('esqueci')) ?>">Esqueci minha senha</a>
  </form>
  <p class="muted pequeno">Ainda não tem acesso? Peça ao síndico ou à administração para cadastrar você.</p>
</div>
<?php pagina_fim();
