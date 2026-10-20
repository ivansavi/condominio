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
    $bloco = post('bloco'); $numero = post('numero'); $valor = ler_dinheiro(post('valor_condominio')); $ativo = post('ativo') ? 1 : 0;
    if ($numero === '') flash('Informe o número da unidade.', 'erro');
    elseif (valor('SELECT id FROM unidades WHERE bloco = ? AND numero = ? AND id <> ?', [$bloco, $numero, $id])) flash('Esta unidade já existe.', 'erro');
    elseif ($x) { q('UPDATE unidades SET bloco=?, numero=?, valor_condominio=?, ativo=? WHERE id=?', [$bloco, $numero, $valor, $ativo, $id]); flash('Unidade atualizada.'); redirect('unidades'); }
    else { q('INSERT INTO unidades (bloco, numero, valor_condominio) VALUES (?,?,?)', [$bloco, $numero, $valor]); flash('Unidade cadastrada.'); redirect('unidades'); }
}
pagina_inicio($x ? 'Editar unidade' : 'Nova unidade', ['voltar' => url('unidades')]);
?>
<form method="post" class="form card"><?= csrf_campo() ?>
  <label>Bloco / Torre (opcional)<input name="bloco" value="<?= e($x['bloco'] ?? '') ?>"></label>
  <label>Número do apartamento / casa<input name="numero" value="<?= e($x['numero'] ?? '') ?>" required></label>
  <label>Valor da taxa de condomínio (R$)<input name="valor_condominio" value="<?= $x ? number_format((float)$x['valor_condominio'], 2, ',', '.') : '' ?>" inputmode="decimal" placeholder="450,00"></label>
  <?php if ($x): ?><label class="check"><input type="checkbox" name="ativo" value="1" <?= $x['ativo'] ? 'checked' : '' ?>> Unidade ativa</label><?php endif; ?>
  <button class="btn btn-grande">Salvar</button>
</form>
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
