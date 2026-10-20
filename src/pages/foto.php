<?php
// Recebe a foto de perfil já recortada pelo navegador (JSON). Ação "remover" apaga a foto.
header('Content-Type: application/json; charset=utf-8');
$u = usuario();
if (!eh_post()) { http_response_code(405); exit('{"ok":false}'); }
try {
    if (post('acao') === 'remover') {
        apagar_arquivo($u['foto_arquivo_id'] ? (int)$u['foto_arquivo_id'] : null);
        q('UPDATE usuarios SET foto_arquivo_id = NULL WHERE id = ?', [$u['id']]);
        flash('Foto removida.');
    } else {
        $novo = salvar_imagem('foto', 600, false);
        if (!$novo) throw new RuntimeException('Nenhuma imagem recebida.');
        $antiga = $u['foto_arquivo_id'] ? (int)$u['foto_arquivo_id'] : null;
        q('UPDATE usuarios SET foto_arquivo_id = ? WHERE id = ?', [$novo, $u['id']]);
        apagar_arquivo($antiga);
        flash('Foto atualizada!');
    }
    echo json_encode(['ok' => true]);
} catch (RuntimeException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()]);
}
