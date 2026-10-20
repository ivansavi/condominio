<?php
$me = usuario();
$id = (int)get('id');
$x = $id ? linha('SELECT * FROM usuarios WHERE id = ?', [$id]) : null;
if ($id && !$x) { flash('Usuário não encontrado.', 'erro'); redirect('usuarios'); }
// Síndico sem acesso à gestão só administra condôminos
if ($x && !eh_gestao() && $x['perfil'] !== 'condomino') { flash('Apenas a gestão altera síndicos e administradores.', 'erro'); redirect('usuarios'); }
$unidades = linhas('SELECT * FROM unidades WHERE ativo = 1 ORDER BY bloco, CAST(numero AS UNSIGNED), numero');

if (eh_post()) {
    $acao = post('acao', 'salvar');
    if ($x && $acao === 'resetar') {
        $nova = senha_provisoria();
        q('UPDATE usuarios SET senha_hash = ?, trocar_senha = 1 WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $x['id']]);
        $_SESSION['cred'] = ['id' => $x['id'], 'nome' => $x['nome'], 'email' => $x['email'], 'senha' => $nova, 'tel' => $x['whatsapp'] ?: $x['telefone']];
        redirect('usuario', ['id' => $x['id']]);
    }
    if ($x && $acao === 'alternar') {
        if ($x['id'] == $me['id']) flash('Você não pode desativar o próprio acesso.', 'erro');
        else { q('UPDATE usuarios SET ativo = 1 - ativo WHERE id = ?', [$x['id']]); flash($x['ativo'] ? 'Acesso desativado.' : 'Acesso reativado.'); }
        redirect('usuario', ['id' => $x['id']]);
    }
    $nome = post('nome'); $email = strtolower(post('email'));
    $perfil = eh_gestao() ? post('perfil', 'condomino') : 'condomino';
    if (!in_array($perfil, ['condomino', 'sindico', 'gestao'], true)) $perfil = 'condomino';
    $acesso_gestao = (eh_gestao() && $perfil === 'sindico' && post('acesso_gestao')) ? 1 : 0;
    $unidade = (int)post('unidade_id') ?: null;
    $tipo = in_array(post('tipo_morador'), ['proprietario', 'inquilino'], true) ? post('tipo_morador') : null;
    if ($x && $x['id'] == $me['id']) { $perfil = $x['perfil']; $acesso_gestao = (int)$x['acesso_gestao']; }
    $erro = null;
    if ($nome === '') $erro = 'Informe o nome.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'Informe um e-mail válido.';
    elseif (valor('SELECT id FROM usuarios WHERE email = ? AND id <> ?', [$email, $x['id'] ?? 0])) $erro = 'Já existe um usuário com este e-mail.';
    elseif ($perfil === 'condomino' && !$unidade) $erro = 'Escolha a unidade do condômino.';
    if ($erro) flash($erro, 'erro');
    else {
        $campos = [$nome, $email, post('cpf') ?: null, post('telefone') ?: null, post('whatsapp') ?: null, $perfil, $acesso_gestao, $unidade, $tipo];
        if ($x) {
            q('UPDATE usuarios SET nome=?, email=?, cpf=?, telefone=?, whatsapp=?, perfil=?, acesso_gestao=?, unidade_id=?, tipo_morador=? WHERE id=?', [...$campos, $x['id']]);
            flash('Cadastro atualizado.'); redirect('usuario', ['id' => $x['id']]);
        }
        $nova = senha_provisoria();
        q('INSERT INTO usuarios (nome, email, cpf, telefone, whatsapp, perfil, acesso_gestao, unidade_id, tipo_morador, senha_hash, trocar_senha) VALUES (?,?,?,?,?,?,?,?,?,?,1)',
            [...$campos, password_hash($nova, PASSWORD_DEFAULT)]);
        $novo = (int)db()->lastInsertId();
        $_SESSION['cred'] = ['id' => $novo, 'nome' => $nome, 'email' => $email, 'senha' => $nova, 'tel' => post('whatsapp') ?: post('telefone')];
        redirect('usuario', ['id' => $novo]);
    }
    $x = array_merge($x ?? [], ['nome' => $nome, 'email' => $email, 'cpf' => post('cpf'), 'telefone' => post('telefone'), 'whatsapp' => post('whatsapp'), 'perfil' => $perfil, 'acesso_gestao' => $acesso_gestao, 'unidade_id' => $unidade, 'tipo_morador' => $tipo, 'id' => $x['id'] ?? 0, 'ativo' => $x['ativo'] ?? 1]);
}

$cred = $_SESSION['cred'] ?? null;
if ($cred && $x && $cred['id'] != $x['id']) $cred = null;
unset($_SESSION['cred']);

pagina_inicio($x && $x['id'] ? 'Editar usuário' : 'Novo usuário', ['voltar' => url('usuarios')]);

if ($cred):
    $link = rtrim((string)cfg('url_base'), '/') . '/';
    $msg = "Olá, {$cred['nome']}! Seu acesso ao app do " . conf('condominio_nome', 'condomínio') . " foi criado.\nEndereço: $link\nE-mail: {$cred['email']}\nSenha provisória: {$cred['senha']}\nNo primeiro acesso você vai criar sua senha pessoal.";
    $tel = preg_replace('/\D/', '', (string)$cred['tel']);
    if ($tel && strlen($tel) <= 11) $tel = '55' . $tel; ?>
  <section class="card destaque">
    <p class="rotulo">Acesso criado — anote agora</p>
    <p>E-mail: <strong><?= e($cred['email']) ?></strong></p>
    <p>Senha provisória: <code class="senha-prov"><?= e($cred['senha']) ?></code></p>
    <p class="pequeno">Esta senha aparece só uma vez. No primeiro acesso a pessoa cria a senha pessoal.</p>
    <a class="btn btn-claro" target="_blank" rel="noopener" href="https://wa.me/<?= e($tel) ?>?text=<?= e(rawurlencode($msg)) ?>">Enviar por WhatsApp</a>
    <button class="btn btn-claro" type="button" data-copiar-texto="<?= e($msg) ?>">Copiar mensagem</button>
  </section>
<?php endif; ?>

<form method="post" class="form card"><?= csrf_campo() ?>
  <label>Nome completo<input name="nome" value="<?= e($x['nome'] ?? '') ?>" required></label>
  <label>E-mail (será o login)<input type="email" name="email" value="<?= e($x['email'] ?? '') ?>" inputmode="email" required></label>
  <?php if (eh_gestao() && (!$x || $x['id'] != $me['id'])): ?>
    <label>Tipo de acesso
      <select name="perfil" data-perfil>
        <option value="condomino" <?= ($x['perfil'] ?? 'condomino') === 'condomino' ? 'selected' : '' ?>>Condômino (morador)</option>
        <option value="sindico" <?= ($x['perfil'] ?? '') === 'sindico' ? 'selected' : '' ?>>Síndico / Administrador</option>
        <option value="gestao" <?= ($x['perfil'] ?? '') === 'gestao' ? 'selected' : '' ?>>Gestão (boletos e prestação de contas)</option>
      </select></label>
    <label class="check"><input type="checkbox" name="acesso_gestao" value="1" <?= !empty($x['acesso_gestao']) ? 'checked' : '' ?>> Síndico também acessa o portal da gestão</label>
  <?php endif; ?>
  <label>Unidade
    <select name="unidade_id"><option value="">— nenhuma —</option>
      <?php foreach ($unidades as $un): ?><option value="<?= $un['id'] ?>" <?= (int)($x['unidade_id'] ?? 0) === (int)$un['id'] ? 'selected' : '' ?>><?= e(nome_unidade($un)) ?></option><?php endforeach; ?>
    </select></label>
  <label>Situação
    <select name="tipo_morador"><option value="">—</option>
      <option value="proprietario" <?= ($x['tipo_morador'] ?? '') === 'proprietario' ? 'selected' : '' ?>>Proprietário</option>
      <option value="inquilino" <?= ($x['tipo_morador'] ?? '') === 'inquilino' ? 'selected' : '' ?>>Inquilino</option></select></label>
  <label>CPF<input name="cpf" value="<?= e($x['cpf'] ?? '') ?>" inputmode="numeric" placeholder="000.000.000-00"></label>
  <label>Telefone<input type="tel" name="telefone" value="<?= e($x['telefone'] ?? '') ?>" inputmode="tel"></label>
  <label>WhatsApp<input type="tel" name="whatsapp" value="<?= e($x['whatsapp'] ?? '') ?>" inputmode="tel"></label>
  <button class="btn btn-grande"><?= $x && $x['id'] ? 'Salvar alterações' : 'Criar acesso' ?></button>
  <?php if (!$x || !$x['id']): ?><p class="muted pequeno">Uma senha provisória será gerada para você enviar ao morador.</p><?php endif; ?>
</form>

<?php if ($x && $x['id']): ?>
  <form method="post" data-confirmar="Gerar uma nova senha provisória? A senha atual deixará de funcionar."><?= csrf_campo() ?><input type="hidden" name="acao" value="resetar">
    <button class="btn btn-sec">🔑 Gerar nova senha provisória</button></form>
  <?php if ($x['id'] != $me['id']): ?>
  <form method="post" data-confirmar="<?= $x['ativo'] ? 'Desativar o acesso desta pessoa?' : 'Reativar o acesso?' ?>"><?= csrf_campo() ?><input type="hidden" name="acao" value="alternar">
    <button class="btn <?= $x['ativo'] ? 'btn-perigo' : 'btn-sec' ?>"><?= $x['ativo'] ? 'Desativar acesso' : 'Reativar acesso' ?></button></form>
  <?php endif;
  if ($x['unidade_id']): ?><a class="btn btn-sec" href="<?= e(url('boletos', ['unidade' => $x['unidade_id']])) ?>">💰 Ver histórico financeiro da unidade</a><?php endif;
endif;
pagina_fim();
