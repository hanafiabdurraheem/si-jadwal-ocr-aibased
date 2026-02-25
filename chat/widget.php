<?php
// Widget chatbot. Pastikan halaman yang menyertakan file ini sudah memulai session & mengecek login.
$ts = time();
?>
<link rel="stylesheet" href="/si-jadwal/chat/style.css?v=<?= $ts ?>">
<div class="chatbot-floating" id="chatbotToggle" aria-label="Buka chatbot">
  <span class="chatbot-icon" aria-hidden="true"></span>
</div>

<div class="chatbot-modal" id="chatbotModal" aria-hidden="true">
  <div class="chatbot-header">
    <div>
      <div class="chatbot-title">Asisten Jadwal</div>
      <div class="chatbot-subtitle">Bantu to-do, tugas, & penyesuaian jadwal</div>
    </div>
    <button class="chatbot-close" type="button" id="chatbotClose">×</button>
  </div>
  <div class="chatbot-quick">
    <button class="chatbot-chip" data-prompt="Buatkan to-do list hari ini.">To-do hari ini</button>
    <button class="chatbot-chip" data-prompt="Tugas terdekat yang belum selesai.">Tugas terdekat</button>
    <button class="chatbot-chip" data-prompt="Tambah tugas baru.">Tambah tugas</button>
    <button class="chatbot-chip" data-prompt="Cek dan sesuaikan jadwal yang bentrok.">Cek jadwal</button>
  </div>
  <div class="chatbot-messages" id="chatbotMessages"></div>
  <div class="chatbot-input">
    <textarea id="chatbotInput" rows="2" placeholder="Tulis pesan..."></textarea>
    <button type="button" id="chatbotSend">Kirim</button>
  </div>
</div>

<script src="/si-jadwal/chat/script.js?v=<?= $ts ?>"></script>
