<?php
$enviado = false;
if (eh_post()) {
    $email = strtolower(post('email'));
    $u = linha('SELECT id, nome FROM usuarios WHERE email = ? AND ativo = 1', [$email]);
    if ($u && !login_bloqueado('reset:' . $email)) {
        login_falhou('reset:' . $email);
        $token = bin2hex(random_bytes(32));
        q('INSERT INTO reset_senha (usuario_id, token_hash, expira_em) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)', [$u['id'], hash('sha256', $token)]);
        $link = rtrim((string)cfg('url_base'), '/') . '/' . url('redefinir', ['t' => $token]);
        enviar_email($email, 'Recuperação de senha', "Olá, {$u['nome']}!\n\nPara criar uma nova senha, acesse o link abaixo (vale por 1 hora):\n\n$link\n\nSe você não pediu isso, ignore esta mensagem.");
    }
    $enviado = true; // resposta igual exista ou não o e-mail (evita descobrir quem é morador)
}
pagina_inicio('Recuperar senha');
?>
<div class="login">
  <h1>Recuperar senha</h1>
  <?php if ($enviado): ?>
    <div class="card"><p>Se o e-mail estiver cadastrado, você vai receber um link para criar uma nova senha. Confira também a caixa de spam.</p>
    <p class="muted pequeno">Não chegou? Peça ao síndico ou à administração para gerar uma senha provisória para você.</p></div>
    <a class="btn btn-sec" href="<?= e(url('login')) ?>">Voltar para o login</a>
  <?php else: ?>
    <p class="muted">Digite o e-mail do seu cadastro. Enviaremos um link para criar uma nova senha.</p>
    <form method="post" class="form card"><?= csrf_campo() ?>
      <label>E-mail<input type="email" name="email" inputmode="email" required autofocus></label>
      <button class="btn btn-grande">Enviar link</button>
      <a class="link-centro" href="<?= e(url('login')) ?>">Voltar</a>
    </form>
  <?php endif; ?>
</div>
<?php pagina_fim();
