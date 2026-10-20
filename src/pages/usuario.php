<?php
$me = usuario();
$id = (int)get('id');
$x = $id ? linha('SELECT * FROM usuarios WHERE id = ?', [$id]) : null;
if ($id && !$x) { flash('Usuário não encontrado.', 'erro'); redirect('usuarios'); }
// Síndico sem acesso à gestão só administra condôminos
if ($x && !eh_gestao() && $x['perfil'] !== 'condomino') { flash('Apenas a gestão altera síndicos e administradores.', 'erro'); redirect('usuarios'); }
$unidades = linhas('SELECT * FROM unidades WHERE ativo = 1 ORDER BY bloco, CAST(numero AS UNSIGNED), numero');
$unidade_de = fn(?int $uid) => $uid ? linha('SELECT * FROM unidades WHERE id = ?', [$uid]) : null;

/** Mantém o histórico de proprietários/locatários da unidade em dia com o cadastro do morador. */
function sincronizar_vinculo(int $uid): void {
    $u = linha('SELECT * FROM usuarios WHERE id = ?', [$uid]);
    if (!$u) return;
    q('UPDATE unidade_vinculos SET data_fim = COALESCE(data_fim, ?) WHERE usuario_id = ? AND (? IS NULL OR unidade_id <> ?)', [date('Y-m-d'), $uid, $u['unidade_id'], $u['unidade_id']]);
    if (!$u['unidade_id'] || !$u['tipo_morador'] || $u['perfil'] !== 'condomino') return;
    $tipo = $u['tipo_morador'] === 'inquilino' ? 'locatario' : 'proprietario';
    $dados = [$tipo, $u['nome'], $u['cpf'], $u['telefone'] ?: $u['whatsapp'], $u['email'], $u['data_compra'], $u['data_venda']];
    $v = linha('SELECT id FROM unidade_vinculos WHERE usuario_id = ? AND unidade_id = ?', [$uid, $u['unidade_id']]);
    if ($v) q('UPDATE unidade_vinculos SET tipo=?, nome=?, cpf=?, telefone=?, email=?, data_inicio=?, data_fim=? WHERE id=?', [...$dados, $v['id']]);
    else q('INSERT INTO unidade_vinculos (tipo, nome, cpf, telefone, email, data_inicio, data_fim, unidade_id, usuario_id) VALUES (?,?,?,?,?,?,?,?,?)', [...$dados, $u['unidade_id'], $uid]);
}

if (eh_post()) {
    $acao = post('acao', 'salvar');
    if ($x && $acao === 'resetar') {
        $nova = senha_provisoria();
        q('UPDATE usuarios SET senha_hash = ?, trocar_senha = 1 WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $x['id']]);
        registrar_historico('usuario', (int)$x['id'], ['Senha' => ['', 'Senha provisória gerada']]);
        $_SESSION['cred'] = ['id' => $x['id'], 'nome' => $x['nome'], 'email' => $x['email'], 'senha' => $nova, 'tel' => $x['whatsapp'] ?: $x['telefone']];
        redirect('usuario', ['id' => $x['id']]);
    }
    if ($x && $acao === 'alternar') {
        if ($x['id'] == $me['id']) flash('Você não pode desativar o próprio acesso.', 'erro');
        else {
            q('UPDATE usuarios SET ativo = 1 - ativo WHERE id = ?', [$x['id']]);
            registrar_historico('usuario', (int)$x['id'], ['Cadastro ativo' => [sim_nao($x['ativo']), sim_nao(!$x['ativo'])]]);
            flash($x['ativo'] ? 'Acesso desativado.' : 'Acesso reativado.');
        }
        redirect('usuario', ['id' => $x['id']]);
    }
    $nome = post('nome'); $email = strtolower(post('email'));
    $perfil = eh_gestao() ? post('perfil', 'condomino') : ($x['perfil'] ?? 'condomino');
    if (!in_array($perfil, ['condomino', 'sindico', 'gestao'], true)) $perfil = 'condomino';
    $acesso_gestao = (eh_gestao() && $perfil === 'sindico' && post('acesso_gestao')) ? 1 : 0;
    if ($x && !eh_gestao()) $acesso_gestao = (int)$x['acesso_gestao'];
    $unidade = (int)post('unidade_id') ?: null;
    $tipo = in_array(post('tipo_morador'), ['proprietario', 'inquilino'], true) ? post('tipo_morador') : null;
    $compra = data_valida(post('data_compra')); $venda = data_valida(post('data_venda'));
    $ativo = $x ? (int)$x['ativo'] : 1;
    if ($x && $x['id'] == $me['id']) { $perfil = $x['perfil']; $acesso_gestao = (int)$x['acesso_gestao']; }
    $erro = null;
    if ($nome === '') $erro = 'Informe o nome.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'Informe um e-mail válido.';
    elseif (valor('SELECT id FROM usuarios WHERE email = ? AND id <> ?', [$email, $x['id'] ?? 0])) $erro = 'Já existe um usuário com este e-mail.';
    elseif ($perfil === 'condomino' && !$unidade) $erro = 'Escolha a unidade do condômino.';
    elseif ($compra && $venda && $venda < $compra) $erro = 'A data da venda não pode ser anterior à data da compra.';
    if ($erro) flash($erro, 'erro');
    else {
        $novo = ['nome' => $nome, 'email' => $email, 'cpf' => post('cpf') ?: null, 'telefone' => post('telefone') ?: null, 'whatsapp' => post('whatsapp') ?: null, 'perfil' => $perfil,
                 'acesso_gestao' => $acesso_gestao, 'unidade_id' => $unidade, 'tipo_morador' => $tipo, 'data_compra' => $compra, 'data_venda' => $venda, 'ativo' => $ativo];
        $campos = array_values($novo);
        if ($x) {
            $dif = diferencas(retrato_usuario($x, $unidade_de($x['unidade_id'] ? (int)$x['unidade_id'] : null)), retrato_usuario($novo, $unidade_de($unidade)));
            if (!$dif) { flash('Nenhuma alteração foi feita.'); redirect('usuario', ['id' => $x['id']]); }
            if (post('confirmar') === '') pagina_confirmar_alteracao($dif, 'Registrar alterações?', url('usuario', ['id' => $x['id']]));
            q('UPDATE usuarios SET nome=?, email=?, cpf=?, telefone=?, whatsapp=?, perfil=?, acesso_gestao=?, unidade_id=?, tipo_morador=?, data_compra=?, data_venda=?, ativo=? WHERE id=?', [...$campos, $x['id']]);
            if (post('confirmar') === 'sim') registrar_historico('usuario', (int)$x['id'], $dif);
            sincronizar_vinculo((int)$x['id']);
            flash('Cadastro atualizado.' . (post('confirmar') === 'sim' ? ' Alterações registradas no histórico.' : '')); redirect('usuario', ['id' => $x['id']]);
        }
        $nova = senha_provisoria();
        q('INSERT INTO usuarios (nome, email, cpf, telefone, whatsapp, perfil, acesso_gestao, unidade_id, tipo_morador, data_compra, data_venda, ativo, senha_hash, trocar_senha) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1)',
            [...$campos, password_hash($nova, PASSWORD_DEFAULT)]);
        $novo_id = (int)db()->lastInsertId();
        sincronizar_vinculo($novo_id);
        registrar_historico('usuario', $novo_id, ['Cadastro' => ['', 'Criado']]);
        $_SESSION['cred'] = ['id' => $novo_id, 'nome' => $nome, 'email' => $email, 'senha' => $nova, 'tel' => post('whatsapp') ?: post('telefone')];
        if (post('enviar_email')) {
            $ok = enviar_email($email, 'Seu acesso ao aplicativo do ' . conf('condominio_nome', 'condomínio'), "Olá, $nome!\n\nSeu acesso foi criado.\n\nE-mail: $email\nSenha provisória: $nova\n\nNo primeiro acesso você vai criar sua senha pessoal.", ['tipo' => 'acesso', 'botao' => ['Acessar o aplicativo', link_app('login')]]);
            flash($ok ? 'Acesso enviado por e-mail.' : 'Não foi possível enviar o e-mail: ' . ($GLOBALS['ultimo_erro_email'] ?? ''), $ok ? 'ok' : 'erro');
        }
        redirect('usuario', ['id' => $novo_id]);
    }
    $x = array_merge($x ?? [], $novo ?? [], ['id' => $x['id'] ?? 0, 'ativo' => $x['ativo'] ?? 1, 'nome' => $nome, 'email' => $email, 'cpf' => post('cpf'), 'telefone' => post('telefone'), 'whatsapp' => post('whatsapp'), 'perfil' => $perfil, 'acesso_gestao' => $acesso_gestao, 'unidade_id' => $unidade, 'tipo_morador' => $tipo, 'data_compra' => $compra, 'data_venda' => $venda]);
}

$cred = $_SESSION['cred'] ?? null;
if ($cred && $x && $cred['id'] != $x['id']) $cred = null;
unset($_SESSION['cred']);

pagina_inicio($x && $x['id'] ? 'Editar cadastro' : 'Novo cadastro', ['voltar' => url('usuarios')]);

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
    <select name="tipo_morador" data-tipo-morador><option value="">—</option>
      <option value="proprietario" <?= ($x['tipo_morador'] ?? '') === 'proprietario' ? 'selected' : '' ?>>Proprietário</option>
      <option value="inquilino" <?= ($x['tipo_morador'] ?? '') === 'inquilino' ? 'selected' : '' ?>>Inquilino</option></select></label>
  <div class="duas">
    <label><span data-rot-inicio>Data da compra</span><input type="date" name="data_compra" value="<?= e($x['data_compra'] ?? '') ?>"></label>
    <label><span data-rot-fim>Data da venda</span><input type="date" name="data_venda" value="<?= e($x['data_venda'] ?? '') ?>"></label>
  </div>
  <label>CPF<input name="cpf" value="<?= e($x['cpf'] ?? '') ?>" inputmode="numeric" placeholder="000.000.000-00"></label>
  <label>Telefone<input type="tel" name="telefone" value="<?= e($x['telefone'] ?? '') ?>" inputmode="tel"></label>
  <label>WhatsApp<input type="tel" name="whatsapp" value="<?= e($x['whatsapp'] ?? '') ?>" inputmode="tel"></label>
  <?php if (!$x || !$x['id']): ?>
    <label class="check"><input type="checkbox" name="enviar_email" value="1"> Enviar o acesso por e-mail</label>
    <p class="muted pequeno">Uma senha provisória será gerada para você enviar ao morador.</p>
  <?php endif; ?>
  <button class="btn btn-grande"><?= $x && $x['id'] ? 'Salvar alterações' : 'Criar acesso' ?></button>
</form>

<?php if ($x && $x['id']): ?>
  <div class="card situacao">
    <div><strong><?= $x['ativo'] ? 'Cadastro ativo' : 'Cadastro desativado' ?></strong>
      <span class="muted pequeno d-bloco"><?= $x['ativo'] ? 'Pode entrar no aplicativo.' : 'Não consegue entrar no aplicativo.' ?></span></div>
    <?php if ($x['id'] != $me['id']): ?>
      <form method="post" data-confirmar="<?= $x['ativo'] ? 'Desativar o acesso desta pessoa?' : 'Reativar o acesso?' ?>"><?= csrf_campo() ?><input type="hidden" name="acao" value="alternar">
        <button class="btn <?= $x['ativo'] ? 'btn-perigo' : '' ?> btn-pequeno"><?= $x['ativo'] ? 'Desativar' : 'Ativar' ?></button></form>
    <?php endif; ?>
  </div>
  <form method="post" data-confirmar="Gerar uma nova senha provisória? A senha atual deixará de funcionar."><?= csrf_campo() ?><input type="hidden" name="acao" value="resetar">
    <button class="btn btn-sec">🔑 Gerar nova senha provisória</button></form>
  <a class="btn btn-sec" href="<?= e(url('historico', ['e' => 'usuario', 'id' => $x['id']])) ?>">🕘 Histórico de alterações</a>
  <?php if ($x['unidade_id']): ?>
    <a class="btn btn-sec" href="<?= e(url('boletos', ['unidade' => $x['unidade_id']])) ?>">💰 Histórico financeiro da unidade</a>
    <a class="btn btn-sec" href="<?= e(url('unidade', ['id' => $x['unidade_id']])) ?>">🏢 Ver unidade, proprietários e locatários</a>
  <?php endif;
endif;
pagina_fim();
