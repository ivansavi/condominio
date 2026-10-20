<?php
$comp = competencia_valida(get('c', post('competencia', date('Y-m')))) ?? date('Y-m-01');
$unidades = linhas('SELECT * FROM unidades WHERE ativo = 1 ORDER BY bloco, CAST(numero AS UNSIGNED), numero');
if (eh_post()) {
    $venc = post('vencimento'); $desc = post('descricao') ?: 'Taxa de condomínio';
    $uniforme = post('valor_unico') !== '' ? ler_dinheiro(post('valor_unico')) : null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $venc)) flash('Informe a data de vencimento.', 'erro');
    else {
        $n = 0; $ja = 0; $sem = 0;
        foreach ($unidades as $un) {
            $v = $uniforme ?? (float)$un['valor_condominio'];
            if ($v <= 0) { $sem++; continue; }
            if (valor("SELECT id FROM cobrancas WHERE unidade_id = ? AND competencia = ? AND descricao = ? AND status <> 'cancelado'", [$un['id'], $comp, $desc])) { $ja++; continue; }
            q('INSERT INTO cobrancas (unidade_id, competencia, descricao, valor, vencimento, pix_copia_cola) VALUES (?,?,?,?,?,NULL)', [$un['id'], $comp, $desc, $v, $venc]);
            $n++;
        }
        flash("$n boleto(s) gerado(s)." . ($ja ? " $ja já existia(m)." : '') . ($sem ? " $sem unidade(s) sem valor definido foram ignoradas." : ''));
        redirect('cobrancas', ['c' => substr($comp, 0, 7)]);
    }
}
$dia = max(1, min(28, (int)conf('dia_vencimento', '10')));
pagina_inicio('Gerar boletos do mês', ['voltar' => url('cobrancas')]);
?>
<form method="post" class="form card"><?= csrf_campo() ?>
  <p class="muted">Cria uma cobrança para cada unidade ativa, usando o valor da taxa cadastrado em cada unidade. Cobranças repetidas são ignoradas.</p>
  <label>Mês de referência<input type="month" name="competencia" value="<?= e(substr($comp, 0, 7)) ?>" required></label>
  <label>Vencimento<input type="date" name="vencimento" value="<?= e(date('Y-m-', strtotime($comp)) . str_pad((string)$dia, 2, '0', STR_PAD_LEFT)) ?>" required></label>
  <label>Descrição<input name="descricao" value="Taxa de condomínio"></label>
  <label>Valor único para todas (opcional)<input name="valor_unico" inputmode="decimal" placeholder="Deixe vazio para usar o valor de cada unidade"></label>
  <p class="muted pequeno"><?= count($unidades) ?> unidade(s) ativa(s). Depois de gerar, abra cada cobrança para anexar o PDF do boleto do banco, linha digitável ou Pix.</p>
  <button class="btn btn-grande">Gerar boletos</button>
</form>
<?php pagina_fim();
