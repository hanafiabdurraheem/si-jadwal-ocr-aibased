<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek login
if (empty($_SESSION['username'])) {
    header("Location: ../login/index.php");
    exit();
}

$username = $_SESSION['username'];

require_once __DIR__ . '/../backend/schedule_store.php';
$scheduleIndex = load_schedule_index($username);
$scheduleItems = $scheduleIndex['items'] ?? [];
$activeScheduleId = $scheduleIndex['active_id'] ?? null;

usort($scheduleItems, function ($a, $b) {
    return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
});
?>

<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta charset="utf-8" />
  <link rel="stylesheet" href="global.css" />
  <link rel="stylesheet" href="styleguide.css" />
  <link rel="stylesheet" href="style.css?v=<?= time() ?>" />
</head>

<body>
  <div class="tambah-kurang-jadwal">
    <div class="div">

      <div class="text-wrapper">Jadwal Saya</div>

      <!-- Upload Form -->
      <form id="uploadForm" action="../backend/upload.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="fileToUpload[]" id="fileToUpload"
        accept=".jpg,.jpeg,.png" multiple required style="display:none;" />
      </form>

      <div class="text-wrapper-4">Daftar Jadwal</div>
      <img class="line" src="/../img/line.png" />

      <div class="schedule-list">
        <?php if (!empty($scheduleItems)): ?>
            <?php foreach ($scheduleItems as $item): ?>
                <?php
                    $itemId = $item['id'] ?? '';
                    $isActive = ($itemId === $activeScheduleId);
                    $createdAt = $item['created_at'] ?? '';
                    $createdLabel = $createdAt ? date('d M Y H:i', strtotime($createdAt)) : '';
                    $displayName = $item['name'] ?? 'Jadwal';
                ?>
                <div class="schedule-card <?php echo $isActive ? 'active' : ''; ?>" data-id="<?php echo htmlspecialchars($itemId); ?>">
                    <div class="schedule-info">
                        <div class="schedule-name"><?php echo htmlspecialchars($displayName); ?></div>
                        <div class="schedule-meta"><?php echo htmlspecialchars($createdLabel); ?></div>
                    </div>
                    <div class="schedule-actions">
                        <label class="switch">
                            <input type="checkbox" class="schedule-toggle" data-id="<?php echo htmlspecialchars($itemId); ?>" <?php echo $isActive ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <button class="rename-link" type="button" data-id="<?php echo htmlspecialchars($itemId); ?>">Rename</button>
                        <a class="edit-link" href="/si-jadwal/jadwal/confirm-edit-jadwal/index.php?schedule_id=<?php echo urlencode($itemId); ?>">
                            Edit
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty-state">Belum ada jadwal. Upload foto untuk mulai.</p>
        <?php endif; ?>
      </div>

      <?php include '../nav.php'; ?>

    </div>
  </div>

  <button class="fab-upload" type="button" id="fabUpload" aria-label="Upload banyak foto">+</button>

  <div class="upload-overlay" id="uploadOverlay" aria-hidden="true">
    <div class="upload-card">
      <div class="spinner"></div>
      <div class="upload-text">Memproses jadwal, mohon tunggu...</div>
    </div>
  </div>

  <script>
    const uploadInput = document.getElementById("fileToUpload");
    const uploadForm = document.getElementById("uploadForm");
    const fabUpload = document.getElementById("fabUpload");
    const uploadOverlay = document.getElementById("uploadOverlay");

    function showUploadOverlay() {
        uploadOverlay.classList.add("show");
        uploadOverlay.setAttribute("aria-hidden", "false");
    }

    function hideUploadOverlay() {
        uploadOverlay.classList.remove("show");
        uploadOverlay.setAttribute("aria-hidden", "true");
    }

    fabUpload.addEventListener("click", () => uploadInput.click());

    uploadInput.addEventListener("change", async () => {
        if (!uploadInput.files || uploadInput.files.length === 0) return;

        if (!window.fetch) {
            uploadForm.submit();
            return;
        }

        showUploadOverlay();
        const formData = new FormData();
        formData.append("total_files", uploadInput.files.length);
        Array.from(uploadInput.files).forEach(file => {
            formData.append("fileToUpload[]", file);
        });

        try {
            const response = await fetch("../backend/upload.php?ajax=1", {
                method: "POST",
                headers: { "X-Requested-With": "XMLHttpRequest" },
                body: formData
            });
            const data = await response.json();
            if (!data.ok) {
                throw new Error(data.message || "Gagal upload");
            }
            window.location.href = data.redirect;
        } catch (err) {
            hideUploadOverlay();
            alert("Gagal memproses upload. Silakan coba lagi.");
        }
    });

    const toggles = document.querySelectorAll(".schedule-toggle");
    const renameButtons = document.querySelectorAll(".rename-link");

    toggles.forEach(toggle => {
        toggle.addEventListener("change", async (event) => {
            const target = event.target;
            if (!target.checked) {
                target.checked = true;
                return;
            }

            const scheduleId = target.dataset.id;
            try {
                const response = await fetch("../backend/set_active_schedule.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ scheduleId })
                });
                const data = await response.json();
                if (!data.ok) {
                    throw new Error(data.message || "Gagal mengaktifkan jadwal");
                }

                toggles.forEach(item => {
                    if (item.dataset.id !== scheduleId) {
                        item.checked = false;
                        item.closest(".schedule-card")?.classList.remove("active");
                    }
                });
                target.closest(".schedule-card")?.classList.add("active");
            } catch (err) {
                target.checked = false;
                alert("Gagal mengaktifkan jadwal. Silakan coba lagi.");
            }
        });
    });

    renameButtons.forEach(button => {
        button.addEventListener("click", async () => {
            const scheduleId = button.dataset.id;
            const card = button.closest(".schedule-card");
            const nameEl = card?.querySelector(".schedule-name");
            const currentName = nameEl?.textContent?.trim() || "";
            const newName = prompt("Nama jadwal baru:", currentName);
            if (!newName || newName.trim() === currentName) {
                return;
            }

            try {
                const response = await fetch("../backend/rename_schedule.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ scheduleId, name: newName.trim() })
                });
                const data = await response.json();
                if (!data.ok) {
                    throw new Error(data.message || "Gagal rename");
                }
                if (nameEl) {
                    nameEl.textContent = data.name;
                }
            } catch (err) {
                alert("Gagal mengganti nama jadwal.");
            }
        });
    });
  </script>
</body>
</html>
