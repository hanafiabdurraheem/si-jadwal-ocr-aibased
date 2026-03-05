(() => {
  const BASE_PATH = '/si-jadwal';
  const SW_URL = `${BASE_PATH}/service-worker.js`;
  const DB_NAME = 'si-jadwal-pwa-db';
  const DB_VERSION = 1;

  const STORES = {
    META: 'meta',
    SCHEDULES: 'schedules',
    TASKS: 'tasks',
    OUTBOX: 'outbox',
    NOTIFICATIONS: 'notifications'
  };

  const DAY_INDEX_TO_ID = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  const DEFAULT_HEADER = [
    'No',
    'Kode',
    'Nama Matakuliah',
    'SKS',
    'Kelas/Rombel',
    'Pengampu',
    'Jenis',
    'Ruang',
    'Hari',
    'Jam Mulai',
    'Jam Selesai',
    'Mode'
  ];

  let dbPromise;
  let initPromise;
  let flushLock = false;
  let reminderInterval;
  let deferredInstallPrompt = null;
  let installAvailable = false;

  function log(...args) {
    console.log('[SiJadwalPWA]', ...args);
  }

  function emit(eventName, detail = {}) {
    window.dispatchEvent(new CustomEvent(eventName, { detail }));
  }

  function ensureHeadTag(tagName, attributes) {
    const existing = Array.from(document.head.getElementsByTagName(tagName)).find((node) =>
      Object.entries(attributes).every(([key, value]) => node.getAttribute(key) === value)
    );
    if (existing) {
      return;
    }

    const el = document.createElement(tagName);
    Object.entries(attributes).forEach(([key, value]) => {
      el.setAttribute(key, value);
    });
    document.head.appendChild(el);
  }

  function ensurePwaHead() {
    ensureHeadTag('link', { rel: 'manifest', href: `${BASE_PATH}/manifest.webmanifest` });
    ensureHeadTag('meta', { name: 'theme-color', content: '#121212' });
    ensureHeadTag('meta', { name: 'apple-mobile-web-app-capable', content: 'yes' });
    ensureHeadTag('meta', { name: 'apple-mobile-web-app-status-bar-style', content: 'black-translucent' });
  }

  function requestToPromise(request) {
    return new Promise((resolve, reject) => {
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  async function openDb() {
    if (dbPromise) {
      return dbPromise;
    }

    dbPromise = new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);

      request.onupgradeneeded = (event) => {
        const db = event.target.result;

        if (!db.objectStoreNames.contains(STORES.META)) {
          db.createObjectStore(STORES.META, { keyPath: 'key' });
        }

        if (!db.objectStoreNames.contains(STORES.SCHEDULES)) {
          db.createObjectStore(STORES.SCHEDULES, { keyPath: 'id' });
        }

        if (!db.objectStoreNames.contains(STORES.TASKS)) {
          db.createObjectStore(STORES.TASKS, { keyPath: 'id' });
        }

        if (!db.objectStoreNames.contains(STORES.OUTBOX)) {
          const outbox = db.createObjectStore(STORES.OUTBOX, { keyPath: 'id', autoIncrement: true });
          outbox.createIndex('by_next_attempt', 'nextAttemptAt', { unique: false });
        }

        if (!db.objectStoreNames.contains(STORES.NOTIFICATIONS)) {
          db.createObjectStore(STORES.NOTIFICATIONS, { keyPath: 'id' });
        }
      };

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error || new Error('Gagal membuka IndexedDB'));
    });

    return dbPromise;
  }

  async function withTransaction(storeName, mode, handler) {
    const db = await openDb();

    return new Promise((resolve, reject) => {
      const tx = db.transaction(storeName, mode);
      const store = tx.objectStore(storeName);
      let result;

      Promise.resolve()
        .then(() => handler(store, tx))
        .then((value) => {
          result = value;
        })
        .catch((error) => {
          reject(error);
          tx.abort();
        });

      tx.oncomplete = () => resolve(result);
      tx.onerror = () => reject(tx.error || new Error('IndexedDB transaction error'));
      tx.onabort = () => reject(tx.error || new Error('IndexedDB transaction aborted'));
    });
  }

  async function idbGet(storeName, key) {
    return withTransaction(storeName, 'readonly', async (store) => {
      const req = store.get(key);
      return requestToPromise(req);
    });
  }

  async function idbGetAll(storeName) {
    return withTransaction(storeName, 'readonly', async (store) => {
      const req = store.getAll();
      return requestToPromise(req);
    });
  }

  async function idbPut(storeName, value) {
    return withTransaction(storeName, 'readwrite', async (store) => {
      const req = store.put(value);
      await requestToPromise(req);
      return value;
    });
  }

  async function idbAdd(storeName, value) {
    return withTransaction(storeName, 'readwrite', async (store) => {
      const req = store.add(value);
      return requestToPromise(req);
    });
  }

  async function idbDelete(storeName, key) {
    return withTransaction(storeName, 'readwrite', async (store) => {
      const req = store.delete(key);
      await requestToPromise(req);
      return true;
    });
  }

  async function idbClear(storeName) {
    return withTransaction(storeName, 'readwrite', async (store) => {
      const req = store.clear();
      await requestToPromise(req);
      return true;
    });
  }

  function normalizeTimeValue(value) {
    if (!value) {
      return '';
    }

    const match = String(value).trim().match(/(\d{1,2})[.:](\d{2})/);
    if (!match) {
      return '';
    }

    return `${match[1].padStart(2, '0')}:${match[2]}`;
  }

  function parseDateTime(dateString, timeString = '') {
    if (!dateString) {
      return null;
    }

    const normalizedTime = normalizeTimeValue(timeString) || '23:59';
    const composed = `${dateString}T${normalizedTime}:00`;
    const date = new Date(composed);
    return Number.isNaN(date.getTime()) ? null : date;
  }

  function parseTodayTime(timeString) {
    const normalized = normalizeTimeValue(timeString);
    if (!normalized) {
      return null;
    }

    const [hour, minute] = normalized.split(':').map(Number);
    if (Number.isNaN(hour) || Number.isNaN(minute)) {
      return null;
    }

    const now = new Date();
    const candidate = new Date(now.getFullYear(), now.getMonth(), now.getDate(), hour, minute, 0, 0);
    return candidate;
  }

  function cloneRows(rows) {
    if (!Array.isArray(rows)) {
      return [];
    }
    return rows.map((row) => ({ ...row }));
  }

  async function putMeta(key, value) {
    return idbPut(STORES.META, { key, value });
  }

  async function getMeta(key, fallback = null) {
    const row = await idbGet(STORES.META, key);
    return row ? row.value : fallback;
  }

  async function clearStoreAndInsert(storeName, rows) {
    await idbClear(storeName);
    for (const row of rows) {
      await idbPut(storeName, row);
    }
  }

  async function storeSnapshot(snapshot) {
    if (!snapshot || !snapshot.ok) {
      return;
    }

    const scheduleIndex = snapshot.schedule_index || { active_id: null, items: [] };
    const scheduleMap = snapshot.schedules || {};
    const items = Array.isArray(scheduleIndex.items) ? scheduleIndex.items : [];

    const scheduleRows = items.map((item) => {
      const details = scheduleMap[item.id] || {};
      return {
        id: item.id,
        name: details.name || item.name || 'Jadwal',
        header: Array.isArray(details.header) && details.header.length > 0 ? details.header : DEFAULT_HEADER,
        rows: cloneRows(details.rows || []),
        isActive: item.id === scheduleIndex.active_id,
        updatedAt: Date.now()
      };
    });

    await clearStoreAndInsert(STORES.SCHEDULES, scheduleRows);

    const taskRows = Array.isArray(snapshot.tasks)
      ? snapshot.tasks.map((task) => ({ ...task }))
      : [];
    await clearStoreAndInsert(STORES.TASKS, taskRows);

    await putMeta('schedule_index', scheduleIndex);
    await putMeta('active_schedule_id', scheduleIndex.active_id || null);
    await putMeta('last_snapshot_at', Date.now());

    emit('si-jadwal:snapshot-updated', { scheduleIndex });
  }

  async function primeScheduleIndex(payload) {
    const items = Array.isArray(payload?.items) ? payload.items : [];
    const activeId = payload?.activeId || null;
    const scheduleIndex = {
      active_id: activeId,
      items: items.map((item) => ({
        id: item.id,
        name: item.name || 'Jadwal',
        created_at: item.created_at || '',
        updated_at: item.updated_at || '',
        is_active: item.id === activeId ? 1 : 0
      }))
    };

    await putMeta('schedule_index', scheduleIndex);
    await putMeta('active_schedule_id', activeId);

    for (const item of scheduleIndex.items) {
      const existing = await idbGet(STORES.SCHEDULES, item.id);
      await idbPut(STORES.SCHEDULES, {
        id: item.id,
        name: item.name,
        header: existing?.header || DEFAULT_HEADER,
        rows: cloneRows(existing?.rows || []),
        isActive: item.id === activeId,
        updatedAt: Date.now()
      });
    }
  }

  async function primeSchedule(payload) {
    if (!payload?.id) {
      return;
    }

    const existing = await idbGet(STORES.SCHEDULES, payload.id);
    const record = {
      id: payload.id,
      name: payload.name || existing?.name || 'Jadwal',
      header: Array.isArray(payload.header) && payload.header.length > 0 ? payload.header : (existing?.header || DEFAULT_HEADER),
      rows: cloneRows(payload.rows || existing?.rows || []),
      isActive: payload.isActive === undefined ? Boolean(existing?.isActive) : Boolean(payload.isActive),
      updatedAt: Date.now()
    };

    await idbPut(STORES.SCHEDULES, record);

    if (record.isActive) {
      await putMeta('active_schedule_id', payload.id);
    }
  }

  async function getSchedule(id) {
    if (!id) {
      return null;
    }
    return idbGet(STORES.SCHEDULES, id);
  }

  async function getActiveSchedule() {
    const activeId = await getMeta('active_schedule_id', null);
    if (!activeId) {
      return null;
    }
    return idbGet(STORES.SCHEDULES, activeId);
  }

  async function updateScheduleRowsLocal(scheduleId, header, rows, name = null) {
    if (!scheduleId) {
      return;
    }

    const existing = await idbGet(STORES.SCHEDULES, scheduleId);
    await idbPut(STORES.SCHEDULES, {
      id: scheduleId,
      name: name || existing?.name || 'Jadwal',
      header: Array.isArray(header) && header.length > 0 ? header : (existing?.header || DEFAULT_HEADER),
      rows: cloneRows(rows || []),
      isActive: existing ? Boolean(existing.isActive) : false,
      updatedAt: Date.now()
    });
  }

  async function updateScheduleNameLocal(scheduleId, name) {
    if (!scheduleId) {
      return;
    }

    const existing = await idbGet(STORES.SCHEDULES, scheduleId);
    if (!existing) {
      return;
    }

    existing.name = name;
    existing.updatedAt = Date.now();
    await idbPut(STORES.SCHEDULES, existing);

    const index = await getMeta('schedule_index', { active_id: null, items: [] });
    if (Array.isArray(index.items)) {
      index.items = index.items.map((item) => (item.id === scheduleId ? { ...item, name } : item));
      await putMeta('schedule_index', index);
    }
  }

  async function setActiveScheduleLocal(scheduleId) {
    if (!scheduleId) {
      return;
    }

    const schedules = await idbGetAll(STORES.SCHEDULES);
    let found = false;

    for (const schedule of schedules) {
      const isActive = schedule.id === scheduleId;
      if (schedule.isActive !== isActive) {
        schedule.isActive = isActive;
        schedule.updatedAt = Date.now();
        await idbPut(STORES.SCHEDULES, schedule);
      }
      if (isActive) {
        found = true;
      }
    }

    if (!found) {
      await idbPut(STORES.SCHEDULES, {
        id: scheduleId,
        name: 'Jadwal',
        header: DEFAULT_HEADER,
        rows: [],
        isActive: true,
        updatedAt: Date.now()
      });
    }

    const index = await getMeta('schedule_index', { active_id: null, items: [] });
    index.active_id = scheduleId;
    index.items = Array.isArray(index.items)
      ? index.items.map((item) => ({ ...item, is_active: item.id === scheduleId ? 1 : 0 }))
      : [];

    await putMeta('schedule_index', index);
    await putMeta('active_schedule_id', scheduleId);
  }

  async function removeQueueDuplicates(type, coalesceKey) {
    if (!coalesceKey) {
      return;
    }

    const rows = await idbGetAll(STORES.OUTBOX);
    for (const row of rows) {
      if (row.type === type && row.coalesceKey === coalesceKey) {
        await idbDelete(STORES.OUTBOX, row.id);
      }
    }
  }

  async function enqueueMutation(type, payload, coalesceKey = null) {
    await removeQueueDuplicates(type, coalesceKey);

    const id = await idbAdd(STORES.OUTBOX, {
      type,
      payload,
      coalesceKey,
      createdAt: Date.now(),
      nextAttemptAt: Date.now(),
      tries: 0
    });

    emit('si-jadwal:queue-updated', { queued: true });
    return id;
  }

  async function sendMutation(type, payload) {
    let endpoint = null;

    if (type === 'save_schedule') {
      endpoint = `${BASE_PATH}/backend/save_schedule.php`;
    } else if (type === 'set_active_schedule') {
      endpoint = `${BASE_PATH}/backend/set_active_schedule.php`;
    } else if (type === 'rename_schedule') {
      endpoint = `${BASE_PATH}/backend/rename_schedule.php`;
    }

    if (!endpoint) {
      return { ok: true };
    }

    let response;
    try {
      response = await fetch(endpoint, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
    } catch (error) {
      return { ok: false, retryable: true, reason: 'network' };
    }

    if (response.status === 401) {
      return { ok: false, retryable: false, unauthorized: true };
    }

    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      return { ok: false, retryable: true, reason: 'invalid-json' };
    }

    if (!response.ok || !data?.ok) {
      return { ok: false, retryable: true, reason: data?.message || 'server-error' };
    }

    return { ok: true, data };
  }

  async function registerBackgroundSync() {
    if (!('serviceWorker' in navigator)) {
      return;
    }

    try {
      const registration = await navigator.serviceWorker.ready;
      if ('sync' in registration) {
        await registration.sync.register('si-jadwal-sync');
      }
    } catch (error) {
      // Browser belum mendukung sync manager.
    }
  }

  async function flushOutbox() {
    if (flushLock || !navigator.onLine) {
      return;
    }

    flushLock = true;
    emit('si-jadwal:sync-state', { state: 'running' });

    try {
      const queue = await idbGetAll(STORES.OUTBOX);
      queue.sort((a, b) => a.id - b.id);

      let hadPendingError = false;
      for (const job of queue) {
        const now = Date.now();
        if ((job.nextAttemptAt || 0) > now) {
          continue;
        }

        const result = await sendMutation(job.type, job.payload);
        if (result.ok) {
          await idbDelete(STORES.OUTBOX, job.id);
          continue;
        }

        if (result.unauthorized) {
          emit('si-jadwal:auth-expired', {});
          break;
        }

        hadPendingError = true;
        job.tries = (job.tries || 0) + 1;
        job.nextAttemptAt = Date.now() + Math.min(2 ** job.tries * 1000, 5 * 60 * 1000);
        await idbPut(STORES.OUTBOX, job);
      }

      if (!hadPendingError) {
        await refreshSnapshot();
      }
    } finally {
      flushLock = false;
      emit('si-jadwal:sync-state', { state: 'idle' });
    }
  }

  async function refreshSnapshot() {
    if (!navigator.onLine) {
      return null;
    }

    let response;
    try {
      response = await fetch(`${BASE_PATH}/backend/api/pwa_snapshot.php`, {
        method: 'GET',
        credentials: 'include',
        cache: 'no-store'
      });
    } catch (error) {
      return null;
    }

    if (response.status === 401) {
      emit('si-jadwal:auth-expired', {});
      return null;
    }

    if (!response.ok) {
      return null;
    }

    let data;
    try {
      data = await response.json();
    } catch (error) {
      return null;
    }

    if (!data?.ok) {
      return null;
    }

    await storeSnapshot(data);
    return data;
  }

  async function saveSchedule(payload) {
    const safePayload = {
      scheduleId: payload?.scheduleId || '',
      header: Array.isArray(payload?.header) ? payload.header : DEFAULT_HEADER,
      rows: Array.isArray(payload?.rows) ? cloneRows(payload.rows) : [],
      name: payload?.name || null
    };

    if (!safePayload.scheduleId) {
      return { ok: false, message: 'scheduleId tidak valid' };
    }

    await updateScheduleRowsLocal(safePayload.scheduleId, safePayload.header, safePayload.rows, safePayload.name);

    if (!navigator.onLine) {
      await enqueueMutation('save_schedule', safePayload, `save_schedule:${safePayload.scheduleId}`);
      await registerBackgroundSync();
      return { ok: true, queued: true };
    }

    const result = await sendMutation('save_schedule', safePayload);
    if (result.ok) {
      await refreshSnapshot();
      return { ok: true, queued: false };
    }

    await enqueueMutation('save_schedule', safePayload, `save_schedule:${safePayload.scheduleId}`);
    await registerBackgroundSync();
    return { ok: true, queued: true };
  }

  async function updateActiveSchedule(scheduleId) {
    if (!scheduleId) {
      return { ok: false, message: 'scheduleId tidak valid' };
    }

    await setActiveScheduleLocal(scheduleId);

    if (!navigator.onLine) {
      await enqueueMutation('set_active_schedule', { scheduleId }, 'set_active_schedule:global');
      await registerBackgroundSync();
      return { ok: true, queued: true };
    }

    const result = await sendMutation('set_active_schedule', { scheduleId });
    if (result.ok) {
      await refreshSnapshot();
      return { ok: true, queued: false };
    }

    await enqueueMutation('set_active_schedule', { scheduleId }, 'set_active_schedule:global');
    await registerBackgroundSync();
    return { ok: true, queued: true };
  }

  async function renameSchedule(scheduleId, name) {
    if (!scheduleId || !name) {
      return { ok: false, message: 'Data rename tidak valid' };
    }

    await updateScheduleNameLocal(scheduleId, name);

    if (!navigator.onLine) {
      await enqueueMutation('rename_schedule', { scheduleId, name }, `rename_schedule:${scheduleId}`);
      await registerBackgroundSync();
      return { ok: true, queued: true, name };
    }

    const result = await sendMutation('rename_schedule', { scheduleId, name });
    if (result.ok) {
      await refreshSnapshot();
      return { ok: true, queued: false, name };
    }

    await enqueueMutation('rename_schedule', { scheduleId, name }, `rename_schedule:${scheduleId}`);
    await registerBackgroundSync();
    return { ok: true, queued: true, name };
  }

  async function hasPendingQueue() {
    const queue = await idbGetAll(STORES.OUTBOX);
    return queue.length > 0;
  }

  async function showNotification(title, options) {
    if (!('Notification' in window) || Notification.permission !== 'granted') {
      return;
    }

    try {
      const registration = await navigator.serviceWorker.getRegistration(BASE_PATH + '/');
      if (registration && registration.showNotification) {
        await registration.showNotification(title, options);
        return;
      }
    } catch (error) {
      // fallback ke Notification API langsung.
    }

    new Notification(title, options);
  }

  async function sendTestNotification() {
    if (!('Notification' in window)) {
      return { ok: false, reason: 'unsupported' };
    }
    if (Notification.permission !== 'granted') {
      return { ok: false, reason: 'permission-not-granted' };
    }

    await showNotification('Si Jadwal', {
      body: 'Notifikasi berhasil diaktifkan.',
      tag: `test-${Date.now()}`,
      data: { type: 'test' }
    });
    return { ok: true };
  }

  async function isNotificationSent(id) {
    const row = await idbGet(STORES.NOTIFICATIONS, id);
    return Boolean(row);
  }

  async function markNotificationSent(id) {
    await idbPut(STORES.NOTIFICATIONS, { id, sentAt: Date.now() });
  }

  async function pruneNotificationHistory() {
    const rows = await idbGetAll(STORES.NOTIFICATIONS);
    const cutoff = Date.now() - (14 * 24 * 60 * 60 * 1000);
    for (const row of rows) {
      if ((row.sentAt || 0) < cutoff) {
        await idbDelete(STORES.NOTIFICATIONS, row.id);
      }
    }
  }

  async function sendUpcomingClassReminder(now) {
    const active = await getActiveSchedule();
    if (!active || !Array.isArray(active.rows)) {
      return;
    }

    const todayName = DAY_INDEX_TO_ID[now.getDay()];

    for (const row of active.rows) {
      const rowDay = String(row.Hari || '').trim();
      if (rowDay !== todayName) {
        continue;
      }

      const startDate = parseTodayTime(row['Jam Mulai']);
      if (!startDate) {
        continue;
      }

      const diffMs = startDate.getTime() - now.getTime();
      const diffMin = Math.floor(diffMs / 60000);
      if (diffMin < 0 || diffMin > 15) {
        continue;
      }

      const subject = row['Nama Matakuliah'] || 'Kelas';
      const room = row.Ruang ? ` • ${row.Ruang}` : '';
      const dateKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
      const eventId = `class:${active.id}:${subject}:${dateKey}:${normalizeTimeValue(row['Jam Mulai'])}`;

      if (await isNotificationSent(eventId)) {
        continue;
      }

      await showNotification('Pengingat Kelas', {
        body: `${subject} mulai ${diffMin} menit lagi${room}`,
        tag: eventId,
        data: { type: 'class', scheduleId: active.id }
      });
      await markNotificationSent(eventId);
    }
  }

  async function sendTaskReminder(now) {
    const tasks = await idbGetAll(STORES.TASKS);

    for (const task of tasks) {
      if (!task || task.status === 'Selesai' || task.status === 'Arsip') {
        continue;
      }

      const dueDate = parseDateTime(task.tanggal, task.jam || '23:59');
      if (!dueDate) {
        continue;
      }

      const diffMs = dueDate.getTime() - now.getTime();
      const diffMin = Math.floor(diffMs / 60000);
      if (diffMin < 0 || diffMin > 60) {
        continue;
      }

      const taskName = task.jenis || 'Tugas';
      const course = task.mata_kuliah || 'Mata kuliah';
      const eventId = `task:${task.id}:${dueDate.toISOString().slice(0, 16)}`;

      if (await isNotificationSent(eventId)) {
        continue;
      }

      await showNotification('Deadline Tugas', {
        body: `${taskName} (${course}) jatuh tempo ${diffMin} menit lagi`,
        tag: eventId,
        data: { type: 'task', taskId: task.id }
      });
      await markNotificationSent(eventId);
    }
  }

  async function runReminderChecks() {
    if (!('Notification' in window) || Notification.permission !== 'granted') {
      return;
    }

    await pruneNotificationHistory();
    const now = new Date();
    await sendUpcomingClassReminder(now);
    await sendTaskReminder(now);
  }

  async function startReminderLoop() {
    if (!('Notification' in window) || Notification.permission !== 'granted') {
      return;
    }

    if (reminderInterval) {
      return;
    }

    await runReminderChecks();
    reminderInterval = window.setInterval(() => {
      runReminderChecks().catch(() => null);
    }, 60 * 1000);
  }

  async function requestNotificationPermission() {
    if (!('Notification' in window)) {
      return { ok: false, permission: 'unsupported' };
    }
    if (!window.isSecureContext) {
      return { ok: false, permission: Notification.permission, reason: 'insecure-context' };
    }
    if (!('serviceWorker' in navigator)) {
      return { ok: false, permission: Notification.permission, reason: 'service-worker-unsupported' };
    }

    const permission = await Notification.requestPermission();
    if (permission === 'granted') {
      await startReminderLoop();
      await sendTestNotification();
      return { ok: true, permission, reason: 'granted' };
    }

    return { ok: false, permission, reason: permission === 'denied' ? 'denied' : 'default' };
  }

  function setupInstallPromptListener() {
    window.addEventListener('beforeinstallprompt', (event) => {
      event.preventDefault();
      deferredInstallPrompt = event;
      installAvailable = true;
      emit('si-jadwal:install-available', {});
    });
  }

  async function promptInstall() {
    if (!deferredInstallPrompt) {
      return false;
    }

    deferredInstallPrompt.prompt();
    await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;
    installAvailable = false;
    return true;
  }

  function canPromptInstall() {
    return Boolean(deferredInstallPrompt || installAvailable);
  }

  function isStandaloneMode() {
    const mediaStandalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;
    const iosStandalone = window.navigator.standalone === true;
    return Boolean(mediaStandalone || iosStandalone);
  }

  async function getRuntimeDiagnostics() {
    const hostname = window.location.hostname || '';
    const isLocalhost = hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '::1';
    const isLanIp = /^(?:\d{1,3}\.){3}\d{1,3}$/.test(hostname);
    const protocol = window.location.protocol || '';
    const secure = window.isSecureContext;
    let swReady = false;

    if ('serviceWorker' in navigator) {
      try {
        await navigator.serviceWorker.ready;
        swReady = true;
      } catch (error) {
        swReady = false;
      }
    }

    return {
      protocol,
      hostname,
      isSecureContext: secure,
      isLocalhost,
      isLanIp,
      notificationPermission: ('Notification' in window) ? Notification.permission : 'unsupported',
      canPromptInstall: canPromptInstall(),
      isStandalone: isStandaloneMode(),
      serviceWorkerReady: swReady
    };
  }

  async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
      return;
    }

    try {
      await navigator.serviceWorker.register(SW_URL, { scope: `${BASE_PATH}/` });
    } catch (error) {
      log('Gagal register service worker:', error);
    }

    navigator.serviceWorker.addEventListener('message', (event) => {
      if (event.data?.type === 'SI_JADWAL_TRIGGER_SYNC') {
        flushOutbox().catch(() => null);
      }
    });
  }

  async function clearLocalData() {
    try {
      const cacheKeys = await caches.keys();
      await Promise.all(
        cacheKeys
          .filter((key) => key.startsWith('si-jadwal-'))
          .map((key) => caches.delete(key))
      );
    } catch (error) {
      // ignore
    }

    await new Promise((resolve) => {
      const request = indexedDB.deleteDatabase(DB_NAME);
      request.onsuccess = () => resolve();
      request.onerror = () => resolve();
      request.onblocked = () => resolve();
    });
  }

  function bindLogoutCleanup() {
    const links = document.querySelectorAll('a[href*="backend/logout.php"]');
    links.forEach((link) => {
      if (link.dataset.pwaBound === '1') {
        return;
      }

      link.dataset.pwaBound = '1';
      link.addEventListener('click', (event) => {
        event.preventDefault();
        const destination = link.getAttribute('href') || `${BASE_PATH}/login/index.php`;
        clearLocalData()
          .catch(() => null)
          .finally(() => {
            window.location.href = destination;
          });
      });
    });
  }

  function bindNetworkEvents() {
    window.addEventListener('online', () => {
      emit('si-jadwal:network', { online: true });
      flushOutbox().catch(() => null);
      refreshSnapshot().catch(() => null);
    });

    window.addEventListener('offline', () => {
      emit('si-jadwal:network', { online: false });
    });
  }

  function setConnectivityBadge() {
    const node = document.getElementById('pwa-connectivity-badge');
    if (!node) {
      return;
    }

    const syncText = navigator.onLine ? 'Online' : 'Offline';
    node.textContent = syncText;
    node.classList.toggle('offline', !navigator.onLine);
  }

  function bindConnectivityBadge() {
    setConnectivityBadge();
    window.addEventListener('online', setConnectivityBadge);
    window.addEventListener('offline', setConnectivityBadge);
  }

  async function init() {
    if (initPromise) {
      return initPromise;
    }

    initPromise = (async () => {
      ensurePwaHead();
      setupInstallPromptListener();
      bindNetworkEvents();
      bindConnectivityBadge();
      bindLogoutCleanup();

      await openDb();
      await registerServiceWorker();

      if (navigator.onLine) {
        await refreshSnapshot();
      }

      const hasQueue = await hasPendingQueue();
      if (hasQueue && navigator.onLine) {
        await flushOutbox();
      }

      if ('Notification' in window && Notification.permission === 'granted') {
        await startReminderLoop();
      }

      emit('si-jadwal:ready', {});
    })();

    return initPromise;
  }

  window.SiJadwalPWA = {
    init,
    refreshSnapshot,
    flushOutbox,
    saveSchedule,
    updateActiveSchedule,
    renameSchedule,
    primeSchedule,
    primeScheduleIndex,
    getSchedule,
    getActiveSchedule,
    requestNotificationPermission,
    sendTestNotification,
    promptInstall,
    canPromptInstall,
    isStandaloneMode,
    getRuntimeDiagnostics,
    clearLocalData,
    hasPendingQueue
  };
})();
