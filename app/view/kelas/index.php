<!DOCTYPE html>
<html>
  <head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta charset="utf-8" />
  <?php include PROJECT_ROOT . '/app/view/theme.php'; ?>
  <link rel="stylesheet" href="app/view/kelas/global.css" />
    <link rel="stylesheet" href="app/view/kelas/styleguide.css" />
    <link rel="stylesheet" href="app/view/kelas/style.css?v=<?= time() ?>" />
  </head>
  <body data-chat-context="pengingat">
    <div class="kelas">
      <div class="container">
        <div class="header">
          <h1>Pengingat Tugas</h1>
          <p>Urutan berdasarkan hari dan jam terdekat.</p>
        </div>

        <section class="section">
          <button type="button" class="section-title section-toggle collapsed" data-section="active">
            Pengingat Tugas
            <span class="section-count"><?= count($tasksActive) ?></span>
            <svg class="chevron-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M6 9l6 6 6-6" />
            </svg>
          </button>
          <div class="section-body collapsed" id="section-body-active">
            <?php if (empty($tasksActive)): ?>
              <div class="empty-state">Belum ada tugas aktif.</div>
            <?php else: ?>
              <div class="task-list">
                <?php foreach ($tasksActive as $index => $task): ?>
                  <div class="task-card <?= $task['overdue'] ? 'late' : '' ?>">
                    <div class="task-header" data-target="detail-active-<?= $index ?>">
                      <div>
                        <div class="task-title"><?= htmlspecialchars($task['mataKuliah'] ?: 'Tugas') ?></div>
                        <div class="task-subtitle"><?= htmlspecialchars($task['jenis']) ?></div>
                      </div>
                      <div class="task-time">
                        <div><?= htmlspecialchars($task['hari']) ?></div>
                        <div><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' • ' . htmlspecialchars($task['jam_display']) : '' ?></div>
                      </div>
                    </div>
                    <div class="task-detail" id="detail-active-<?= $index ?>">
                      <div class="detail-row"><span>Status</span><strong><?= htmlspecialchars($task['status'] ?: 'Belum selesai') ?></strong></div>
                      <div class="detail-row"><span>Deadline</span><strong><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' ' . htmlspecialchars($task['jam_display']) : '' ?></strong></div>
                      <div class="detail-row"><span>Mata Kuliah</span><strong><?= htmlspecialchars($task['mataKuliah']) ?></strong></div>
                      <div class="detail-row"><span>Jenis Tugas</span><strong><?= htmlspecialchars($task['jenis']) ?></strong></div>
                      <?php if (!empty($task['link_pelaksanaan'])): ?>
                        <div class="detail-row"><span>Link</span><strong><a href="<?= htmlspecialchars($task['link_pelaksanaan']) ?>" target="_blank" rel="noopener noreferrer">Buka link</a></strong></div>
                      <?php endif; ?>
                    </div>
                    <div class="task-actions">
                      <form method="POST">
                        <input type="hidden" name="task_action" value="done">
                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                        <button type="submit" class="btn-primary">Selesai</button>
                      </form>
                      <form method="POST">
                        <input type="hidden" name="task_action" value="pending">
                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                        <button type="submit" class="btn-secondary">Belum</button>
                      </form>
                      <?php if ($task['overdue']): ?>
                        <form method="POST">
                          <input type="hidden" name="task_action" value="archive">
                          <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                          <button type="submit" class="btn-warning">Arsipkan</button>
                        </form>
                      <?php endif; ?>
                      <form method="POST">
                        <input type="hidden" name="task_action" value="delete">
                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                        <button type="submit" class="btn-danger">Hapus</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <div class="section-separator"></div>

        <section class="section">
          <button type="button" class="section-title section-toggle collapsed" data-section="history">
            Histori Tugas
            <span class="section-count"><?= count($tasksHistory) ?></span>
            <svg class="chevron-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M6 9l6 6 6-6" />
            </svg>
          </button>
          <div class="section-body collapsed" id="section-body-history">
            <?php if (empty($tasksHistory)): ?>
              <div class="empty-state">Belum ada tugas selesai.</div>
            <?php else: ?>
              <div class="task-list">
                <?php foreach ($tasksHistory as $index => $task): ?>
                  <div class="task-card done">
                    <div class="task-header" data-target="detail-history-<?= $index ?>">
                      <div>
                        <div class="task-title"><?= htmlspecialchars($task['mataKuliah'] ?: 'Tugas') ?></div>
                        <div class="task-subtitle"><?= htmlspecialchars($task['jenis']) ?></div>
                      </div>
                      <div class="task-time">
                        <div><?= htmlspecialchars($task['hari']) ?></div>
                        <div><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' • ' . htmlspecialchars($task['jam_display']) : '' ?></div>
                      </div>
                    </div>
                    <div class="task-detail" id="detail-history-<?= $index ?>">
                      <div class="detail-row"><span>Status</span><strong>Selesai</strong></div>
                      <div class="detail-row"><span>Deadline</span><strong><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' ' . htmlspecialchars($task['jam_display']) : '' ?></strong></div>
                    </div>
                    <div class="task-actions">
                      <form method="POST">
                        <input type="hidden" name="task_action" value="delete">
                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                        <button type="submit" class="btn-danger">Hapus</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <div class="section-separator"></div>

        <section class="section">
          <button type="button" class="section-title section-toggle collapsed" data-section="archive">
            Arsip Tugas
            <span class="section-count"><?= count($tasksArchive) ?></span>
            <svg class="chevron-icon" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M6 9l6 6 6-6" />
            </svg>
          </button>
          <div class="section-body collapsed" id="section-body-archive">
            <?php if (empty($tasksArchive)): ?>
              <div class="empty-state">Belum ada tugas diarsipkan.</div>
            <?php else: ?>
              <div class="task-list">
                <?php foreach ($tasksArchive as $index => $task): ?>
                  <div class="task-card archived">
                    <div class="task-header" data-target="detail-archive-<?= $index ?>">
                      <div>
                        <div class="task-title"><?= htmlspecialchars($task['mataKuliah'] ?: 'Tugas') ?></div>
                        <div class="task-subtitle"><?= htmlspecialchars($task['jenis']) ?></div>
                      </div>
                      <div class="task-time">
                        <div><?= htmlspecialchars($task['hari']) ?></div>
                        <div><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' • ' . htmlspecialchars($task['jam_display']) : '' ?></div>
                      </div>
                    </div>
                    <div class="task-detail" id="detail-archive-<?= $index ?>">
                      <div class="detail-row"><span>Status</span><strong>Arsip</strong></div>
                      <div class="detail-row"><span>Deadline</span><strong><?= htmlspecialchars($task['tanggal']) ?><?= $task['jam_display'] ? ' ' . htmlspecialchars($task['jam_display']) : '' ?></strong></div>
                    </div>
                    <div class="task-actions">
                      <form method="POST">
                        <input type="hidden" name="task_action" value="delete">
                        <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                        <button type="submit" class="btn-danger">Hapus</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </section>

      </div>
    </div>
    <?php include PROJECT_ROOT . '/app/view/nav.php'; ?>
    <?php include PROJECT_ROOT . '/app/view/chat/widget.php'; ?>

    <script>
      document.querySelectorAll('.task-header').forEach(header => {
        header.addEventListener('click', () => {
          const card = header.closest('.task-card');
          if (card) {
            card.classList.toggle('open');
          }
        });
      });

      document.querySelectorAll('.section-toggle').forEach(toggle => {
        toggle.addEventListener('click', () => {
          const sectionName = toggle.dataset.section;
          const body = document.getElementById('section-body-' + sectionName);
          if (!body) return;
          body.classList.toggle('collapsed');
          toggle.classList.toggle('collapsed');
        });
      });
    </script>
  </body>
</html>
