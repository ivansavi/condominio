<?php
$id = (int)get('id');
$v = $id ? linha('SELECT v.* FROM unidade_vinculos v JOIN unidades un ON un.id = v.unidade_id WHERE v.id = ? AND un.condominio_id = ?', [$id, cid()]) : null;
if ($id && !$v) redirect('unidades');
$usr = null;
if ($v && $v['usuario_id']) $usr = linha('SELECT * FROM usuarios WHERE id = ?', [$v['usuario_id']]);
elseif (!$v && get('usuario')) $usr = linha('SELECT * FROM usuarios WHERE id = ?', [(int)get('usuario')]);
if (!$v && get('usuario') && !$usr) redirect('usuarios');
$unidades = linhas('SELECT * FROM unidades WHERE condominio_id = ? AND ativo = 1 ORDER BY bloco, CAST(numero AS UNSIGNED), numero', [cid()]);
$un = $v ? unidade_do_condominio((int)$v['unidade_id']) : (get('unidade') ? unidade_do_condominio((int)get('unidade')) : null);
if (!$v && !$un && !$usr) redirect('unidades');
$tipo = $v['tipo'] ?? (post('tipo', get('tipo')) === 'locatario' ? 'locatario' : 'proprietario');
$origem = get('origem', post('origem')) === 'usuario' || (!$v && get('usuario')) ? 'usuario' : 'unidade';
$volta = $origem === 'usuario' && $usr ? url('usuario', ['id' => $usr['id']]) : ($un ? url('unidade', ['id' => $un['id']]) : url('unidades'));

if (eh_post()) {
    $rotulo = $tipo === 'locatario' ? 'locatário' : 'proprietário';
    $uid_hist = $usr ? (int)$usr['id'] : null;
    if ($v && post('acao') === 'excluir') {
        q('DELETE FROM unidade_vinculos WHERE id = ?', [$v['id']]);
        registrar_historico('unidade', (int)$v['unidade_id'], [ucfirst($rotulo) => [$v['nome'], 'Removido do histórico']]);
        if ($uid_hist) registrar_historico('usuario', $uid_hist, ['Unidade vinculada' => [nome_unidade($un), 'Vínculo removido']]);
        flash('Registro removido.'); redirect($origem === 'usuario' && $usr ? 'usuario' : 'unidade', $origem === 'usuario' && $usr ? ['id' => $usr['id']] : ['id' => $v['unidade_id']]);
    }
    $unidade_id = $v ? (int)$v['unidade_id'] : (int)(post('unidade_id') ?: ($un['id'] ?? 0));
    $unidade = unidade_do_condominio($unidade_id);
    $nome = $usr['nome'] ?? post('nome'); $ini = data_valida(post('data_inicio')); $fim = data_valida(post('data_fim'));
    $email = $usr['email'] ?? strtolower(post('email'));
    if (!$unidade) flash('Escolha a unidade.', 'erro');
    elseif ($nome === '') flash('Informe o nome.', 'erro');
    elseif ($ini && $fim && $fim < $ini) flash('A data final não pode ser anterior à data inicial.', 'erro');
    else {
        $linkar = $usr ? (int)$usr['id'] : null;
        if (!$linkar && $email !== '' && ($ex = valor('SELECT id FROM usuarios WHERE email = ?', [$email]))) $linkar = (int)$ex; // mesmo e-mail = mesmo cadastro
        $cred = null;
        if (!$linkar && post('criar_acesso') && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $senha = senha_provisoria();
            q('INSERT INTO usuarios (nome, email, cpf, telefone, senha_hash, trocar_senha) VALUES (?,?,?,?,?,1)', [$nome, $email, post('cpf') ?: null, post('telefone') ?: null, password_hash($senha, PASSWORD_DEFAULT)]);
            $linkar = (int)db()->lastInsertId();
            registrar_historico('usuario', $linkar, ['Cadastro' => ['', 'Criado']]);
            $cred = ['id' => $linkar, 'nome' => $nome, 'email' => $email, 'senha' => $senha, 'tel' => post('telefone')];
        }
        $dados = [$nome, post('cpf') ?: ($usr['cpf'] ?? null), post('telefone') ?: ($usr['telefone'] ?? null), $email ?: null, $ini, $fim, post('observacao') ?: null];
        if ($v) {
            q('UPDATE unidade_vinculos SET tipo=?, nome=?, cpf=?, telefone=?, email=?, data_inicio=?, data_fim=?, observacao=?, usuario_id=? WHERE id=?', [$tipo, ...$dados, $linkar, $v['id']]);
            $dif = diferencas(
                ['Tipo' => $v['tipo'] === 'locatario' ? 'Locatário' : 'Proprietário', 'Nome' => $v['nome'], 'Início' => $v['data_inicio'] ? data_br($v['data_inicio']) : '', 'Fim' => $v['data_fim'] ? data_br($v['data_fim']) : ''],
                ['Tipo' => $rotulo === 'locatário' ? 'Locatário' : 'Proprietário', 'Nome' => $nome, 'Início' => $ini ? data_br($ini) : '', 'Fim' => $fim ? data_br($fim) : '']);
            registrar_historico('unidade', (int)$unidade['id'], array_combine(array_map(fn($k) => ucfirst($rotulo) . ' · ' . $k, array_keys($dif)), array_values($dif)));
        } else {
            if (!$fim && post('encerrar_atual') && $ini) q('UPDATE unidade_vinculos SET data_fim = ? WHERE unidade_id = ? AND tipo = ? AND data_fim IS NULL', [$ini, $unidade['id'], $tipo]);
            q('INSERT INTO unidade_vinculos (tipo, nome, cpf, telefone, email, data_inicio, data_fim, observacao, usuario_id, unidade_id) VALUES (?,?,?,?,?,?,?,?,?,?)', [$tipo, ...$dados, $linkar, $unidade['id']]);
            registrar_historico('unidade', (int)$unidade['id'], [ucfirst($rotulo) => ['', "Adicionado: $nome" . ($ini ? ' (desde ' . data_br($ini) . ')' : '')]]);
            if ($linkar) registrar_historico('usuario', $linkar, ['Unidade vinculada' => ['', nome_unidade($unidade) . " ($rotulo)" . ($ini ? ' desde ' . data_br($ini) : '')]]);
        }
        if ($cred) { $_SESSION['cred'] = $cred; flash('Acesso ao aplicativo criado. Anote a senha provisória.'); redirect('usuario', ['id' => $linkar]); }
        flash('Dados salvos.'); redirect($origem === 'usuario' && $usr ? 'usuario' : 'unidade', $origem === 'usuario' && $usr ? ['id' => $usr['id']] : ['id' => $unidade['id']]);
    }
    $v = array_merge($v ?? [], ['nome' => $nome, 'cpf' => post('cpf'), 'telefone' => post('telefone'), 'email' => $email, 'data_inicio' => $ini, 'data_fim' => $fim, 'observacao' => post('observacao')]);
}
$rotulo = $tipo === 'locatario' ? 'locatário' : 'proprietário';
pagina_inicio(($id ? 'Editar ' : 'Novo ') . 'vínculo', ['voltar' => $volta]);
?>
<?php if ($un && $id): ?><p class="muted"><?= e(nome_unidade($un)) ?></p><?php endif; ?>
<form method="post" class="form card"><?= csrf_campo() ?><input type="hidden" name="origem" value="<?= e($origem) ?>">
  <?php if ($usr): ?><div class="perfil-topo"><?= avatar($usr, 48) ?><div><strong><?= e($usr['nome']) ?></strong><br><span class="muted pequeno">Dados vêm do cadastro do morador</span></div></div><?php endif; ?>
  <?php if (!$id): ?>
    <label>Unidade<select name="unidade_id" required><option value="">— escolha —</option>
      <?php foreach ($unidades as $u2): ?><option value="<?= $u2['id'] ?>" <?= (int)($un['id'] ?? 0) === (int)$u2['id'] ? 'selected' : '' ?>><?= e(nome_unidade($u2)) ?></option><?php endforeach; ?></select></label>
  <?php endif; ?>
  <label>Situação<select name="tipo" data-tipo-morador>
    <option value="proprietario" <?= $tipo === 'proprietario' ? 'selected' : '' ?>>Proprietário</option>
    <option value="locatario" <?= $tipo === 'locatario' ? 'selected' : '' ?>>Inquilino / locatário</option></select></label>
  <div class="duas">
    <label><span data-rot-inicio>Data da compra</span><input type="date" name="data_inicio" value="<?= e($v['data_inicio'] ?? '') ?>"></label>
    <label><span data-rot-fim>Data da venda</span><input type="date" name="data_fim" value="<?= e($v['data_fim'] ?? '') ?>"></label>
  </div>
  <?php if (!$usr): ?>
    <label>Nome completo<input name="nome" value="<?= e($v['nome'] ?? '') ?>" required></label>
    <label>CPF<input name="cpf" value="<?= e($v['cpf'] ?? '') ?>" inputmode="numeric"></label>
    <label>Telefone<input type="tel" name="telefone" value="<?= e($v['telefone'] ?? '') ?>" inputmode="tel"></label>
    <label>E-mail<input type="email" name="email" value="<?= e($v['email'] ?? '') ?>" inputmode="email"></label>
    <?php if (empty($v['usuario_id'])): ?><label class="check"><input type="checkbox" name="criar_acesso" value="1"> Criar acesso ao aplicativo (precisa de e-mail)</label><?php endif; ?>
  <?php endif; ?>
  <label>Observação<input name="observacao" value="<?= e($v['observacao'] ?? '') ?>"></label>
  <?php if (!$id): ?><label class="check"><input type="checkbox" name="encerrar_atual" value="1" checked> Encerrar o vínculo atual do mesmo tipo na data de início deste</label><?php endif; ?>
  <p class="muted pequeno">Deixe a data final vazia para quem ainda está na unidade. Se o e-mail já tiver cadastro, o vínculo é ligado a ele automaticamente.</p>
  <button class="btn btn-grande">Salvar</button>
</form>
<?php if ($id): ?>
  <form method="post" data-confirmar="Remover este registro do histórico?"><?= csrf_campo() ?><input type="hidden" name="origem" value="<?= e($origem) ?>"><input type="hidden" name="acao" value="excluir"><button class="btn btn-perigo">Remover registro</button></form>
<?php endif;
pagina_fim();
