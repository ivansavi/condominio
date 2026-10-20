<?php
header('Content-Type: application/json; charset=utf-8');
if (eh_post() && in_array(post('tema'), ['auto', 'claro', 'escuro'], true)) {
    q('UPDATE usuarios SET tema = ? WHERE id = ?', [post('tema'), usuario()['id']]);
    echo '{"ok":true}';
} else { http_response_code(422); echo '{"ok":false}'; }
