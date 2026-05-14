<?php

require_once PROJECT_ROOT . '/app/model/schedule_store.php';

function beranda_render_jadwal_hari_ini(string $username): string
{
    ob_start();

    date_default_timezone_set('Asia/Jakarta');
    $dayOrder = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
    $todayIndex = max(0, min(6, (int)date('N') - 1));

    $active = resolve_active_schedule_item($username);
    if (!$active) {
        return '<div style="padding: 20px; text-align: center; color: white; margin-top: 50px;">
                    <p style="margin-bottom: 12px; font-family: \'Poppins\', sans-serif;">Belum ada jadwal aktif. Silakan buat atau aktifkan jadwal.</p>
                    <a href="index.php?route=jadwal" style="display: inline-block; padding: 10px 20px; background-color: var(--theme-accent, #6552fe); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; font-family: \'Poppins\', sans-serif;">Upload Jadwal</a>
                </div>';
    }

    $rows = get_schedule_rows($username, $active['id']);
    if (empty($rows)) {
        echo "⚠️ Jadwal kosong.";
        return ob_get_clean();
    }

    $rowsByDay = [];
    foreach ($dayOrder as $dayName) {
        $rowsByDay[$dayName] = [];
    }

    foreach ($rows as $row) {
        $dayValue = trim((string)($row['hari'] ?? ''));
        if (!in_array($dayValue, $dayOrder, true)) {
            continue;
        }
        $rowsByDay[$dayValue][] = [
            'nama_matakuliah' => (string)($row['nama_matakuliah'] ?? '-'),
            'jam_mulai' => (string)($row['jam_mulai'] ?? '-'),
            'jam_selesai' => (string)($row['jam_selesai'] ?? '-'),
            'ruang' => (string)($row['ruang'] ?? '-')
        ];
    }

    foreach ($rowsByDay as $dayName => &$dayRows) {
        usort($dayRows, function ($a, $b) {
            $timeA = strtotime(str_replace('.', ':', $a['jam_mulai'] ?? ''));
            $timeB = strtotime(str_replace('.', ':', $b['jam_mulai'] ?? ''));
            return $timeA <=> $timeB;
        });
    }
    unset($dayRows);

    $payload = [
        'day_order' => $dayOrder,
        'today_index' => $todayIndex,
        'rows_by_day' => $rowsByDay
    ];
    $payloadJson = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
    if ($payloadJson === false) {
        $payloadJson = '{"day_order":[],"today_index":0,"rows_by_day":{}}';
    }

    echo '<div class="daily-schedule-panel">';
    echo '  <div class="daily-schedule-toolbar">';
    echo '    <button type="button" class="day-nav-btn" id="dayNavPrev" aria-label="Hari sebelumnya">';
    echo '      <svg class="day-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 6.5L9 12l5.5 5.5"/></svg>';
    echo '    </button>';
    echo '    <button type="button" class="day-selected-btn" id="daySelectedBtn" aria-live="polite">Hari ini</button>';
    echo '    <button type="button" class="day-nav-btn" id="dayNavNext" aria-label="Hari selanjutnya">';
    echo '      <svg class="day-nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 6.5L15 12l-5.5 5.5"/></svg>';
    echo '    </button>';
    echo '  </div>';
    echo '  <div class="daily-table-shell">';
    echo '    <table border="1" cellpadding="6" cellspacing="0" class="daily-schedule-table">';
    echo '      <thead>';
    echo '        <tr>';
    echo '          <th>Nama Matakuliah</th>';
    echo '          <th>Jam Mulai</th>';
    echo '          <th>Jam Selesai</th>';
    echo '          <th>Ruang</th>';
    echo '        </tr>';
    echo '      </thead>';
    echo '      <tbody id="dailyScheduleBody"></tbody>';
    echo '    </table>';
    echo '  </div>';
    echo '  <p class="teks-putih daily-empty" id="dailyScheduleEmpty" style="display:none;">Tidak ada jadwal pada hari ini.</p>';
    echo '</div>';

    echo '<script>';
    echo '(function(){';
    echo '  const data = ' . $payloadJson . ';';
    echo '  const dayOrder = Array.isArray(data.day_order) ? data.day_order : [];';
    echo '  const rowsByDay = data.rows_by_day || {};';
    echo '  const todayIndex = Number.isInteger(data.today_index) ? data.today_index : 0;';
    echo '  let currentIndex = todayIndex;';
    echo '  const body = document.getElementById("dailyScheduleBody");';
    echo '  const emptyEl = document.getElementById("dailyScheduleEmpty");';
    echo '  const selectedBtn = document.getElementById("daySelectedBtn");';
    echo '  const prevBtn = document.getElementById("dayNavPrev");';
    echo '  const nextBtn = document.getElementById("dayNavNext");';
    echo '  function escapeHtml(value){';
    echo '    return String(value).replace(/[&<>\"\\\']/g, function(ch){';
    echo '      return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","\'":"&#39;"}[ch] || ch;';
    echo '    });';
    echo '  }';
    echo '  function getRowsByIndex(index){';
    echo '    const day = dayOrder[index] || "";';
    echo '    const rows = Array.isArray(rowsByDay[day]) ? rowsByDay[day] : [];';
    echo '    return { day, rows };';
    echo '  }';
    echo '  function render(){';
    echo '    if (!body || !emptyEl || !selectedBtn) return;';
    echo '    const result = getRowsByIndex(currentIndex);';
    echo '    selectedBtn.textContent = result.day || "Hari ini";';
    echo '    if (!result.rows.length) {';
    echo '      body.innerHTML = "";';
    echo '      emptyEl.style.display = "block";';
    echo '      emptyEl.textContent = "Tidak ada jadwal pada " + (result.day || "hari ini") + ".";';
    echo '      return;';
    echo '    }';
    echo '    emptyEl.style.display = "none";';
    echo '    body.innerHTML = result.rows.map(function(row){';
    echo '      return "<tr>" +';
    echo '        "<td>" + escapeHtml(row.nama_matakuliah || "-") + "</td>" +';
    echo '        "<td>" + escapeHtml(row.jam_mulai || "-") + "</td>" +';
    echo '        "<td>" + escapeHtml(row.jam_selesai || "-") + "</td>" +';
    echo '        "<td>" + escapeHtml(row.ruang || "-") + "</td>" +';
    echo '      "</tr>";';
    echo '    }).join("");';
    echo '  }';
    echo '  if (prevBtn) {';
    echo '    prevBtn.addEventListener("click", function(){';
    echo '      currentIndex = (currentIndex + 6) % 7;';
    echo '      render();';
    echo '    });';
    echo '  }';
    echo '  if (nextBtn) {';
    echo '    nextBtn.addEventListener("click", function(){';
    echo '      currentIndex = (currentIndex + 1) % 7;';
    echo '      render();';
    echo '    });';
    echo '  }';
    echo '  if (selectedBtn) {';
    echo '    selectedBtn.addEventListener("click", function(){';
    echo '      currentIndex = todayIndex;';
    echo '      render();';
    echo '    });';
    echo '  }';
    echo '  render();';
    echo '})();';
    echo '</script>';

    echo "\n\n<style> .teks-putih {\n  color: white;\n  padding-left: 12px;\n}\n</style>\n";

    return ob_get_clean();
}
