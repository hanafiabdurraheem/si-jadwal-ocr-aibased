<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['username'])) {
    header("Location: ../../login/index.php");
    exit();
}

require_once __DIR__ . '/../../backend/schedule_store.php';
require_once __DIR__ . '/../../backend/db.php';

$username = $_SESSION['username'];
$daysOrder = ["Senin","Selasa","Rabu","Kamis","Jumat","Sabtu","Minggu"];
$extraDay = "Tanpa Hari";

$requestedId = isset($_GET['schedule_id']) ? trim($_GET['schedule_id']) : null;
if ($requestedId) {
    set_active_schedule_id($username, $requestedId);
}

$activeItem = resolve_active_schedule_item($username);
if (!$activeItem) {
    echo "File jadwal tidak ditemukan.";
    exit();
}

$header = ["No","Kode","Nama Matakuliah","SKS","Kelas/Rombel","Pengampu","Jenis","Ruang","Hari","Jam Mulai","Jam Selesai"];
$rowsAssoc = [];
$jadwal = [];
foreach ($daysOrder as $day) $jadwal[$day] = [];
$jadwal[$extraDay] = [];

$dbRows = get_schedule_rows($username, $activeItem['id']);
$idx = 0;
foreach ($dbRows as $r) {
    $rowAssoc = [
        "No" => $r['no_col'] ?? '',
        "Kode" => $r['kode'] ?? '',
        "Nama Matakuliah" => $r['nama_matakuliah'] ?? '',
        "SKS" => $r['sks'] ?? '',
        "Kelas/Rombel" => $r['kelas'] ?? '',
        "Pengampu" => $r['pengampu'] ?? '',
        "Jenis" => $r['jenis'] ?? '',
        "Ruang" => $r['ruang'] ?? '',
        "Hari" => $r['hari'] ?? '',
        "Jam Mulai" => $r['jam_mulai'] ?? '',
        "Jam Selesai" => $r['jam_selesai'] ?? '',
        "_index" => $idx++
    ];
    $dayValue = trim($rowAssoc['Hari']);
    if (!in_array($dayValue, $daysOrder, true)) {
        $dayValue = $extraDay;
    }
    $rowsAssoc[] = $rowAssoc;
    $jadwal[$dayValue][] = $rowAssoc;
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Jadwal Interaktif</title>
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>
    <div class="page">
        <div class="page-header">
            <div>
                <h1>Edit Jadwal</h1>
                <p class="subtitle">Drag & drop untuk pindah hari. Tekan lama kartu untuk edit detail.</p>
                <div class="active-schedule">Aktif: <?= h($activeItem['name'] ?? 'Jadwal') ?></div>
            </div>
            <div class="header-actions">
                <button id="addCourseBtn" class="add-btn" type="button">Tambah Matakuliah</button>
                <button id="saveBtn" class="save-btn">Simpan Jadwal</button>
            </div>
        </div>

        <div id="status" class="status" aria-live="polite"></div>

        <div class="board" id="board">
            <?php foreach (array_merge($daysOrder, [$extraDay]) as $day): ?>
                <div class="day-column" data-day="<?= h($day) ?>">
                    <div class="day-title"><?= h($day) ?></div>
                    <div class="cards">
                        <?php foreach ($jadwal[$day] as $row): ?>
                            <div class="card" draggable="true" data-row-index="<?= h($row['_index']) ?>" data-day="<?= h($day) ?>">
                                <div class="conflict-badge">Konflik</div>
                                <div class="card-title"><?= h($row['Nama Matakuliah'] ?? 'Tanpa Nama') ?></div>
                                <div class="card-meta"><?= h($row['Jam Mulai'] ?? '') ?> - <?= h($row['Jam Selesai'] ?? '') ?></div>
                                <div class="card-meta"><?= h($row['Ruang'] ?? '') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div id="deleteZone" class="delete-zone" aria-hidden="true">
            <div class="delete-icon">X</div>
            <div class="delete-text">Seret ke sini untuk hapus</div>
        </div>
    </div>

    <div class="modal" id="editModal" aria-hidden="true">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Detail</h2>
                <button class="modal-close" type="button" id="closeModal">X</button>
            </div>
            <form id="editForm" class="modal-form">
                <div id="formFields"></div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" id="cancelEdit">Batal</button>
                    <button type="submit" class="btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const scheduleId = <?php echo json_encode($activeItem['id'] ?? ''); ?>;
        const scheduleHeader = <?php echo json_encode($header); ?>;
        const scheduleRows = <?php echo json_encode($rowsAssoc); ?>;
        const daysOrder = <?php echo json_encode($daysOrder); ?>;
        const extraDay = <?php echo json_encode($extraDay); ?>;

        const statusEl = document.getElementById("status");
        const saveBtn = document.getElementById("saveBtn");
        const addCourseBtn = document.getElementById("addCourseBtn");
        const deleteZone = document.getElementById("deleteZone");
        const columns = document.querySelectorAll(".day-column");
        const cards = document.querySelectorAll(".card");

        const rowsByIndex = new Map(scheduleRows.map(row => [row._index, row]));
        let isDirty = false;
        let draggingCard = null;
        let originColumn = null;
        let longPressTimer = null;
        let longPressTriggered = false;
        let touchStart = null;
        let currentTouchColumn = null;
        let dragGhost = null;
        let dragOffset = null;
        let isTouchDragging = false;
        let nextRowIndex = Math.max(-1, ...scheduleRows.map(row => row._index)) + 1;

        function setStatus(message, isError = false) {
            statusEl.textContent = message;
            statusEl.classList.toggle("error", isError);
        }

        function markDirty() {
            isDirty = true;
            saveBtn.disabled = false;
            setStatus("Perubahan belum disimpan.");
        }

        function showDeleteZone() {
            if (!deleteZone) return;
            deleteZone.classList.add("active");
            deleteZone.setAttribute("aria-hidden", "false");
        }

        function hideDeleteZone() {
            if (!deleteZone) return;
            deleteZone.classList.remove("active", "hover");
            deleteZone.setAttribute("aria-hidden", "true");
        }

        function setDeleteHover(isHover) {
            if (!deleteZone) return;
            deleteZone.classList.toggle("hover", isHover);
        }

        function isPointInDeleteZone(x, y) {
            if (!deleteZone) return false;
            const rect = deleteZone.getBoundingClientRect();
            return x >= rect.left && x <= rect.right && y >= rect.top && y <= rect.bottom;
        }

        function removeDragGhost() {
            if (dragGhost) {
                dragGhost.remove();
                dragGhost = null;
            }
        }

        function deleteCard(card) {
            const rowIndex = Number(card.dataset.rowIndex);
            rowsByIndex.delete(rowIndex);
            const rowPos = scheduleRows.findIndex(row => row._index === rowIndex);
            if (rowPos >= 0) scheduleRows.splice(rowPos, 1);
            card.remove();
            markDirty();
            updateConflicts();
        }

        function updateCardContent(card, row) {
            const titleEl = card.querySelector(".card-title");
            const metaEls = card.querySelectorAll(".card-meta");
            if (titleEl) {
                titleEl.textContent = row["Nama Matakuliah"] || "Tanpa Nama";
            }
            if (metaEls[0]) {
                metaEls[0].textContent = `${row["Jam Mulai"] || ""} - ${row["Jam Selesai"] || ""}`.trim();
            }
            if (metaEls[1]) {
                metaEls[1].textContent = row["Ruang"] || "";
            }
        }

        function normalizeTimeValue(value) {
            if (!value) return "";
            const match = String(value).trim().match(/(\\d{1,2})[.:](\\d{2})/);
            if (!match) return "";
            const hours = match[1].padStart(2, "0");
            const minutes = match[2];
            return `${hours}:${minutes}`;
        }

        function parseTimeToMinutes(value) {
            const normalized = normalizeTimeValue(value);
            if (!normalized) return null;
            const [h, m] = normalized.split(":").map(Number);
            if (Number.isNaN(h) || Number.isNaN(m)) return null;
            return h * 60 + m;
        }

        function updateConflicts() {
            const cards = document.querySelectorAll(".card");
            cards.forEach(card => card.classList.remove("conflict"));

            const grouped = {};
            rowsByIndex.forEach(row => {
                let day = (row["Hari"] || "").trim();
                if (!daysOrder.includes(day)) {
                    day = extraDay;
                }
                if (!grouped[day]) grouped[day] = [];
                grouped[day].push(row);
            });

            Object.values(grouped).forEach(items => {
                for (let i = 0; i < items.length; i++) {
                    for (let j = i + 1; j < items.length; j++) {
                        const a = items[i];
                        const b = items[j];
                        const startA = parseTimeToMinutes(a["Jam Mulai"]);
                        const endA = parseTimeToMinutes(a["Jam Selesai"]);
                        const startB = parseTimeToMinutes(b["Jam Mulai"]);
                        const endB = parseTimeToMinutes(b["Jam Selesai"]);

                        if (startA === null || endA === null || startB === null || endB === null) {
                            continue;
                        }
                        if (startA >= endA || startB >= endB) {
                            continue;
                        }

                        const overlap = Math.max(startA, startB) < Math.min(endA, endB);
                        if (overlap) {
                            const cardA = document.querySelector(`.card[data-row-index="${a._index}"]`);
                            const cardB = document.querySelector(`.card[data-row-index="${b._index}"]`);
                            if (cardA) cardA.classList.add("conflict");
                            if (cardB) cardB.classList.add("conflict");
                        }
                    }
                }
            });
        }

        function moveCardToDay(card, newDay) {
            const rowIndex = Number(card.dataset.rowIndex);
            const row = rowsByIndex.get(rowIndex);
            if (!row) return;

            const safeDay = newDay === extraDay ? "" : newDay;
            row["Hari"] = safeDay;

            const targetColumn = document.querySelector(`.day-column[data-day="${newDay}"] .cards`);
            if (targetColumn) {
                targetColumn.appendChild(card);
                card.dataset.day = newDay;
                markDirty();
                updateConflicts();
            }
        }

        function clearColumnHighlight() {
            columns.forEach(col => col.classList.remove("drag-over"));
        }

        function buildCardElement(row, day) {
            const card = document.createElement("div");
            card.className = "card";
            card.draggable = true;
            card.dataset.rowIndex = row._index;
            card.dataset.day = day;

            const conflictBadge = document.createElement("div");
            conflictBadge.className = "conflict-badge";
            conflictBadge.textContent = "Konflik";

            const title = document.createElement("div");
            title.className = "card-title";
            title.textContent = row["Nama Matakuliah"] || "Tanpa Nama";

            const metaTime = document.createElement("div");
            metaTime.className = "card-meta";
            metaTime.textContent = `${row["Jam Mulai"] || ""} - ${row["Jam Selesai"] || ""}`.trim();

            const metaRoom = document.createElement("div");
            metaRoom.className = "card-meta";
            metaRoom.textContent = row["Ruang"] || "";

            card.append(conflictBadge, title, metaTime, metaRoom);
            return card;
        }

        function bindCardEvents(card) {
            card.addEventListener("dragstart", (e) => {
                draggingCard = card;
                originColumn = card.closest(".day-column");
                card.classList.add("dragging");
                showDeleteZone();
                e.dataTransfer.setData("text/plain", card.dataset.rowIndex);
            });

            card.addEventListener("dragend", () => {
                card.classList.remove("dragging");
                draggingCard = null;
                originColumn = null;
                hideDeleteZone();
            });

            card.addEventListener("dblclick", () => openEditModal(card));

            card.addEventListener("touchstart", (e) => {
                longPressTriggered = false;
                isTouchDragging = false;
                touchStart = { x: e.touches[0].clientX, y: e.touches[0].clientY };
                const rect = card.getBoundingClientRect();
                dragOffset = { x: touchStart.x - rect.left, y: touchStart.y - rect.top };
                longPressTimer = setTimeout(() => {
                    longPressTriggered = true;
                    openEditModal(card);
                }, 550);
            }, { passive: true });

            card.addEventListener("touchmove", (e) => {
                if (!touchStart) return;
                const touch = e.touches[0];
                const dx = Math.abs(touch.clientX - touchStart.x);
                const dy = Math.abs(touch.clientY - touchStart.y);
                if ((dx > 8 || dy > 8) && longPressTimer) {
                    clearTimeout(longPressTimer);
                    longPressTimer = null;
                }

                if (longPressTriggered) return;

                e.preventDefault();
                if (!isTouchDragging) {
                    isTouchDragging = true;
                    draggingCard = card;
                    originColumn = card.closest(".day-column");
                    showDeleteZone();
                    card.classList.add("dragging-touch");

                    const rect = card.getBoundingClientRect();
                    dragGhost = card.cloneNode(true);
                    dragGhost.classList.add("drag-ghost");
                    dragGhost.style.width = `${rect.width}px`;
                    dragGhost.style.height = `${rect.height}px`;
                    document.body.appendChild(dragGhost);
                }

                if (dragGhost && dragOffset) {
                    dragGhost.style.transform = `translate(${touch.clientX - dragOffset.x}px, ${touch.clientY - dragOffset.y}px)`;
                }

                const overDelete = isPointInDeleteZone(touch.clientX, touch.clientY);
                setDeleteHover(overDelete);
                if (overDelete) {
                    clearColumnHighlight();
                    currentTouchColumn = null;
                    return;
                }

                const column = document.elementFromPoint(touch.clientX, touch.clientY)?.closest(".day-column");
                if (column && column !== currentTouchColumn) {
                    clearColumnHighlight();
                    column.classList.add("drag-over");
                    currentTouchColumn = column;
                }
            }, { passive: false });

            card.addEventListener("touchend", (e) => {
                if (longPressTimer) {
                    clearTimeout(longPressTimer);
                    longPressTimer = null;
                }

                const touch = e.changedTouches[0];
                const dropOnDelete = isTouchDragging && touch && isPointInDeleteZone(touch.clientX, touch.clientY);

                if (dropOnDelete && draggingCard) {
                    deleteCard(draggingCard);
                } else if (currentTouchColumn && draggingCard && !longPressTriggered) {
                    const newDay = currentTouchColumn.dataset.day;
                    if (newDay) {
                        moveCardToDay(draggingCard, newDay);
                    }
                }

                clearColumnHighlight();
                card.classList.remove("dragging-touch");
                removeDragGhost();
                hideDeleteZone();
                draggingCard = null;
                currentTouchColumn = null;
                touchStart = null;
                isTouchDragging = false;
            });

            card.addEventListener("touchcancel", () => {
                if (longPressTimer) {
                    clearTimeout(longPressTimer);
                    longPressTimer = null;
                }
                clearColumnHighlight();
                card.classList.remove("dragging-touch");
                removeDragGhost();
                hideDeleteZone();
                draggingCard = null;
                currentTouchColumn = null;
                touchStart = null;
                isTouchDragging = false;
            });
        }

        cards.forEach(card => bindCardEvents(card));

        columns.forEach(column => {
            column.addEventListener("dragover", (e) => {
                e.preventDefault();
                column.classList.add("drag-over");
            });

            column.addEventListener("dragleave", () => {
                column.classList.remove("drag-over");
            });

            column.addEventListener("drop", (e) => {
                e.preventDefault();
                column.classList.remove("drag-over");

                if (!draggingCard) return;

                const newDay = column.dataset.day;
                const oldDay = draggingCard.dataset.day;
                if (newDay === oldDay) return;

                moveCardToDay(draggingCard, newDay);
            });
        });

        if (deleteZone) {
            deleteZone.addEventListener("dragover", (e) => {
                e.preventDefault();
                setDeleteHover(true);
            });

            deleteZone.addEventListener("dragleave", () => {
                setDeleteHover(false);
            });

            deleteZone.addEventListener("drop", (e) => {
                e.preventDefault();
                setDeleteHover(false);
                if (draggingCard) {
                    deleteCard(draggingCard);
                    draggingCard = null;
                }
                hideDeleteZone();
            });
        }

        const modal = document.getElementById("editModal");
        const closeModalBtn = document.getElementById("closeModal");
        const cancelEditBtn = document.getElementById("cancelEdit");
        const editForm = document.getElementById("editForm");
        const formFields = document.getElementById("formFields");
        let activeEditIndex = null;

        function createEmptyRow() {
            const row = { _index: nextRowIndex++ };
            scheduleHeader.forEach(col => {
                row[col] = "";
            });
            scheduleRows.push(row);
            rowsByIndex.set(row._index, row);
            return row;
        }

        function addNewCourse() {
            const row = createEmptyRow();
            const targetColumn = document.querySelector(`.day-column[data-day="${extraDay}"] .cards`);
            const card = buildCardElement(row, extraDay);
            if (targetColumn) {
                targetColumn.appendChild(card);
            }
            bindCardEvents(card);
            markDirty();
            updateConflicts();
            openEditModal(card);
        }

        if (addCourseBtn) {
            addCourseBtn.addEventListener("click", addNewCourse);
        }

        const fieldInputs = {};
        scheduleHeader.forEach(col => {
            const wrapper = document.createElement("div");
            wrapper.className = "form-field";

            const label = document.createElement("label");
            label.textContent = col;

            let input;
            if (col === "Hari") {
                input = document.createElement("select");
                const optionEmpty = document.createElement("option");
                optionEmpty.value = "";
                optionEmpty.textContent = "Tanpa Hari";
                input.appendChild(optionEmpty);
                daysOrder.forEach(day => {
                    const opt = document.createElement("option");
                    opt.value = day;
                    opt.textContent = day;
                    input.appendChild(opt);
                });
            } else if (col === "Jam Mulai" || col === "Jam Selesai") {
                input = document.createElement("input");
                input.type = "time";
            } else {
                input = document.createElement("input");
                input.type = "text";
            }

            input.name = col;
            input.className = "form-input";

            wrapper.appendChild(label);
            wrapper.appendChild(input);
            formFields.appendChild(wrapper);
            fieldInputs[col] = input;
        });

        function openEditModal(card) {
            const rowIndex = Number(card.dataset.rowIndex);
            const row = rowsByIndex.get(rowIndex);
            if (!row) return;

            activeEditIndex = rowIndex;
            scheduleHeader.forEach(col => {
                if (fieldInputs[col]) {
                    if (col === "Jam Mulai" || col === "Jam Selesai") {
                        fieldInputs[col].value = normalizeTimeValue(row[col]);
                    } else {
                        fieldInputs[col].value = row[col] ?? "";
                    }
                }
            });

            modal.classList.add("open");
            modal.setAttribute("aria-hidden", "false");
        }

        function closeModal() {
            modal.classList.remove("open");
            modal.setAttribute("aria-hidden", "true");
            activeEditIndex = null;
        }

        closeModalBtn.addEventListener("click", closeModal);
        cancelEditBtn.addEventListener("click", closeModal);
        modal.addEventListener("click", (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });

        editForm.addEventListener("submit", (e) => {
            e.preventDefault();
            if (activeEditIndex === null) return;

            const row = rowsByIndex.get(activeEditIndex);
            if (!row) return;

            scheduleHeader.forEach(col => {
                row[col] = fieldInputs[col]?.value ?? "";
            });

            const card = document.querySelector(`.card[data-row-index="${activeEditIndex}"]`);
            if (card) {
                const newDay = row["Hari"] && daysOrder.includes(row["Hari"]) ? row["Hari"] : extraDay;
                if (card.dataset.day !== newDay) {
                    moveCardToDay(card, newDay);
                } else {
                    markDirty();
                }
                updateCardContent(card, row);
                updateConflicts();
            }

            closeModal();
        });

        saveBtn.addEventListener("click", async () => {
            saveBtn.disabled = true;
            setStatus("Menyimpan...");

            const sortedRows = [...scheduleRows]
                .sort((a, b) => a._index - b._index)
                .map(row => {
                    const cleanRow = {};
                    scheduleHeader.forEach(col => {
                        cleanRow[col] = row[col] ?? "";
                    });
                    return cleanRow;
                });

            try {
                const response = await fetch("../../backend/save_schedule.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        scheduleId,
                        header: scheduleHeader,
                        rows: sortedRows
                    })
                });
                const data = await response.json();
                if (!data.ok) {
                    throw new Error(data.message || "Gagal menyimpan");
                }

                isDirty = false;
                setStatus("Perubahan tersimpan.");
                saveBtn.disabled = true;
                window.location.href = "../../beranda/index.php?notice=updated";
            } catch (err) {
                setStatus("Gagal menyimpan. Coba lagi.", true);
                saveBtn.disabled = false;
            }
        });
        updateConflicts();
    </script>
</body>
</html>
