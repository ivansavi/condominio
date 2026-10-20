<?php
$token = get('t', post('t'));
$r = $token ? linha('SELECT * FROM reset_senha WHERE token_hash = ? AND usado = 0 AND expira_em > NOW()', [hash('sha256', $token)]) : null;
if ($r && eh_post()) {
    $s1 = (string)($_POST['senha'] ?? ''); $s2 = (string)($_POST['senha2'] ?? '');
    if (mb_strlen($s1) < 8) flash('A senha precisa ter pelo menos 8 caracteres.', 'erro');
    elseif ($s1 !== $s2) flash('As senhas não são iguais.', 'erro');
    else {
        q('UPDATE usuarios SET senha_hash = ?, trocar_senha = 0 WHERE id = ?', [password_hash($s1, PASSWORD_DEFAULT), $r['usuario_id']]);
        q('UPDATE reset_senha SET usado = 1 WHERE usuario_id = ?', [$r['usuario_id']]);
        flash('Senha alterada! Entre com a nova senha.');
        redirect('login');
    }
}
pagina_inicio('Nova senha');
?>
<div class="login">
  <h1>Nova senha</h1>
  <?php if (!$r): ?>
    <div class="alerta alerta-erro">Este link expirou ou já foi usado.</div>
    <a class="btn" href="<?= e(url('esqueci')) ?>">Pedir um novo link</a>
  <?php else: ?>
    <form method="post" class="form card"><?= csrf_campo() ?><input type="hidden" name="t" value="<?= e($token) ?>">
      <label>Nova senha (mínimo 8 caracteres)<input type="password" name="senha" autocomplete="new-password" minlength="8" required autofocus></label>
      <label>Repita a nova senha<input type="password" name="senha2" autocomplete="new-password" minlength="8" required></label>
      <button class="btn btn-grande">Salvar nova senha</button>
    </form>
  <?php endif; ?>
</div>
<?php pagina_fim();
