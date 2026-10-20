<?php
$u = usuario();
$f = linha('SELECT * FROM arquivos WHERE id = ?', [(int)get('id')]);
$ok = false;
if ($f) {
    if (eh_admin()) $ok = true;
    else {
        $ok = (bool)valor('SELECT 1 FROM cobrancas WHERE arquivo_id = ? AND unidade_id = ?', [$f['id'], (int)$u['unidade_id']])
           || (bool)valor('SELECT 1 FROM prestacao_anexos a JOIN prestacoes p ON p.competencia = a.competencia WHERE a.arquivo_id = ? AND p.publicada = 1', [$f['id']]);
    }
}
$path = $f ? RAIZ . '/storage/uploads/' . basename($f['caminho']) : '';
if (!$ok || !is_file($path)) { http_response_code(404); exit('Arquivo não encontrado.'); }
header('Content-Type: ' . $f['mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . rawurlencode($f['nome_original']) . '"');
header('Cache-Control: private, max-age=0');
readfile($path);
