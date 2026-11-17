<?php
// Partial: Floating chatbot widget. Di-include dari layouts/footer.php agar tampil di semua halaman.
// Base URL dihitung dari SCRIPT_NAME agar benar baik saat docroot=public
// maupun saat dijalankan di subfolder (mis. /projekfasilitas/public).
$__chatBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
$__chatEndpoint = $__chatBase . '/api/chat';
$__chatCss = $__chatBase . '/css/chatbot.css';
$__chatJs = $__chatBase . '/js/chatbot.js';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($__chatCss) ?>">
<button id="chatFab" class="chat-fab" aria-label="Buka chatbot" aria-expanded="false" title="Tanya LaporFasilitas AI">
  <i class="fa-solid fa-robot chat-fab-open"></i>
  <i class="fa-solid fa-xmark chat-fab-close"></i>
</button>
<div id="chatWindow" class="chat-window" role="dialog" aria-label="LaporFasilitas AI" data-endpoint="<?= htmlspecialchars($__chatEndpoint) ?>">
  <div class="chat-header">
    <div class="chat-avatar"><i class="fa-solid fa-robot"></i></div>
    <div>
      <h3>LaporFasilitas AI</h3>
      <p>Bagaimana saya dapat membantu?</p>
    </div>
  </div>
  <div id="chatMessages" class="chat-messages" aria-live="polite">
    <div class="chat-empty">
      <p><strong>Mulai percakapan</strong><br>Contoh pertanyaan cepat:</p>
      <div class="chat-quick">
        <button type="button" data-quick="Bagaimana cara membuat laporan?">Bagaimana cara membuat laporan?</button>
        <button type="button" data-quick="Apa saja fasilitas yang bisa dilaporkan?">Apa saja fasilitas yang bisa dilaporkan?</button>
        <button type="button" data-quick="Bagaimana cara cek status laporan?">Bagaimana cara cek status laporan?</button>
        <button type="button" data-quick="Apa arti status laporan saya?">Apa arti status laporan saya?</button>
      </div>
    </div>
  </div>
  <form id="chatForm" class="chat-input-bar" autocomplete="off">
    <textarea id="chatInput" rows="1" maxlength="1000" placeholder="Ajukan pertanyaan..." aria-label="Ajukan pertanyaan"></textarea>
    <button id="chatSend" class="chat-send" type="submit"><i class="fa-solid fa-paper-plane"></i> Kirim</button>
  </form>
</div>
<script src="<?= htmlspecialchars($__chatJs) ?>" defer></script>
