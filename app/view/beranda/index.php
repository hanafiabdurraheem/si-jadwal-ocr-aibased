<!DOCTYPE html>
<html>
  
  <head>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta charset="utf-8" />
  <?php include PROJECT_ROOT . '/app/view/theme.php'; ?>
  <link rel="stylesheet" href="app/view/beranda/global.css?v=<?= time() ?>" />
    <link rel="stylesheet" href="app/view/beranda/styleguide.css?v=<?= time() ?>" />
    <link rel="stylesheet" href="app/view/beranda/style.css?v=<?= time() ?>" />
    <link rel="stylesheet" href="app/view/beranda/detailed/global.css?v=<?= time() ?>" />
    <link rel="stylesheet" href="app/view/beranda/detailed/styleguide.css?v=<?= time() ?>" />
    <link rel="stylesheet" href="app/view/beranda/detailed/style.css?v=<?= time() ?>">
  </head>


  <body data-chat-context="beranda">
    <div class="jadwal-kuliah">
      <div class="div">
      <span class="text-wrapper">Hey, <?php echo htmlspecialchars($username); ?></span>
      <span class="waktu">
        <?php
          setlocale(LC_TIME, 'id_ID');
          date_default_timezone_set('Asia/Jakarta');
          echo strftime('%A, %d %B');
        ?>
      </span>
      <?php if ($noticeUpdated): ?>
        <div id="toastNotice" class="toast-notice">Perubahan sudah dilakukan</div>
      <?php endif; ?>
      
        
          <div class="overlap" style="cursor: pointer;" onclick="openModal()">


              <div class="kotak-upcoming"></div>
              <div class="text-wrapper-2">Upcoming schedule</div>
              <div class="text-wrapper-3">
  <?php
    require_once PROJECT_ROOT . '/app/model/beranda/upcoming.php';
    echo beranda_render_upcoming($username);
  ?>
</div>


            </div>
          </a>


        <div class="tabel-di-tengah-rata-kiri">
  <?php
    require_once PROJECT_ROOT . '/app/model/beranda/jadwal-hari-ini.php';
    echo beranda_render_jadwal_hari_ini($username);
  ?>
</div>



        <img class="user"/>

      
    
      <!-- Modal HTML -->
      <div id="popupOverlay" 
        style="display:none; 
        position:absolute; 
        top:0; 
        left:0; 
        width:60px;         
        height:100%;        
        background:transparentns; 
        z-index:999;">
      </div>

      <div id="popupModal" 
        style="display:none; 
        position:absolute; 
        top:339px; 
        left:50%; 
        transform:translate(-50%, -50%); 
        background:transparent; 
        padding:0px; 
        width:90%;
        max-width:383px; 
        height:auto;
        max-height:none;
        border-radius: 12px;
        overflow:visible; 
        z-index:1000;">
        <div id="modalContent">Memuat...
        </div>
        
      </div>



    <script>
  document.addEventListener("DOMContentLoaded", function () {
    const toast = document.getElementById('toastNotice');
    if (toast) {
      setTimeout(() => {
        toast.classList.add('show');
      }, 100);
      setTimeout(() => {
        toast.classList.remove('show');
      }, 2500);
      if (window.history && window.history.replaceState) {
        const url = new URL(window.location);
        url.searchParams.delete('notice');
        window.history.replaceState({}, document.title, url.pathname + url.search);
      }
    }

    window.openModal = function () {
      document.getElementById('popupOverlay').style.display = 'block';
      document.getElementById('popupModal').style.display = 'block';

      fetch('index.php?route=beranda-detailed')
        .then(response => response.text())
        .then(html => {
          document.getElementById('modalContent').innerHTML = html;
        })
        .catch(err => {
          document.getElementById('modalContent').innerHTML = 'Gagal memuat konten.';
          console.error(err);
        });
    };

    window.closeModal = function () {
      document.getElementById('popupOverlay').style.display = 'none';
      document.getElementById('popupModal').style.display = 'none';
    };
  });
</script>


      </div>
      <?php include PROJECT_ROOT . '/app/view/nav.php'; ?>
    </div>
    <?php include PROJECT_ROOT . '/app/view/chat/widget.php'; ?>
  </body>
</html>
