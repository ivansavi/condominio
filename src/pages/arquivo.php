<?php
$u = usuario();
$f = linha('SELECT * FROM arquivos WHERE id = ?', [(int)get('id')]);
$ok = false;
if ($f) {
    $id = (int)$f['id'];
    $acessiveis = array_map(fn($c) => (int)$c['id'], condominios_acessiveis());
    $minhas = ids_unidades_do_usuario();
    $in = fn(array $a) => $a ? implode(',', array_map('intval', $a)) : '0';
    // logo do condomínio: qualquer usuário daquele condomínio; foto: o próprio usuário ou quem administra
    if (valor('SELECT 1 FROM condominios WHERE logo_arquivo_id = ?', [$id])) $ok = true;
    elseif (valor('SELECT 1 FROM usuarios WHERE foto_arquivo_id = ? AND id = ?', [$id, $u['id']])) $ok = true;
    elseif (eh_admin() && valor('SELECT 1 FROM usuarios WHERE foto_arquivo_id = ?', [$id])) $ok = true;
    else {
        // boleto: dono da unidade ou quem gerencia cobranças daquele condomínio
        $cob = linha('SELECT c.unidade_id, un.condominio_id FROM cobrancas c JOIN unidades un ON un.id = c.unidade_id WHERE c.arquivo_id = ?', [$id]);
        if ($cob && (in_array((int)$cob['unidade_id'], $minhas, true) || ((int)$cob['condominio_id'] === cid() && (pode('cobrancas') || pode('financeiro_ver'))))) $ok = true;
        // documento da prestação de contas: publicado para quem é do condomínio; rascunho só para a gestão
        $anx = linha('SELECT a.condominio_id, p.publicada FROM prestacao_anexos a JOIN prestacoes p ON p.condominio_id = a.condominio_id AND p.competencia = a.competencia WHERE a.arquivo_id = ?', [$id]);
        if ($anx && in_array((int)$anx['condominio_id'], $acessiveis, true) && ($anx['publicada'] || ((int)$anx['condominio_id'] === cid() && pode('contas')))) $ok = true;
    }
}
$path = $f ? RAIZ . '/storage/uploads/' . basename($f['caminho']) : '';
if (!$ok || !is_file($path)) { http_response_code(404); exit('Arquivo não encontrado.'); }
header('Content-Type: ' . $f['mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . rawurlencode($f['nome_original']) . '"');
header('Cache-Control: private, max-age=3600');
readfile($path);
