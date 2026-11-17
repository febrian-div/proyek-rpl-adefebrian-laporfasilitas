/**
 * LaporFasilitas - Chatbot Widget (vanilla JS, lazy & ringan).
 * POST /api/chat — API key tidak pernah ke frontend.
 */
(function () {
  var fab = document.getElementById('chatFab');
  var win = document.getElementById('chatWindow');
  var msgs = document.getElementById('chatMessages');
  var form = document.getElementById('chatForm');
  var input = document.getElementById('chatInput');
  var sendBtn = document.getElementById('chatSend');
  if (!fab || !win || !msgs || !form || !input) return;

  var history = []; // {role:'user'|'bot', content} — maks 6 dikirim ke backend
  var greeted = false;

  function now() {
    return new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
  }

  function scrollBottom() {
    msgs.scrollTop = msgs.scrollHeight;
  }

  function bubble(text, who, isError) {
    var empty = msgs.querySelector('.chat-empty');
    if (empty) empty.remove();
    var div = document.createElement('div');
    div.className = 'chat-bubble ' + who + (isError ? ' error' : '');
    var p = document.createElement('div');
    p.textContent = text;
    var t = document.createElement('small');
    t.textContent = now();
    div.appendChild(p);
    div.appendChild(t);
    msgs.appendChild(div);
    scrollBottom();
    return div;
  }

  function typingOn() {
    var div = document.createElement('div');
    div.className = 'chat-bubble bot';
    div.id = 'chatTyping';
    div.innerHTML = '<span class="chat-typing"><span></span><span></span><span></span></span>';
    msgs.appendChild(div);
    scrollBottom();
  }

  function typingOff() {
    var el = document.getElementById('chatTyping');
    if (el) el.remove();
  }

  function toggle(open) {
    var willOpen = typeof open === 'boolean' ? open : !win.classList.contains('open');
    win.classList.toggle('open', willOpen);
    fab.classList.toggle('open', willOpen);
    fab.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    if (willOpen && !greeted) {
      greeted = true;
      bubble('Halo! Saya asisten LaporFasilitas. Tanya cara membuat laporan, arti status, atau status laporan Anda.', 'bot');
    }
    if (willOpen) setTimeout(function () { input.focus(); }, 100);
  }

  fab.addEventListener('click', function () { toggle(); });

  msgs.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-quick]');
    if (!btn) return;
    input.value = btn.getAttribute('data-quick');
    form.requestSubmit();
  });

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit();
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = input.value.trim();
    if (!text || sendBtn.disabled) return;
    bubble(text, 'user');
    history.push({ role: 'user', content: text });
    input.value = '';
    input.style.height = 'auto';
    sendBtn.disabled = true;
    typingOn();

    fetch(win.getAttribute('data-endpoint') || '/api/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ message: text, history: history.slice(-6) })
    })
      .then(function (res) {
        return res.json().then(function (data) {
          if (!res.ok) throw new Error((data && data.message) || ('Gagal memproses (HTTP ' + res.status + ')'));
          return data;
        });
      })
      .then(function (data) {
        typingOff();
        var reply = (data && data.message) || 'Maaf, saya tidak memiliki informasi tersebut. Silakan hubungi admin atau gunakan menu yang tersedia di website.';
        bubble(reply, 'bot');
        history.push({ role: 'bot', content: reply });
        history = history.slice(-12);
      })
      .catch(function (err) {
        typingOff();
        bubble('Maaf, terjadi gangguan koneksi. Silakan coba lagi. (' + err.message + ')', 'bot', true);
      })
      .finally(function () {
        sendBtn.disabled = false;
        input.focus();
      });
  });
})();
