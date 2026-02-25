<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/schedule_store.php';
require_once __DIR__ . '/db.php';

$username = $_SESSION['username'] ?? '';


date_default_timezone_set('Asia/Jakarta');
$hariIni = date('l');

$mapHari = [
    'Monday'    => 'Senin',
    'Tuesday'   => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday'  => 'Kamis',
    'Friday'    => 'Jumat',
    'Saturday'  => 'Sabtu',
    'Sunday'    => 'Minggu'
];

$hariIndonesia = $mapHari[$hariIni] ?? '';

$active = resolve_active_schedule_item($username);

if (!$active) {
    echo '
    <form id="uploadForm" action="../backend/upload.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="fileToUpload[]" id="fileToUpload" multiple accept=".jpg,.jpeg,.png" required style="display:none;" />
        
        <a href="#" class="tambah-button" style="color:white;" 
           onclick="document.getElementById(\'fileToUpload\').click(); return false;">
           Tekan untuk upload KRS!
        </a>
    </form>

    <div id="uploadOverlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#1b1b1f; color:white; padding:16px 20px; border-radius:12px; text-align:center;">
            <div style="width:32px; height:32px; border:3px solid rgba(255,255,255,0.2); border-top-color:#6552fe; border-radius:50%; animation:spin 0.8s linear infinite; margin:0 auto;"></div>
            <div style="margin-top:10px; font-size:12px; color:#c7c7d1;">Memproses jadwal...</div>
        </div>
    </div>

    <style>
      @keyframes spin { to { transform: rotate(360deg); } }
    </style>

    <script>
        const uploadInput = document.getElementById("fileToUpload");
        const overlay = document.getElementById("uploadOverlay");

        uploadInput.addEventListener("change", async function () {
            if (!uploadInput.files || uploadInput.files.length === 0) return;

            if (!window.fetch) {
                document.getElementById("uploadForm").submit();
                return;
            }

            overlay.style.display = "flex";
            const formData = new FormData();
            formData.append("total_files", uploadInput.files.length);
            Array.from(uploadInput.files).forEach(file => formData.append("fileToUpload[]", file));

            try {
                const response = await fetch("../backend/upload.php?ajax=1", {
                    method: "POST",
                    headers: { "X-Requested-With": "XMLHttpRequest" },
                    body: formData
                });
                const data = await response.json();
                if (!data.ok) throw new Error(data.message || "Gagal upload");
                window.location.href = data.redirect;
            } catch (err) {
                overlay.style.display = "none";
                alert("Gagal memproses upload. Silakan coba lagi.");
            }
        });
    </script>
    ';
    exit;
}

$rows = get_schedule_rows($username, $active['id']);

$filtered = array_filter($rows, function ($row) use ($hariIndonesia) {
    return isset($row['hari']) && $row['hari'] === $hariIndonesia;
});

usort($filtered, function ($a, $b) {
    $timeA = strtotime(str_replace('.', ':', $a['jam_mulai'] ?? ''));
    $timeB = strtotime(str_replace('.', ':', $b['jam_mulai'] ?? ''));
    return $timeA <=> $timeB;
});

$now = strtotime(date('H:i'));
$jadwalTerdekat = null;
foreach ($filtered as $row) {
    $jadwalTime = strtotime(str_replace('.', ':', $row['jam_mulai'] ?? ''));
    if ($jadwalTime !== false && $jadwalTime >= $now) {
        $jadwalTerdekat = $row;
        break;
    }
}

echo "<h2></h2>";
if (!$jadwalTerdekat) {
    echo "<p style='color:white;'>Tidak ada dalam waktu dekat</p>";
} else {
    echo "<div class='jadwal-container'>";
    echo "<div class='jadwal-value-only'>" . htmlspecialchars($jadwalTerdekat['nama_matakuliah'] ?? '-') . "</div>";
    echo "</div>";
}
?>
