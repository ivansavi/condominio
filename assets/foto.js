/* Foto de perfil: escolher, tirar foto, enquadrar (arrastar, zoom, girar) e enviar recortada em 512x512. */
(function () {
  var modal = document.getElementById('foto-modal'); if (!modal) return;
  var S = 300, SAIDA = 512;
  var cv = document.getElementById('foto-canvas'), ctx = cv.getContext('2d');
  var video = document.getElementById('foto-video');
  var zoom = document.getElementById('foto-zoom'), ok = document.getElementById('foto-ok');
  var virar = document.getElementById('foto-virar'), dica = document.getElementById('foto-dica'), controles = document.getElementById('foto-controles');
  var img = null, rot = 0, escala = 1, base = 1, ox = 0, oy = 0, stream = null, facing = 'user', modo = 'recorte';
  var ptrs = {}, pinch = 0;

  function dims() { var q = rot % 2; return { w: q ? img.height : img.width, h: q ? img.width : img.height }; }
  function limitar() {
    var d = dims(), w = d.w * escala, h = d.h * escala;
    var mx = Math.max(0, (w - S) / 2), my = Math.max(0, (h - S) / 2);
    ox = Math.min(mx, Math.max(-mx, ox)); oy = Math.min(my, Math.max(-my, oy));
  }
  function desenhar(alvo, tam) {
    var c = alvo.getContext('2d'), f = tam / S;
    c.clearRect(0, 0, tam, tam); c.fillStyle = '#000'; c.fillRect(0, 0, tam, tam);
    c.save(); c.translate(tam / 2 + ox * f, tam / 2 + oy * f); c.rotate(rot * Math.PI / 2); c.scale(escala * f, escala * f);
    c.drawImage(img, -img.width / 2, -img.height / 2); c.restore();
  }
  function render() {
    if (!img) return;
    limitar(); desenhar(cv, S);
    ctx.save(); ctx.fillStyle = 'rgba(0,0,0,.55)'; ctx.beginPath(); ctx.rect(0, 0, S, S); ctx.arc(S / 2, S / 2, S / 2 - 6, 0, Math.PI * 2, true); ctx.fill('evenodd');
    ctx.strokeStyle = 'rgba(255,255,255,.9)'; ctx.lineWidth = 2; ctx.beginPath(); ctx.arc(S / 2, S / 2, S / 2 - 6, 0, Math.PI * 2); ctx.stroke(); ctx.restore();
  }
  function ajustarBase() { var d = dims(); base = Math.max(S / d.w, S / d.h); escala = base * parseFloat(zoom.value); }
  function reiniciar() { zoom.value = 1; ox = 0; oy = 0; ajustarBase(); render(); }
  function abrirModal() { modal.hidden = false; document.body.style.overflow = 'hidden'; }
  function fecharModal() {
    modal.hidden = true; document.body.style.overflow = ''; pararCamera(); img = null;
    document.getElementById('foto-arquivo').value = ''; document.getElementById('foto-captura').value = '';
  }
  function carregar(blobOuArquivo) {
    var url = URL.createObjectURL(blobOuArquivo), i = new Image();
    i.onload = function () { img = i; rot = 0; modo = 'recorte'; mostrarRecorte(); URL.revokeObjectURL(url); abrirModal(); reiniciar(); };
    i.onerror = function () { alert('Não foi possível abrir esta imagem.'); };
    i.src = url;
  }
  function mostrarRecorte() {
    video.hidden = true; cv.hidden = false; controles.hidden = false; virar.hidden = true;
    ok.textContent = 'Usar esta foto'; document.getElementById('foto-titulo').textContent = 'Enquadre sua foto';
    dica.textContent = 'Arraste para posicionar e use o zoom para enquadrar o rosto no círculo.';
  }

  /* Câmera ao vivo (se o aparelho/navegador permitir); senão abre a câmera pelo seletor de arquivo */
  function pararCamera() { if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; } video.srcObject = null; }
  function abrirCamera() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { document.getElementById('foto-captura').click(); return; }
    pararCamera();
    navigator.mediaDevices.getUserMedia({ video: { facingMode: facing, width: { ideal: 1280 }, height: { ideal: 1280 } }, audio: false }).then(function (s) {
      stream = s; video.srcObject = s; video.play(); modo = 'camera'; img = null;
      cv.hidden = true; video.hidden = false; controles.hidden = true; virar.hidden = false;
      ok.textContent = 'Tirar foto'; document.getElementById('foto-titulo').textContent = 'Tirar foto';
      dica.textContent = 'Posicione o rosto e toque em "Tirar foto". Depois você poderá enquadrar.';
      video.style.transform = facing === 'user' ? 'scaleX(-1)' : 'none'; abrirModal();
    }).catch(function () { document.getElementById('foto-captura').click(); });
  }
  function capturar() {
    var w = video.videoWidth, h = video.videoHeight; if (!w) return;
    var c = document.createElement('canvas'); c.width = w; c.height = h; var x = c.getContext('2d');
    if (facing === 'user') { x.translate(w, 0); x.scale(-1, 1); }
    x.drawImage(video, 0, 0, w, h);
    c.toBlob(function (b) { pararCamera(); carregar(b); }, 'image/jpeg', 0.92);
  }

  function enviar(acao, blob) {
    var f = new FormData(); f.append('csrf', document.querySelector('meta[name=csrf]').content);
    if (acao) f.append('acao', acao); if (blob) f.append('foto', blob, 'foto.jpg');
    ok.disabled = true;
    fetch('index.php?p=foto', { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (j.ok) location.reload(); else { ok.disabled = false; alert(j.erro || 'Não foi possível salvar a foto.'); }
    }).catch(function () { ok.disabled = false; alert('Falha de conexão. Tente novamente.'); });
  }

  document.querySelector('[data-foto-escolher]').addEventListener('click', function () { document.getElementById('foto-arquivo').click(); });
  document.querySelector('[data-foto-camera]').addEventListener('click', abrirCamera);
  var rem = document.querySelector('[data-foto-remover]');
  if (rem) rem.addEventListener('click', function () { if (confirm('Remover sua foto?')) enviar('remover'); });
  ['foto-arquivo', 'foto-captura'].forEach(function (id) {
    document.getElementById(id).addEventListener('change', function (e) { if (e.target.files[0]) carregar(e.target.files[0]); });
  });
  document.getElementById('foto-cancelar').addEventListener('click', fecharModal);
  virar.addEventListener('click', function () { facing = facing === 'user' ? 'environment' : 'user'; abrirCamera(); });
  document.querySelector('[data-foto-girar]').addEventListener('click', function () { rot = (rot + 1) % 4; ajustarBase(); render(); });
  document.querySelector('[data-foto-centralizar]').addEventListener('click', reiniciar);
  zoom.addEventListener('input', function () { ajustarBase(); render(); });
  ok.addEventListener('click', function () {
    if (modo === 'camera') return capturar();
    if (!img) return;
    var out = document.createElement('canvas'); out.width = out.height = SAIDA; desenhar(out, SAIDA);
    out.toBlob(function (b) { enviar(null, b); }, 'image/jpeg', 0.9);
  });

  /* Arrastar (um dedo) e pinça (dois dedos) */
  cv.addEventListener('pointerdown', function (e) { cv.setPointerCapture(e.pointerId); ptrs[e.pointerId] = { x: e.clientX, y: e.clientY }; pinch = 0; });
  cv.addEventListener('pointermove', function (e) {
    var p = ptrs[e.pointerId]; if (!p || !img) return;
    var ids = Object.keys(ptrs), k = S / cv.getBoundingClientRect().width;
    if (ids.length === 2) {
      ptrs[e.pointerId] = { x: e.clientX, y: e.clientY };
      var a = ptrs[ids[0]], b = ptrs[ids[1]], d = Math.hypot(a.x - b.x, a.y - b.y);
      if (pinch) { zoom.value = Math.min(4, Math.max(1, parseFloat(zoom.value) * d / pinch)); ajustarBase(); render(); }
      pinch = d; return;
    }
    ox += (e.clientX - p.x) * k; oy += (e.clientY - p.y) * k; ptrs[e.pointerId] = { x: e.clientX, y: e.clientY }; render();
  });
  ['pointerup', 'pointercancel'].forEach(function (t) { cv.addEventListener(t, function (e) { delete ptrs[e.pointerId]; pinch = 0; }); });
  cv.addEventListener('wheel', function (e) { e.preventDefault(); zoom.value = Math.min(4, Math.max(1, parseFloat(zoom.value) * (e.deltaY < 0 ? 1.08 : 0.92))); ajustarBase(); render(); }, { passive: false });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) fecharModal(); });
})();
