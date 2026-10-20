<?php
pagina_inicio('Mais opções');
$grupos = [];
if (eh_gestao()) $grupos['Financeiro'] = [['cobrancas', 'boleto', 'Cobranças e boletos'], ['cobranca_gerar', 'plus', 'Gerar boletos do mês'], ['contas_gestao', 'contas', 'Prestação de contas'], ['inadimplencia', 'alerta', 'Inadimplência']];
else $grupos['Financeiro'] = [['inadimplencia', 'alerta', 'Inadimplência'], ['prestacoes', 'contas', 'Prestação de contas']];
$grupos['Cadastros'] = [['usuarios', 'moradores', 'Moradores e acessos'], ['unidades', 'predio', 'Unidades, proprietários e locatários']];
$grupos['Comunicação'] = [['aviso_form', 'megafone', 'Novo comunicado']];
if (eh_gestao()) { $grupos['Comunicação'][] = ['email_config', 'email', 'E-mails e lembretes']; $grupos['Condomínio'] = [['config', 'config', 'Dados do condomínio']]; }
$grupos['Minha conta'] = [['perfil', 'perfil', 'Meu perfil'], ['sair', 'sair', 'Sair']];
foreach ($grupos as $titulo => $itens): ?>
  <h3 class="sec"><?= e($titulo) ?></h3>
  <div class="lista-menu">
  <?php foreach ($itens as [$p, $ic, $rot]): ?>
    <a class="card item" href="<?= e(url($p)) ?>"><span class="item-ic"><span class="chip"><?= icone($ic, 20) ?></span><?= e($rot) ?></span><span class="seta">›</span></a>
  <?php endforeach; ?>
  </div>
<?php endforeach;
pagina_fim();
