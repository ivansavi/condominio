<?php
$u = usuario();
if (eh_post()) {
    $atual = (string)($_POST['atual'] ?? ''); $s1 = (string)($_POST['senha'] ?? ''); $s2 = (string)($_POST['senha2'] ?? '');
    if (!password_verify($atual, $u['senha_hash'])) flash('A senha atual está incorreta.', 'erro');
    elseif (mb_strlen($s1) < 8) flash('A nova senha precisa ter pelo menos 8 caracteres.', 'erro');
    elseif ($s1 !== $s2) flash('As senhas novas não são iguais.', 'erro');
    elseif ($s1 === $atual) flash('Escolha uma senha diferente da atual.', 'erro');
    else {
        q('UPDATE usuarios SET senha_hash = ?, trocar_senha = 0 WHERE id = ?', [password_hash($s1, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true);
        flash('Senha alterada com sucesso!');
        redirect('inicio');
    }
}
pagina_inicio('Alterar senha', $u['trocar_senha'] ? [] : ['voltar' => url('perfil')]);
?>
<?php if ($u['trocar_senha']): ?><div class="alerta alerta-aviso">Você entrou com uma senha provisória. Crie sua senha pessoal para continuar.</div><?php endif; ?>
<form method="post" class="form card"><?= csrf_campo() ?>
  <label>Senha atual<input type="password" name="atual" autocomplete="current-password" required></label>
  <label>Nova senha (mínimo 8 caracteres)<input type="password" name="senha" autocomplete="new-password" minlength="8" required></label>
  <label>Repita a nova senha<input type="password" name="senha2" autocomplete="new-password" minlength="8" required></label>
  <button class="btn btn-grande">Salvar nova senha</button>
</form>
<?php pagina_fim();
