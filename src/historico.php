<?php
declare(strict_types=1);

/** Compara dois "retratos" (rótulo => texto) e devolve só o que mudou: rótulo => [antes, depois]. */
function diferencas(array $antes, array $depois): array {
    $dif = [];
    foreach ($depois as $rotulo => $novo) {
        $velho = $antes[$rotulo] ?? '';
        if ((string)$velho !== (string)$novo) $dif[$rotulo] = [(string)$velho, (string)$novo];
    }
    return $dif;
}

function registrar_historico(string $entidade, int $id, array $dif): void {
    if (!$dif) return;
    $u = usuario(); $lote = bin2hex(random_bytes(8));
    foreach ($dif as $campo => [$velho, $novo]) {
        q('INSERT INTO historico_alteracoes (entidade, entidade_id, lote, campo, valor_antigo, valor_novo, autor_id, autor_nome) VALUES (?,?,?,?,?,?,?,?)',
            [$entidade, $id, $lote, $campo, $velho === '' ? null : $velho, $novo === '' ? null : $novo, $u['id'] ?? null, $u['nome'] ?? null]);
    }
}

function sim_nao($v): string { return $v ? 'Sim' : 'Não'; }

function retrato_usuario(array $u, ?string $nivel = null): array {
    return ['Nome' => $u['nome'] ?? '', 'E-mail' => $u['email'] ?? '', 'CPF' => $u['cpf'] ?? '', 'Telefone' => $u['telefone'] ?? '', 'WhatsApp' => $u['whatsapp'] ?? '',
        'Nível de acesso' => $nivel ?? '', 'Cadastro ativo' => sim_nao($u['ativo'] ?? 1)];
}

function retrato_unidade(array $u): array {
    return ['Bloco / Torre' => $u['bloco'] ?? '', 'Número' => $u['numero'] ?? '', 'Taxa de condomínio' => dinheiro($u['valor_condominio'] ?? 0), 'Unidade ativa' => sim_nao($u['ativo'] ?? 1)];
}

/**
 * Pergunta ao usuário se quer registrar a alteração no histórico (mostra antes/depois).
 * Reenvia o mesmo formulário com confirmar=sim|nao. Encerra a requisição.
 */
function pagina_confirmar_alteracao(array $dif, string $titulo, string $voltar): never {
    pagina_inicio($titulo, ['voltar' => $voltar]);
    echo '<div class="card"><h3>Você alterou ', count($dif), ' informaç', count($dif) === 1 ? 'ão' : 'ões', '</h3><p class="muted">Confira abaixo e escolha se quer guardar no histórico.</p>';
    foreach ($dif as $campo => [$velho, $novo]) {
        echo '<div class="dif"><span class="dif-campo">', e($campo), '</span><span class="dif-antes">', e($velho !== '' ? $velho : '—'), '</span><span class="dif-seta">→</span><span class="dif-depois">', e($novo !== '' ? $novo : '—'), '</span></div>';
    }
    echo '</div><form method="post" class="botoes">', csrf_campo();
    foreach ($_POST as $k => $v) if (!in_array($k, ['csrf', 'confirmar'], true) && !is_array($v)) echo '<input type="hidden" name="', e($k), '" value="', e($v), '">';
    echo '<button class="btn btn-grande" name="confirmar" value="sim">Salvar e registrar no histórico</button>',
         '<button class="btn btn-sec" name="confirmar" value="nao">Salvar sem registrar</button>',
         '<a class="btn btn-sec" href="', e($_SERVER['REQUEST_URI']), '">Cancelar</a></form>';
    pagina_fim();
    exit;
}

function data_valida(string $s): ?string {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) return null;
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $s : null;
}

/** Replica nome, CPF, telefone e e-mail do cadastro nos vínculos das unidades (histórico de proprietários/locatários). */
function atualizar_dados_vinculos(int $uid): void {
    q('UPDATE unidade_vinculos v JOIN usuarios u ON u.id = v.usuario_id SET v.nome = u.nome, v.cpf = u.cpf, v.telefone = COALESCE(u.telefone, u.whatsapp), v.email = u.email WHERE u.id = ?', [$uid]);
}
