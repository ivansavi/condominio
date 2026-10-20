<?php
$me = usuario();
$id = (int)get('id');
$x = $id ? linha('SELECT * FROM usuarios WHERE id = ?', [$id]) : null;
$nivel_x = null; $sou_eu = $x && (int)$x['id'] === (int)$me['id'];
if ($id) {
    $do_cond = $x && valor('SELECT 1 FROM usuarios u WHERE u.id = ? AND (EXISTS (SELECT 1 FROM acessos a WHERE a.usuario_id = u.id AND a.condominio_id = ?)
        OR EXISTS (SELECT 1 FROM unidade_vinculos v JOIN unidades un ON un.id = v.unidade_id WHERE v.usuario_id = u.id AND un.condominio_id = ?))', [$id, cid(), cid()]);
    if (!$do_cond) { flash('Usuário não encontrado neste condomínio.', 'erro'); redirect('usuarios'); }
    $nivel_x = linha('SELECT a.nivel_id, n.nome FROM acessos a JOIN niveis_acesso n ON n.id = a.nivel_id WHERE a.usuario_id = ? AND a.condominio_id = ?', [$id, cid()]);
    // só quem gerencia níveis mexe na equipe (e no gestor principal global)
    if (($nivel_x && !pode('niveis') && !$sou_eu) || ($x['super_admin'] && !$me['super_admin'] && !$sou_eu)) { flash('Apenas quem gerencia níveis de acesso altera a equipe.', 'erro'); redirect('usuarios'); }
}
$niveis = linhas('SELECT * FROM niveis_acesso WHERE condominio_id = ? ORDER BY sistema DESC, nome', [cid()]);
$unidades = linhas('SELECT * FROM unidades WHERE condominio_id = ? AND ativo = 1 ORDER BY bloco, CAST(numero AS UNSIGNED), numero', [cid()]);
$nome_nivel = fn(?int $nid) => $nid ? (valor('SELECT nome FROM niveis_acesso WHERE id = ? AND condominio_id = ?', [$nid, cid()]) ?: '') : '';

function definir_nivel(int $uid, ?int $nivel_id): void {
    if ($nivel_id) q('INSERT INTO acessos (usuario_id, condominio_id, nivel_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE nivel_id = VALUES(nivel_id)', [$uid, cid(), $nivel_id]);
    else q('DELETE FROM acessos WHERE usuario_id = ? AND condominio_id = ?', [$uid, cid()]);
}
function criar_vinculo_morador(int $uid, int $unidade, ?string $tipo, ?string $ini, ?string $fim): void {
    $u = linha('SELECT * FROM usuarios WHERE id = ?', [$uid]);
    q('INSERT INTO unidade_vinculos (unidade_id, tipo, nome, cpf, telefone, email, data_inicio, data_fim, usuario_id) VALUES (?,?,?,?,?,?,?,?,?)',
        [$unidade, $tipo === 'locatario' ? 'locatario' : 'proprietario', $u['nome'], $u['cpf'], $u['telefone'] ?: $u['whatsapp'], $u['email'], $ini, $fim, $uid]);
    $un = unidade_do_condominio($unidade);
    registrar_historico('usuario', $uid, ['Unidade vinculada' => ['', nome_unidade($un) . ($tipo === 'locatario' ? ' (locatário)' : ' (proprietário)') . ($ini ? ' desde ' . data_br($ini) : '')]]);
}

if (eh_post()) {
    $acao = post('acao', 'salvar');
    if ($x && in_array($acao, ['resetar', 'alternar'], true) && $x['super_admin'] && !$sou_eu && !$me['super_admin']) { flash('Sem permissão.', 'erro'); redirect('usuarios'); }
    if ($x && $acao === 'resetar') {
        $nova = senha_provisoria();
        q('UPDATE usuarios SET senha_hash = ?, trocar_senha = 1 WHERE id = ?', [password_hash($nova, PASSWORD_DEFAULT), $x['id']]);
        registrar_historico('usuario', (int)$x['id'], ['Senha' => ['', 'Senha provisória gerada']]);
        $_SESSION['cred'] = ['id' => $x['id'], 'nome' => $x['nome'], 'email' => $x['email'], 'senha' => $nova, 'tel' => $x['whatsapp'] ?: $x['telefone']];
        redirect('usuario', ['id' => $x['id']]);
    }
    if ($x && $acao === 'alternar') {
        if ($sou_eu) flash('Você não pode desativar o próprio acesso.', 'erro');
        else {
            q('UPDATE usuarios SET ativo = 1 - ativo WHERE id = ?', [$x['id']]);
            registrar_historico('usuario', (int)$x['id'], ['Cadastro ativo' => [sim_nao($x['ativo']), sim_nao(!$x['ativo'])]]);
            flash($x['ativo'] ? 'Acesso desativado.' : 'Acesso reativado.');
        }
        redirect('usuario', ['id' => $x['id']]);
    }
    $nome = post('nome'); $email = strtolower(post('email'));
    $nivel_id = (pode('niveis') && !$sou_eu) ? ((int)post('nivel_id') ?: null) : ((int)($nivel_x['nivel_id'] ?? 0) ?: null);
    if ($nivel_id && !in_array($nivel_id, array_column($niveis, 'id'), true)) $nivel_id = null;
    $unidade = (int)post('unidade_id') ?: null;
    $tipo = post('tipo_morador') === 'locatario' ? 'locatario' : 'proprietario';
    $ini = data_valida(post('data_inicio')); $fim = data_valida(post('data_fim'));
    $erro = null;
    $existente = valor('SELECT id FROM usuarios WHERE email = ? AND id <> ?', [$email, $x['id'] ?? 0]);
    if ($nome === '') $erro = 'Informe o nome.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'Informe um e-mail válido.';
    elseif ($existente && $x) $erro = 'Já existe um usuário com este e-mail.';
    elseif (!$x && !$nivel_id && !$unidade) $erro = 'Escolha a unidade do morador (ou um nível de acesso para a equipe).';
    elseif ($unidade && !unidade_do_condominio($unidade)) $erro = 'Unidade inválida.';
    elseif ($ini && $fim && $fim < $ini) $erro = 'A data final não pode ser anterior à inicial.';
    if ($erro) flash($erro, 'erro');
    elseif (!$x && $existente) {
        // E-mail já cadastrado (talvez em outro condomínio): um único cadastro acessa todos os condomínios
        $uid = (int)$existente;
        if ($unidade) criar_vinculo_morador($uid, $unidade, $tipo, $ini, $fim);
        if ($nivel_id) definir_nivel($uid, $nivel_id);
        flash('Este e-mail já tinha cadastro. O mesmo acesso passou a valer também neste condomínio.');
        redirect('usuario', ['id' => $uid]);
    } else {
        $novo = ['nome' => $nome, 'email' => $email, 'cpf' => post('cpf') ?: null, 'telefone' => post('telefone') ?: null, 'whatsapp' => post('whatsapp') ?: null, 'ativo' => $x ? (int)$x['ativo'] : 1];
        if ($x) {
            $dif = diferencas(retrato_usuario($x, $nivel_x['nome'] ?? ''), retrato_usuario($novo, $nome_nivel($nivel_id)));
            if (!$dif) { flash('Nenhuma alteração foi feita.'); redirect('usuario', ['id' => $x['id']]); }
            if (post('confirmar') === '') pagina_confirmar_alteracao($dif, 'Registrar alterações?', url('usuario', ['id' => $x['id']]));
            q('UPDATE usuarios SET nome=?, email=?, cpf=?, telefone=?, whatsapp=? WHERE id=?', [$nome, $email, $novo['cpf'], $novo['telefone'], $novo['whatsapp'], $x['id']]);
            definir_nivel((int)$x['id'], $nivel_id);
            atualizar_dados_vinculos((int)$x['id']);
            if (post('confirmar') === 'sim') registrar_historico('usuario', (int)$x['id'], $dif);
            flash('Cadastro atualizado.' . (post('confirmar') === 'sim' ? ' Alterações registradas no histórico.' : '')); redirect('usuario', ['id' => $x['id']]);
        }
        $nova = senha_provisoria();
        q('INSERT INTO usuarios (nome, email, cpf, telefone, whatsapp, senha_hash, trocar_senha) VALUES (?,?,?,?,?,?,1)', [$nome, $email, $novo['cpf'], $novo['telefone'], $novo['whatsapp'], password_hash($nova, PASSWORD_DEFAULT)]);
        $novo_id = (int)db()->lastInsertId();
        if ($unidade) criar_vinculo_morador($novo_id, $unidade, $tipo, $ini, $fim);
        if ($nivel_id) definir_nivel($novo_id, $nivel_id);
        registrar_historico('usuario', $novo_id, ['Cadastro' => ['', 'Criado']]);
        $_SESSION['cred'] = ['id' => $novo_id, 'nome' => $nome, 'email' => $email, 'senha' => $nova, 'tel' => post('whatsapp') ?: post('telefone')];
        if (post('enviar_email')) {
            $ok = enviar_email($email, 'Seu acesso ao aplicativo do ' . conf('condominio_nome', 'condomínio'), "Olá, $nome!\n\nSeu acesso foi criado.\n\nE-mail: $email\nSenha provisória: $nova\n\nNo primeiro acesso você vai criar sua senha pessoal.", ['tipo' => 'acesso', 'botao' => ['Acessar o aplicativo', link_app('login')]]);
            flash($ok ? 'Acesso enviado por e-mail.' : 'Não foi possível enviar o e-mail: ' . ($GLOBALS['ultimo_erro_email'] ?? ''), $ok ? 'ok' : 'erro');
        }
        redirect('usuario', ['id' => $novo_id]);
    }
    $x = array_merge($x ?? [], ['id' => $x['id'] ?? 0, 'ativo' => $x['ativo'] ?? 1, 'nome' => $nome, 'email' => $email, 'cpf' => post('cpf'), 'telefone' => post('telefone'), 'whatsapp' => post('whatsapp')]);
    $nivel_x = $nivel_id ? ['nivel_id' => $nivel_id] : null;
}

$cred = $_SESSION['cred'] ?? null;
if ($cred && $x && $cred['id'] != $x['id']) $cred = null;
unset($_SESSION['cred']);
$vinculos = !empty($x['id']) ? linhas('SELECT v.*, un.bloco, un.numero FROM unidade_vinculos v JOIN unidades un ON un.id = v.unidade_id WHERE v.usuario_id = ? AND un.condominio_id = ? ORDER BY (v.data_fim IS NULL) DESC, v.data_inicio DESC', [$x['id'], cid()]) : [];
$em_outros = !empty($x['id']) ? (int)valor('SELECT (SELECT COUNT(*) FROM acessos WHERE usuario_id = ? AND condominio_id <> ?) + (SELECT COUNT(*) FROM unidade_vinculos v JOIN unidades un ON un.id = v.unidade_id WHERE v.usuario_id = ? AND un.condominio_id <> ?)', [$x['id'], cid(), $x['id'], cid()]) : 0;

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
  <?php if (!empty($x['id'])): ?><div class="perfil-topo"><?= avatar($x, 56) ?><div><strong><?= e($x['nome']) ?></strong><br><span class="muted pequeno"><?= e($x['email']) ?></span></div></div><?php endif; ?>
  <label>Nome completo<input name="nome" value="<?= e($x['nome'] ?? '') ?>" required></label>
  <label>E-mail (será o login)<input type="email" name="email" value="<?= e($x['email'] ?? '') ?>" inputmode="email" required></label>
  <label>CPF<input name="cpf" value="<?= e($x['cpf'] ?? '') ?>" inputmode="numeric" placeholder="000.000.000-00"></label>
  <label>Telefone<input type="tel" name="telefone" value="<?= e($x['telefone'] ?? '') ?>" inputmode="tel"></label>
  <label>WhatsApp<input type="tel" name="whatsapp" value="<?= e($x['whatsapp'] ?? '') ?>" inputmode="tel"></label>
  <?php if (pode('niveis') && !$sou_eu): ?>
    <label>Nível de acesso neste condomínio
      <select name="nivel_id"><option value="">Apenas morador (sem acesso administrativo)</option>
        <?php foreach ($niveis as $n): ?><option value="<?= $n['id'] ?>" <?= (int)($nivel_x['nivel_id'] ?? 0) === (int)$n['id'] ? 'selected' : '' ?>><?= e($n['nome']) ?></option><?php endforeach; ?></select></label>
  <?php endif; ?>
  <?php if (empty($x['id'])): ?>
    <h3>Unidade</h3>
    <label>Unidade<select name="unidade_id"><option value="">— escolha —</option>
      <?php foreach ($unidades as $un): ?><option value="<?= $un['id'] ?>" <?= (int)post('unidade_id') === (int)$un['id'] ? 'selected' : '' ?>><?= e(nome_unidade($un)) ?></option><?php endforeach; ?></select></label>
    <label>Situação<select name="tipo_morador" data-tipo-morador><option value="proprietario">Proprietário</option><option value="locatario">Inquilino / locatário</option></select></label>
    <div class="duas">
      <label><span data-rot-inicio>Data da compra</span><input type="date" name="data_inicio"></label>
      <label><span data-rot-fim>Data da venda</span><input type="date" name="data_fim"></label>
    </div>
    <label class="check"><input type="checkbox" name="enviar_email" value="1"> Enviar o acesso por e-mail</label>
    <p class="muted pequeno">Uma senha provisória será gerada. Se o e-mail já tiver cadastro (até em outro condomínio), o mesmo acesso passa a valer aqui também.</p>
  <?php endif; ?>
  <button class="btn btn-grande"><?= !empty($x['id']) ? 'Salvar alterações' : 'Criar acesso' ?></button>
</form>

<?php if (!empty($x['id'])): ?>
  <h3 class="sec">Unidades neste condomínio <a href="<?= e(url('vinculo', ['usuario' => $x['id']])) ?>">+ adicionar unidade</a></h3>
  <?php foreach ($vinculos as $v): ?>
    <a class="card item" href="<?= e(url('vinculo', ['unidade' => $v['unidade_id'], 'id' => $v['id'], 'origem' => 'usuario'])) ?>">
      <div><strong><?= e(nome_unidade($v)) ?></strong><span class="muted pequeno d-bloco"><?= $v['tipo'] === 'locatario' ? 'Locatário' : 'Proprietário' ?> · <?= $v['data_inicio'] ? data_br($v['data_inicio']) : 'sem data' ?> até <?= $v['data_fim'] ? data_br($v['data_fim']) : 'hoje' ?></span></div>
      <span class="selo <?= !$v['data_fim'] || $v['data_fim'] >= date('Y-m-d') ? 'selo-pago' : 'selo-cancelado' ?>"><?= !$v['data_fim'] || $v['data_fim'] >= date('Y-m-d') ? 'Atual' : 'Anterior' ?></span>
    </a>
  <?php endforeach;
  if (!$vinculos) echo '<div class="card vazio">Nenhuma unidade vinculada.</div>'; ?>

  <div class="card situacao">
    <div><strong><?= $x['ativo'] ? 'Cadastro ativo' : 'Cadastro desativado' ?></strong>
      <span class="muted pequeno d-bloco"><?php
        if ($em_outros) echo 'Este cadastro também vale em outros condomínios. Para tirá-lo daqui, encerre os vínculos com data final.';
        else echo $x['ativo'] ? 'Pode entrar no aplicativo.' : 'Não consegue entrar no aplicativo.'; ?></span></div>
    <?php if (!$sou_eu && !$em_outros): ?>
      <form method="post" data-confirmar="<?= $x['ativo'] ? 'Desativar o acesso desta pessoa?' : 'Reativar o acesso?' ?>"><?= csrf_campo() ?><input type="hidden" name="acao" value="alternar">
        <button class="btn <?= $x['ativo'] ? 'btn-perigo' : '' ?> btn-pequeno"><?= $x['ativo'] ? 'Desativar' : 'Ativar' ?></button></form>
    <?php endif; ?>
  </div>
  <form method="post" data-confirmar="Gerar uma nova senha provisória? A senha atual deixará de funcionar."><?= csrf_campo() ?><input type="hidden" name="acao" value="resetar">
    <button class="btn btn-sec">🔑 Gerar nova senha provisória</button></form>
  <a class="btn btn-sec" href="<?= e(url('historico', ['e' => 'usuario', 'id' => $x['id']])) ?>">🕘 Histórico de alterações</a>
<?php endif;
pagina_fim();
