<?php
$id = (int)get('id');
$x = $id ? linha('SELECT * FROM unidades WHERE id = ?', [$id]) : null;
if ($id && !$x) redirect('unidades');
if (eh_post()) {
    if (post('acao') === 'lote') { // cadastro em lote: "101,102,103" ou "101-110"
        $bloco = post('bloco'); $valor = ler_dinheiro(post('valor_condominio')); $n = 0;
        foreach (preg_split('/[\s,;]+/', post('lista'), -1, PREG_SPLIT_NO_EMPTY) as $tok) {
            $nums = [$tok];
            if (preg_match('/^(\d+)-(\d+)$/', $tok, $m) && (int)$m[2] - (int)$m[1] <= 500) $nums = range((int)$m[1], (int)$m[2]);
            foreach ($nums as $num) {
                if (!valor('SELECT id FROM unidades WHERE bloco = ? AND numero = ?', [$bloco, (string)$num])) {
                    q('INSERT INTO unidades (bloco, numero, valor_condominio) VALUES (?,?,?)', [$bloco, (string)$num, $valor]); $n++;
                }
            }
        }
        flash("$n unidade(s) cadastrada(s)."); redirect('unidades');
    }
    $novo = ['bloco' => post('bloco'), 'numero' => post('numero'), 'valor_condominio' => ler_dinheiro(post('valor_condominio')), 'ativo' => $x ? (post('ativo') ? 1 : 0) : 1];
    if ($novo['numero'] === '') flash('Informe o número da unidade.', 'erro');
    elseif (valor('SELECT id FROM unidades WHERE bloco = ? AND numero = ? AND id <> ?', [$novo['bloco'], $novo['numero'], $id])) flash('Esta unidade já existe.', 'erro');
    elseif ($x) {
        $dif = diferencas(retrato_unidade($x), retrato_unidade($novo));
        if (!$dif) { flash('Nenhuma alteração foi feita.'); redirect('unidade', ['id' => $id]); }
        if (post('confirmar') === '') pagina_confirmar_alteracao($dif, 'Registrar alterações?', url('unidade', ['id' => $id]));
        q('UPDATE unidades SET bloco=?, numero=?, valor_condominio=?, ativo=? WHERE id=?', [...array_values($novo), $id]);
        if (post('confirmar') === 'sim') registrar_historico('unidade', $id, $dif);
        flash('Unidade atualizada.'); redirect('unidade', ['id' => $id]);
    } else {
        q('INSERT INTO unidades (bloco, numero, valor_condominio) VALUES (?,?,?)', [$novo['bloco'], $novo['numero'], $novo['valor_condominio']]);
        registrar_historico('unidade', (int)db()->lastInsertId(), ['Cadastro' => ['', 'Criada']]);
        flash('Unidade cadastrada.'); redirect('unidades');
    }
    $x = array_merge($x ?? [], $novo);
}
pagina_inicio($x ? nome_unidade($x) : 'Nova unidade', ['voltar' => url('unidades')]);
?>
<form method="post" class="form card"><?= csrf_campo() ?>
  <label>Bloco / Torre (opcional)<input name="bloco" value="<?= e($x['bloco'] ?? '') ?>"></label>
  <label>Número do apartamento / casa<input name="numero" value="<?= e($x['numero'] ?? '') ?>" required></label>
  <label>Valor da taxa de condomínio (R$)<input name="valor_condominio" value="<?= !empty($x) ? number_format((float)$x['valor_condominio'], 2, ',', '.') : '' ?>" inputmode="decimal" placeholder="450,00"></label>
  <?php if ($x): ?><label class="check"><input type="checkbox" name="ativo" value="1" <?= $x['ativo'] ? 'checked' : '' ?>> Unidade ativa</label><?php endif; ?>
  <button class="btn btn-grande">Salvar</button>
</form>

<?php if ($x && !empty($x['id'])):
    $vs = linhas('SELECT v.*, u.ativo AS usuario_ativo FROM unidade_vinculos v LEFT JOIN usuarios u ON u.id = v.usuario_id WHERE v.unidade_id = ? ORDER BY (v.data_fim IS NULL) DESC, v.data_inicio DESC, v.id DESC', [$x['id']]);
    $donos = array_values(array_filter($vs, fn($v) => $v['tipo'] === 'proprietario'));
    $locs = array_values(array_filter($vs, fn($v) => $v['tipo'] === 'locatario'));
    $bloco_vinc = function (array $lista, string $tipo, string $titulo, string $vazio) use ($x) { ?>
      <h3 class="sec"><?= e($titulo) ?> <a href="<?= e(url('vinculo', ['unidade' => $x['id'], 'tipo' => $tipo])) ?>">+ adicionar</a></h3>
      <?php foreach ($lista as $v):
          $atual = !$v['data_fim']; ?>
        <a class="card item" href="<?= e(url('vinculo', ['unidade' => $x['id'], 'id' => $v['id']])) ?>">
          <div><strong><?= e($v['nome']) ?></strong>
            <span class="muted pequeno d-bloco"><?= $v['data_inicio'] ? data_br($v['data_inicio']) : 'data não informada' ?> até <?= $v['data_fim'] ? data_br($v['data_fim']) : 'hoje' ?><?= $v['usuario_id'] ? ' · com acesso ao app' : '' ?></span></div>
          <span class="selo <?= $atual ? 'selo-pago' : 'selo-cancelado' ?>"><?= $atual ? 'Atual' : 'Anterior' ?></span>
        </a>
      <?php endforeach;
      if (!$lista) echo '<div class="card vazio">', e($vazio), '</div>';
    };
    $bloco_vinc($donos, 'proprietario', 'Proprietários (histórico)', 'Nenhum proprietário registrado.');
    $bloco_vinc($locs, 'locatario', 'Locatários', 'Nenhum locatário registrado.'); ?>
  <a class="btn btn-sec" href="<?= e(url('historico', ['e' => 'unidade', 'id' => $x['id']])) ?>">🕘 Histórico de alterações da unidade</a>
  <a class="btn btn-sec" href="<?= e(url('boletos', ['unidade' => $x['id']])) ?>">💰 Histórico financeiro da unidade</a>
<?php endif; ?>

<?php if (!$x): ?>
<form method="post" class="form card"><?= csrf_campo() ?><input type="hidden" name="acao" value="lote">
  <h3>Cadastrar várias de uma vez</h3>
  <label>Bloco / Torre (opcional)<input name="bloco"></label>
  <label>Números<input name="lista" placeholder="101-110, 201-210, 301" required></label>
  <label>Valor da taxa (R$)<input name="valor_condominio" inputmode="decimal" placeholder="450,00"></label>
  <button class="btn btn-sec">Cadastrar em lote</button>
</form>
<?php endif;
pagina_fim();
