<?php
declare(strict_types=1);
require __DIR__ . '/src/app.php';

// página => [arquivo, acesso]  (acesso: publico | logado | admin | gestao)
$rotas = [
    'login' => ['login', 'publico'], 'esqueci' => ['esqueci', 'publico'], 'redefinir' => ['redefinir', 'publico'], 'sair' => ['sair', 'publico'],
    'inicio' => ['inicio', 'logado'], 'perfil' => ['perfil', 'logado'], 'senha' => ['senha', 'logado'],
    'boletos' => ['boletos', 'logado'], 'boleto' => ['boleto', 'logado'],
    'prestacoes' => ['prestacoes', 'logado'], 'prestacao' => ['prestacao', 'logado'],
    'avisos' => ['avisos', 'logado'], 'aviso' => ['aviso', 'logado'], 'arquivo' => ['arquivo', 'logado'], 'mais' => ['mais', 'admin'],
    'usuarios' => ['usuarios', 'admin'], 'usuario' => ['usuario', 'admin'], 'unidades' => ['unidades', 'admin'], 'unidade' => ['unidade', 'admin'],
    'aviso_form' => ['aviso_form', 'admin'], 'inadimplencia' => ['inadimplencia', 'admin'],
    'cobrancas' => ['cobrancas', 'gestao'], 'cobranca_gerar' => ['cobranca_gerar', 'gestao'], 'cobranca' => ['cobranca', 'gestao'],
    'contas_gestao' => ['contas_gestao', 'gestao'], 'config' => ['config', 'gestao'],
];

$p = $_GET['p'] ?? (usuario() ? 'inicio' : 'login');
if (!isset($rotas[$p])) { http_response_code(404); $p = 'inicio'; }
[$arquivo, $acesso] = $rotas[$p];
$GLOBALS['pagina_atual'] = $p;

if ($acesso !== 'publico') {
    $u = usuario();
    if (!$u) redirect('login');
    if ($u['trocar_senha'] && $p !== 'senha' && $p !== 'sair') { flash('Por segurança, crie uma senha nova para continuar.', 'aviso'); redirect('senha'); }
    if ($acesso === 'admin' && !eh_admin()) { http_response_code(403); exit('Acesso negado.'); }
    if ($acesso === 'gestao' && !eh_gestao()) { http_response_code(403); exit('Acesso restrito à gestão.'); }
}
csrf_exigir();
require __DIR__ . '/src/pages/' . $arquivo . '.php';
