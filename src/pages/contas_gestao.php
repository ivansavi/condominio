<?php
$comp = competencia_valida(get('c', post('c', date('Y-m')))) ?? date('Y-m-01');
$ym = substr($comp, 0, 7);
q('INSERT IGNORE INTO prestacoes (condominio_id, competencia) VALUES (?, ?)', [cid(), $comp]);
$CATEGORIAS = ['Folha e encargos', 'Água e esgoto', 'Energia elétrica', 'Limpeza', 'Segurança / portaria', 'Manutenção', 'Elevadores', 'Administração', 'Seguros', 'Impostos e tarifas', 'Jardinagem', 'Outras despesas'];

if (eh_post()) {
    $acao = post('acao');
    try {
        if ($acao === 'lancar') {
            $valor = ler_dinheiro(post('valor')); $data = post('data'); $tipo = post('tipo') === 'receita' ? 'receita' : 'despesa';
            if ($valor <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || post('descricao') === '') throw new RuntimeException('Preencha descrição, data e valor.');
            q('INSERT INTO lancamentos (condominio_id, competencia, tipo, categoria, descricao, valor, data) VALUES (?,?,?,?,?,?,?)', [cid(), $comp, $tipo, post('categoria') ?: 'Outras despesas', post('descricao'), $valor, $data]);
            flash('Lançamento adicionado.');
        } elseif ($acao === 'excluir_lanc') {
            q('DELETE FROM lancamentos WHERE id = ? AND condominio_id = ? AND competencia = ?', [(int)post('id'), cid(), $comp]); flash('Lançamento removido.');
        } elseif ($acao === 'obs') {
            q('UPDATE prestacoes SET observacao = ? WHERE condominio_id = ? AND competencia = ?', [post('observacao') ?: null, cid(), $comp]); flash('Mensagem salva.');
        } elseif ($acao === 'publicar') {
            q('UPDATE prestacoes SET publicada = 1, publicada_em = NOW() WHERE condominio_id = ? AND competencia = ?', [cid(), $comp]); flash('Prestação publicada para os condôminos!');
            if (post('avisar_email')) { [$ok, $falha] = enviar_para_condominos('Prestação de contas de ' . mes_ano($comp), 'A prestação de contas de ' . mes_ano($comp) . " já está disponível no aplicativo.", ['tipo' => 'prestacao', 'botao' => ['Ver prestação de contas', link_app('prestacao', ['c' => $ym])]]); flash("$ok e-mail(s) enviado(s)" . ($falha ? ", $falha falha(s)" : '') . '.'); }
        } elseif ($acao === 'despublicar') {
            q('UPDATE prestacoes SET publicada = 0 WHERE condominio_id = ? AND competencia = ?', [cid(), $comp]); flash('Prestação ocultada.');
        } elseif ($acao === 'anexar') {
            $arq = salvar_upload('arquivo'); if (!$arq) throw new RuntimeException('Escolha um arquivo.');
            q('INSERT INTO prestacao_anexos (condominio_id, competencia, titulo, arquivo_id) VALUES (?,?,?,?)', [cid(), $comp, post('titulo') ?: 'Documento', $arq]); flash('Documento anexado.');
        } elseif ($acao === 'excluir_anexo') {
            q('DELETE FROM prestacao_anexos WHERE id = ? AND condominio_id = ? AND competencia = ?', [(int)post('id'), cid(), $comp]); flash('Documento removido.');
        }
    } catch (RuntimeException $ex) { flash($ex->getMessage(), 'erro'); }
    redirect('contas_gestao', ['c' => $ym]);
}

$pr = linha('SELECT * FROM prestacoes WHERE condominio_id = ? AND competencia = ?', [cid(), $comp]);
$r = resumo_mes($comp);
$anexos = linhas('SELECT * FROM prestacao_anexos WHERE condominio_id = ? AND competencia = ? ORDER BY id', [cid(), $comp]);
$ant = date('Y-m', strtotime($comp . ' -1 month')); $prox = date('Y-m', strtotime($comp . ' +1 month'));
pagina_inicio('Prestação de contas');
?>
<div class="mes-nav">
  <a href="<?= e(url('contas_gestao', ['c' => $ant])) ?>" aria-label="Mês anterior">‹</a><strong><?= e(mes_ano($comp)) ?></strong>
  <a href="<?= e(url('contas_gestao', ['c' => $prox])) ?>" aria-label="Próximo mês">›</a>
</div>
<section class="card resumo">
  <div class="linha-r"><span>Saldo anterior</span><strong><?= dinheiro($r['saldo_anterior']) ?></strong></div>
  <div class="linha-r"><span>Receitas (<?= count($r['pagas']) ?> pagamentos de condomínio + outras)</span><strong class="verde"><?= dinheiro($r['receitas']) ?></strong></div>
  <div class="linha-r"><span>Despesas</span><strong class="vermelho"><?= dinheiro($r['despesas']) ?></strong></div>
  <div class="linha-r total"><span>Saldo final</span><strong><?= dinheiro($r['saldo_final']) ?></strong></div>
</section>
<p class="muted pequeno">As taxas de condomínio recebidas entram sozinhas, conforme você dá baixa nas cobranças. Lance aqui as despesas e outras receitas.</p>

<form method="post" class="form card"><?= csrf_campo() ?><input type="hidden" name="acao" value="lancar"><input type="hidden" name="c" value="<?= e($ym) ?>">
  <h3>Novo lançamento</h3>
  <label>Tipo<select name="tipo"><option value="despesa">Despesa</option><option value="receita">Outra receita</option></select></label>
  <label>Categoria<select name="categoria"><?php foreach ($CATEGORIAS as $cat) echo '<option>', e($cat), '</option>'; ?><option>Multas e juros recebidos</option><option>Aluguel de salão</option></select></label>
  <label>Descrição<input name="descricao" placeholder="Ex.: Conta de luz - áreas comuns" required></label>
  <label>Data<input type="date" name="data" value="<?= e(min(date('Y-m-d'), date('Y-m-t', strtotime($comp)))) ?>" required></label>
  <label>Valor (R$)<input name="valor" inputmode="decimal" required></label>
  <button class="btn">Adicionar</button>
</form>

<h3 class="sec">Lançamentos do mês</h3>
<?php foreach ($r['lancamentos'] as $l): ?>
  <div class="card item">
    <div><strong><?= e($l['descricao']) ?></strong><span class="muted pequeno d-bloco"><?= data_br($l['data']) ?> · <?= e($l['categoria']) ?></span></div>
    <div class="dir"><strong class="<?= $l['tipo'] === 'receita' ? 'verde' : 'vermelho' ?>"><?= $l['tipo'] === 'receita' ? '+' : '−' ?> <?= dinheiro($l['valor']) ?></strong>
      <form method="post" data-confirmar="Remover este lançamento?"><?= csrf_campo() ?><input type="hidden" name="acao" value="excluir_lanc"><input type="hidden" name="c" value="<?= e($ym) ?>"><input type="hidden" name="id" value="<?= $l['id'] ?>"><button class="mini">remover</button></form></div>
  </div>
<?php endforeach;
if (!$r['lancamentos']) echo '<div class="card vazio">Nenhum lançamento neste mês.</div>'; ?>

<h3 class="sec">Documentos (balancete, notas, extratos)</h3>
<?php foreach ($anexos as $a): ?>
  <div class="card item"><a href="<?= e(url('arquivo', ['id' => $a['arquivo_id']])) ?>" target="_blank" rel="noopener">📎 <?= e($a['titulo']) ?></a>
    <form method="post" data-confirmar="Remover este documento?"><?= csrf_campo() ?><input type="hidden" name="acao" value="excluir_anexo"><input type="hidden" name="c" value="<?= e($ym) ?>"><input type="hidden" name="id" value="<?= $a['id'] ?>"><button class="mini">remover</button></form></div>
<?php endforeach; ?>
<form method="post" enctype="multipart/form-data" class="form card"><?= csrf_campo() ?><input type="hidden" name="acao" value="anexar"><input type="hidden" name="c" value="<?= e($ym) ?>">
  <label>Título<input name="titulo" placeholder="Ex.: Balancete de <?= e(mes_ano($comp)) ?>"></label>
  <label>Arquivo (PDF, JPG ou PNG até 5 MB)<input type="file" name="arquivo" accept="application/pdf,image/jpeg,image/png" required></label>
  <button class="btn btn-sec">Anexar documento</button>
</form>

<form method="post" class="form card"><?= csrf_campo() ?><input type="hidden" name="acao" value="obs"><input type="hidden" name="c" value="<?= e($ym) ?>">
  <label>Mensagem do síndico (opcional, aparece no topo da prestação)<textarea name="observacao" rows="4"><?= e($pr['observacao'] ?? '') ?></textarea></label>
  <button class="btn btn-sec">Salvar mensagem</button>
</form>

<a class="btn btn-sec" href="<?= e(url('prestacao', ['c' => $ym])) ?>">👁️ Pré-visualizar como o morador vê</a>
<form method="post"><?= csrf_campo() ?><input type="hidden" name="c" value="<?= e($ym) ?>">
  <?php if ($pr['publicada']): ?>
    <p class="alerta alerta-ok">✅ Publicada em <?= data_br($pr['publicada_em']) ?></p>
    <input type="hidden" name="acao" value="despublicar"><button class="btn btn-sec">Ocultar dos condôminos</button>
  <?php else: ?>
    <label class="check"><input type="checkbox" name="avisar_email" value="1"> Avisar os condôminos por e-mail</label>
    <input type="hidden" name="acao" value="publicar"><button class="btn btn-grande">📢 Publicar para os condôminos</button>
  <?php endif; ?>
</form>
<?php pagina_fim();
