<?php
$u = usuario();
pagina_inicio('Início');
$primeiro = e(explode(' ', $u['nome'])[0]);
echo "<h2 class=\"ola\">Olá, $primeiro 👋</h2>";

if (!eh_admin()):
    if (!$u['unidade_id']): ?>
      <div class="card"><p>Seu cadastro ainda não está ligado a uma unidade. Fale com o síndico ou a administração.</p></div>
    <?php else:
      $abertas = linhas("SELECT * FROM cobrancas WHERE unidade_id = ? AND status = 'aberto' ORDER BY vencimento", [$u['unidade_id']]);
      $venc = array_filter($abertas, fn($c) => cobranca_situacao($c) === 'vencido');
      $total = array_sum(array_map(fn($c) => cobranca_atualizada($c)['total'], $abertas));
      $prox = $abertas[0] ?? null; ?>
      <section class="card destaque <?= $venc ? 'destaque-alerta' : '' ?>">
        <?php if (!$prox): ?>
          <p class="rotulo">Situação financeira</p><p class="grande">Tudo em dia ✅</p>
          <p class="muted">Você não tem boletos em aberto.</p>
        <?php else: $at = cobranca_atualizada($prox); ?>
          <p class="rotulo"><?= $venc ? 'Boleto vencido' : 'Próximo boleto' ?> · <?= e(mes_ano($prox['competencia'])) ?></p>
          <p class="grande"><?= dinheiro($at['total']) ?></p>
          <p>Vencimento: <strong><?= data_br($prox['vencimento']) ?></strong></p>
          <a class="btn btn-claro" href="<?= e(url('boleto', ['id' => $prox['id']])) ?>">Ver boleto / pagar</a>
          <?php if (count($abertas) > 1): ?><p class="pequeno">Total em aberto (<?= count($abertas) ?> boletos): <strong><?= dinheiro($total) ?></strong></p><?php endif; ?>
        <?php endif; ?>
      </section>
    <?php endif;
else:
    $mes = date('Y-m-01');
    $res = resumo_mes($mes);
    $em_aberto = linhas("SELECT * FROM cobrancas WHERE status = 'aberto'");
    $vencidos = array_filter($em_aberto, fn($c) => cobranca_situacao($c) === 'vencido');
    $tot_venc = array_sum(array_map(fn($c) => (float)$c['valor'], $vencidos));
    $unid_inad = count(array_unique(array_map(fn($c) => $c['unidade_id'], $vencidos)));
    $moradores = (int)valor("SELECT COUNT(*) FROM usuarios WHERE perfil='condomino' AND ativo=1"); ?>
    <section class="grade-2">
      <div class="card kpi"><p class="rotulo">Recebido em <?= e(MESES[(int)date('n')]) ?></p><p class="grande verde"><?= dinheiro($res['rec_condominio']) ?></p></div>
      <div class="card kpi"><p class="rotulo">Despesas no mês</p><p class="grande"><?= dinheiro($res['despesas']) ?></p></div>
      <a class="card kpi" href="<?= e(url('inadimplencia')) ?>"><p class="rotulo">Inadimplência</p><p class="grande vermelho"><?= dinheiro($tot_venc) ?></p><p class="pequeno muted"><?= $unid_inad ?> unidade(s)</p></a>
      <a class="card kpi" href="<?= e(url('usuarios')) ?>"><p class="rotulo">Moradores</p><p class="grande"><?= $moradores ?></p><p class="pequeno muted">com acesso</p></a>
    </section>
    <h3 class="sec">Ações rápidas</h3>
    <div class="atalhos">
      <a href="<?= e(url('usuario')) ?>" class="atalho"><span class="chip"><?= icone('plus', 22) ?></span>Cadastrar morador</a>
      <a href="<?= e(url('aviso_form')) ?>" class="atalho"><span class="chip"><?= icone('megafone', 22) ?></span>Novo comunicado</a>
      <?php if (eh_gestao()): ?>
        <a href="<?= e(url('cobranca_gerar')) ?>" class="atalho"><span class="chip"><?= icone('boleto', 22) ?></span>Gerar boletos do mês</a>
        <a href="<?= e(url('contas_gestao')) ?>" class="atalho"><span class="chip"><?= icone('contas', 22) ?></span>Prestação de contas</a>
      <?php endif; ?>
    </div>
<?php endif;

$avisos = linhas("SELECT c.*, (SELECT COUNT(*) FROM comunicado_leituras l WHERE l.comunicado_id = c.id AND l.usuario_id = ?) AS lido
                  FROM comunicados c WHERE c.ativo = 1 ORDER BY c.publicado_em DESC LIMIT 3", [$u['id']]);
if ($avisos): ?>
  <h3 class="sec">Últimos avisos <a href="<?= e(url('avisos')) ?>">ver todos</a></h3>
  <?php foreach ($avisos as $a): ?>
    <a class="card item <?= $a['lido'] ? '' : 'nao-lido' ?>" href="<?= e(url('aviso', ['id' => $a['id']])) ?>">
      <strong><?= $a['importante'] ? '⚠️ ' : '' ?><?= e($a['titulo']) ?></strong>
      <span class="muted pequeno"><?= data_br($a['publicado_em']) ?></span>
    </a>
  <?php endforeach;
endif;

$pub = linha('SELECT competencia FROM prestacoes WHERE publicada = 1 ORDER BY competencia DESC LIMIT 1');
if ($pub): ?>
  <h3 class="sec">Prestação de contas</h3>
  <a class="card item" href="<?= e(url('prestacao', ['c' => substr($pub['competencia'], 0, 7)])) ?>">
    <strong>📊 <?= e(mes_ano($pub['competencia'])) ?></strong><span class="muted pequeno">Ver receitas e despesas</span>
  </a>
<?php endif;
pagina_fim();
