<?php
declare(strict_types=1);
require __DIR__ . '/src/app.php';

// página => [arquivo, acesso]
// acesso: publico | logado | admin (qualquer nível no condomínio) | super (gestor principal) | nome de uma permissão (ver PERMISSOES em src/app.php)
$rotas = [
    'login' => ['login', 'publico'], 'esqueci' => ['esqueci', 'publico'], 'redefinir' => ['redefinir', 'publico'], 'sair' => ['sair', 'publico'],
    'inicio' => ['inicio', 'logado'], 'perfil' => ['perfil', 'logado'], 'senha' => ['senha', 'logado'], 'foto' => ['foto', 'logado'], 'tema' => ['tema', 'logado'],
    'meus_condominios' => ['meus_condominios', 'logado'],
    'boletos' => ['boletos', 'logado'], 'boleto' => ['boleto', 'logado'],
    'prestacoes' => ['prestacoes', 'logado'], 'prestacao' => ['prestacao', 'logado'],
    'avisos' => ['avisos', 'logado'], 'aviso' => ['aviso', 'logado'], 'arquivo' => ['arquivo', 'logado'], 'mais' => ['mais', 'admin'],
    'usuarios' => ['usuarios', 'moradores'], 'usuario' => ['usuario', 'moradores'], 'unidades' => ['unidades', 'moradores'], 'unidade' => ['unidade', 'moradores'],
    'vinculo' => ['vinculo', 'moradores'], 'historico' => ['historico', 'admin'],
    'aviso_form' => ['aviso_form', 'comunicados'], 'inadimplencia' => ['inadimplencia', 'financeiro_ver'],
    'cobrancas' => ['cobrancas', 'cobrancas'], 'cobranca_gerar' => ['cobranca_gerar', 'cobrancas'], 'cobranca' => ['cobranca', 'cobrancas'],
    'contas_gestao' => ['contas_gestao', 'contas'], 'email_config' => ['email_config', 'emails'],
    'config' => ['config', 'condominio'], 'niveis' => ['niveis', 'niveis'], 'nivel' => ['nivel', 'niveis'], 'condominios' => ['condominios', 'super'],
];

$p = $_GET['p'] ?? (usuario() ? 'inicio' : 'login');
if (!isset($rotas[$p])) { http_response_code(404); $p = 'inicio'; }
[$arquivo, $acesso] = $rotas[$p];
$GLOBALS['pagina_atual'] = $p;

if ($acesso !== 'publico') {
    $u = usuario();
    if (!$u) redirect('login');
    if ($u['trocar_senha'] && !in_array($p, ['senha', 'sair'], true)) { flash('Por segurança, crie uma senha nova para continuar.', 'aviso'); redirect('senha'); }
    $negado = match ($acesso) {
        'logado' => false,
        'admin' => !eh_admin(),
        'super' => !$u['super_admin'],
        default => !pode($acesso),
    };
    if ($negado) { http_response_code(403); exit('Acesso negado.'); }
}
csrf_exigir();
require __DIR__ . '/src/pages/' . $arquivo . '.php';
