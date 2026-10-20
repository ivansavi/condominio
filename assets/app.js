(function () {
  document.addEventListener('click', function (e) {
    var t = e.target.closest('button,a'); if (!t) return;
    if (t.matches('[data-ver-senha]')) {
      var i = t.parentNode.querySelector('input');
      i.type = i.type === 'password' ? 'text' : 'password';
      t.textContent = i.type === 'password' ? 'Mostrar' : 'Ocultar';
    }
    if (t.matches('[data-imprimir]')) window.print();
    var texto = t.hasAttribute('data-copiar') ? (document.querySelector(t.getAttribute('data-copiar')) || {}).textContent
              : t.getAttribute('data-copiar-texto');
    if (texto) {
      var ok = function () { var o = t.textContent; t.textContent = '✓ Copiado!'; setTimeout(function () { t.textContent = o; }, 1800); };
      if (navigator.clipboard) navigator.clipboard.writeText(texto.trim()).then(ok, function () { prompt('Copie o texto:', texto); });
      else { prompt('Copie o texto:', texto); }
    }
  });
  document.addEventListener('submit', function (e) {
    var m = e.target.getAttribute('data-confirmar');
    if (m && !confirm(m)) e.preventDefault();
  });
  // Esconde "acesso gestão" quando o perfil não é síndico
  var sel = document.querySelector('[data-perfil]');
  if (sel) {
    var chk = document.querySelector('input[name=acesso_gestao]').closest('label');
    var atualiza = function () { chk.style.display = sel.value === 'sindico' ? '' : 'none'; };
    sel.addEventListener('change', atualiza); atualiza();
  }
  // Rótulos das datas conforme a situação (proprietário compra/vende; inquilino começa/termina a locação)
  var tipo = document.querySelector('[data-tipo-morador]');
  if (tipo) {
    var ini = document.querySelector('[data-rot-inicio]'), fim = document.querySelector('[data-rot-fim]');
    var rot = function () { var loc = tipo.value === 'inquilino'; ini.textContent = loc ? 'Início da locação' : 'Data da compra'; fim.textContent = loc ? 'Fim da locação' : 'Data da venda'; };
    tipo.addEventListener('change', rot); rot();
  }
})();
