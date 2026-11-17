    </div>
</main>

<footer class="footer">
    <div class="container text-center">
        <p class="mb-1 font-semibold" style="color: var(--secondary);">
            <i class="fa-solid fa-city"></i> LaporFasilitas &copy; <?= date('Y') ?> - Sistem Pelaporan Fasilitas Publik Modern
        </p>
        <p class="text-xs text-muted">
            Membantu mewujudkan tata kelola kota yang responsif, transparan, dan terintegrasi dengan Artificial Intelligence.
        </p>
    </div>
</footer>

<!-- Leaflet JS & App Scripts -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="<?= base_url('/js/app.js') ?>"></script>
<script src="<?= base_url('/js/map.js') ?>"></script>

<?php
// Chatbot AI global (floating button + window). Data pribadi difilter backend sesuai role.
$__chatbotPartial = VIEWS_PATH . '/components/chatbot.php';
if (file_exists($__chatbotPartial)) require $__chatbotPartial;
?>
</body>
</html>
