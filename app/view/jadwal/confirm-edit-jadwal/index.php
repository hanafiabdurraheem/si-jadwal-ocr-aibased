<?php
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
    <link rel="stylesheet" href="app/view/jadwal/confirm-edit-jadwal/style.css?v=<?= time() ?>">
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
            if (rowPos >= 0) {
                scheduleRows.splice(rowPos, 1);
            }
            card.remove();
            markDirty();
        }

        function addCard(day, row) {
            const column = document.querySelector(`.day-column[data-day='${day}']`);
            if (!column) return;
            const cardsContainer = column.querySelector('.cards');
            if (!cardsContainer) return;

            const card = document.createElement('div');
            card.className = 'card';
            card.draggable = true;
            card.dataset.rowIndex = row._index;
            card.dataset.day = day;

            const title = document.createElement('div');
            title.className = 'card-title';
            title.textContent = row['Nama Matakuliah'] || 'Tanpa Nama';

            const metaTime = document.createElement('div');
            metaTime.className = 'card-meta';
            metaTime.textContent = `${row['Jam Mulai'] || ''} - ${row['Jam Selesai'] || ''}`;

            const metaRoom = document.createElement('div');
            metaRoom.className = 'card-meta';
            metaRoom.textContent = row['Ruang'] || '';

            const conflictBadge = document.createElement('div');
            conflictBadge.className = 'conflict-badge';
            conflictBadge.textContent = 'Konflik';

            card.append(conflictBadge, title, metaTime, metaRoom);
            cardsContainer.appendChild(card);

            attachCardListeners(card);
        }

        function attachCardListeners(card) {
            card.addEventListener('dragstart', (e) => {
                draggingCard = card;
                originColumn = card.closest('.day-column');
                card.classList.add('dragging');
                e.dataTransfer.setData('text/plain', card.dataset.rowIndex || '');
                showDeleteZone();
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
                draggingCard = null;
                originColumn = null;
                hideDeleteZone();
            });

            card.addEventListener('touchstart', (e) => {
                if (e.touches.length !== 1) return;
                touchStart = e.touches[0];
                draggingCard = card;
                originColumn = card.closest('.day-column');
                longPressTriggered = false;
                longPressTimer = setTimeout(() => {
                    longPressTriggered = true;
                    openEditModal(card.dataset.rowIndex);
                }, 600);
            });

            card.addEventListener('touchmove', (e) => {
                if (!touchStart || longPressTriggered) return;
                const touch = e.touches[0];
                if (!touch) return;
                const dx = Math.abs(touch.clientX - touchStart.clientX);
                const dy = Math.abs(touch.clientY - touchStart.clientY);
                if (dx > 10 || dy > 10) {
                    clearTimeout(longPressTimer);
                    longPressTimer = null;
                    if (!isTouchDragging) {
                        isTouchDragging = true;
                        startTouchDrag(card, touch);
                    }
                    continueTouchDrag(touch);
                }
            });

            card.addEventListener('touchend', (e) => {
                clearTimeout(longPressTimer);
                longPressTimer = null;
                if (isTouchDragging) {
                    endTouchDrag(e.changedTouches[0]);
                }
                isTouchDragging = false;
                draggingCard = null;
                originColumn = null;
                touchStart = null;
            });
        }

        function startTouchDrag(card, touch) {
            dragGhost = card.cloneNode(true);
            dragGhost.classList.add('drag-ghost');
            document.body.appendChild(dragGhost);
            const rect = card.getBoundingClientRect();
            dragOffset = {
                x: touch.clientX - rect.left,
                y: touch.clientY - rect.top
            };
            showDeleteZone();
        }

        function continueTouchDrag(touch) {
            if (!dragGhost || !dragOffset) return;
            dragGhost.style.left = `${touch.clientX - dragOffset.x}px`;
            dragGhost.style.top = `${touch.clientY - dragOffset.y}px`;

            const element = document.elementFromPoint(touch.clientX, touch.clientY);
            const column = element?.closest('.day-column');
            currentTouchColumn = column;

            if (deleteZone) {
                setDeleteHover(isPointInDeleteZone(touch.clientX, touch.clientY));
            }
        }

        function endTouchDrag(touch) {
            if (!draggingCard) return;

            if (deleteZone && isPointInDeleteZone(touch.clientX, touch.clientY)) {
                deleteCard(draggingCard);
                removeDragGhost();
                hideDeleteZone();
                return;
            }

            if (currentTouchColumn) {
                moveCardToColumn(draggingCard, currentTouchColumn);
            }

            removeDragGhost();
            hideDeleteZone();
        }

        function moveCardToColumn(card, column) {
            const newDay = column.dataset.day;
            const rowIndex = Number(card.dataset.rowIndex);
            const row = rowsByIndex.get(rowIndex);
            if (!row) return;

            const oldDay = card.dataset.day;
            if (oldDay === newDay) return;

            row['Hari'] = newDay === extraDay ? '' : newDay;
            card.dataset.day = newDay;
            column.querySelector('.cards').appendChild(card);
            markDirty();
        }

        columns.forEach(column => {
            column.addEventListener('dragover', (e) => {
                e.preventDefault();
            });

            column.addEventListener('drop', (e) => {
                e.preventDefault();
                if (!draggingCard) return;
                moveCardToColumn(draggingCard, column);
            });
        });

        deleteZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            setDeleteHover(true);
        });

        deleteZone.addEventListener('dragleave', () => {
            setDeleteHover(false);
        });

        deleteZone.addEventListener('drop', (e) => {
            e.preventDefault();
            if (draggingCard) {
                deleteCard(draggingCard);
            }
            setDeleteHover(false);
            hideDeleteZone();
        });

        function openEditModal(rowIndex) {
            const row = rowsByIndex.get(Number(rowIndex));
            if (!row) return;

            const formFields = document.getElementById('formFields');
            formFields.innerHTML = '';

            scheduleHeader.forEach(field => {
                const value = row[field] ?? '';
                const fieldGroup = document.createElement('div');
                fieldGroup.className = 'field-group';

                const label = document.createElement('label');
                label.textContent = field;

                const input = document.createElement('input');
                input.type = 'text';
                input.name = field;
                input.value = value;
                input.dataset.field = field;

                fieldGroup.append(label, input);
                formFields.appendChild(fieldGroup);
            });

            document.getElementById('editModal').classList.add('show');
            document.getElementById('editModal').setAttribute('aria-hidden', 'false');
            document.getElementById('editForm').dataset.rowIndex = rowIndex;
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        }

        document.getElementById('closeModal').addEventListener('click', closeEditModal);
        document.getElementById('cancelEdit').addEventListener('click', closeEditModal);

        document.getElementById('editForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const form = e.target;
            const rowIndex = Number(form.dataset.rowIndex);
            const row = rowsByIndex.get(rowIndex);
            if (!row) return;

            form.querySelectorAll('input').forEach(input => {
                const field = input.dataset.field;
                row[field] = input.value;
            });

            const card = document.querySelector(`.card[data-row-index='${rowIndex}']`);
            if (card) {
                const title = card.querySelector('.card-title');
                const metas = card.querySelectorAll('.card-meta');
                if (title) {
                    title.textContent = row['Nama Matakuliah'] || 'Tanpa Nama';
                }
                if (metas[0]) {
                    metas[0].textContent = `${row['Jam Mulai'] || ''} - ${row['Jam Selesai'] || ''}`;
                }
                if (metas[1]) {
                    metas[1].textContent = row['Ruang'] || '';
                }
            }

            closeEditModal();
            markDirty();
        });

        addCourseBtn.addEventListener('click', () => {
            const row = {};
            scheduleHeader.forEach(field => {
                row[field] = '';
            });
            row._index = nextRowIndex++;
            rowsByIndex.set(row._index, row);
            scheduleRows.push(row);
            addCard(extraDay, row);
            markDirty();
        });

        saveBtn.addEventListener('click', async () => {
            if (!isDirty) {
                setStatus('Tidak ada perubahan.');
                return;
            }

            saveBtn.disabled = true;
            setStatus('Menyimpan...');

            const payload = {
                schedule_id: scheduleId,
                rows: scheduleRows.map(row => {
                    const rowCopy = {};
                    scheduleHeader.forEach(field => {
                        rowCopy[field] = row[field] ?? '';
                    });
                    rowCopy['_index'] = row._index;
                    return rowCopy;
                })
            };

            try {
                const response = await fetch('index.php?route=api-save-schedule', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await response.json();
                if (!data.ok) {
                    throw new Error(data.message || 'Gagal menyimpan jadwal');
                }
                setStatus('Jadwal berhasil disimpan.');
                isDirty = false;
                saveBtn.disabled = true;
                window.location.href = 'index.php?route=beranda&notice=updated';
            } catch (err) {
                setStatus('Gagal menyimpan jadwal.', true);
                saveBtn.disabled = false;
            }
        });

        cards.forEach(card => attachCardListeners(card));
    </script>
</body>
</html>
