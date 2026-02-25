(() => {
  const modal = document.getElementById('chatbotModal');
  const toggle = document.getElementById('chatbotToggle');
  const closeBtn = document.getElementById('chatbotClose');
  const messagesEl = document.getElementById('chatbotMessages');
  const inputEl = document.getElementById('chatbotInput');
  const sendBtn = document.getElementById('chatbotSend');
  const chips = document.querySelectorAll('.chatbot-chip');

  if (!modal || !toggle || !closeBtn || !messagesEl || !inputEl || !sendBtn) return;

  const apiUrl = '/si-jadwal/backend/chatbot.php';
  const addTaskUrl = '/si-jadwal/backend/task_quick_add.php';
  const context = document.body.dataset.chatContext || 'general';
  const storageKey = `si_jadwal_chat_${context}`;
  const maxMessages = 12;
  const taskFlow = {
    active: false,
    step: 0,
    mataKuliah: '',
    jenis: '',
    deadline: ''
  };

  function loadHistory() {
    try {
      const raw = localStorage.getItem(storageKey);
      if (!raw) return [];
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed.slice(-maxMessages) : [];
    } catch {
      return [];
    }
  }

  function saveHistory(history) {
    try {
      localStorage.setItem(storageKey, JSON.stringify(history.slice(-maxMessages)));
    } catch {}
  }

  function renderHistory(history) {
    messagesEl.innerHTML = '';
    if (history.length === 0) {
      appendMessage('Hai! Aku bisa bantu bikin to-do hari ini, tambah tugas, atau cek jadwal. Mulai saja dengan mengetik kebutuhanmu.', 'bot');
      return;
    }
    history.forEach(item => appendMessage(item.text, item.who));
  }

  function appendMessage(text, who = 'bot') {
    const div = document.createElement('div');
    div.className = `msg ${who}`;
    div.textContent = text;
    messagesEl.appendChild(div);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function addToHistory(text, who) {
    const history = loadHistory();
    history.push({ text, who });
    saveHistory(history);
  }

  function setLoading(isLoading) {
    sendBtn.disabled = isLoading;
    inputEl.disabled = isLoading;
    sendBtn.textContent = isLoading ? '...' : 'Kirim';
  }

  async function sendMessage(text) {
    appendMessage(text, 'user');
    addToHistory(text, 'user');
    setLoading(true);
    try {
      const res = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: text, context })
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message || 'Gagal memproses');
      appendMessage(data.reply, 'bot');
      addToHistory(data.reply, 'bot');
    } catch (err) {
      appendMessage('Maaf, terjadi kendala. Coba lagi ya.', 'bot');
      addToHistory('Maaf, terjadi kendala. Coba lagi ya.', 'bot');
    } finally {
      setLoading(false);
    }
  }

  async function quickAddTask() {
    setLoading(true);
    try {
      const res = await fetch(addTaskUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          mata_kuliah: taskFlow.mataKuliah,
          jenis: taskFlow.jenis,
          deadline: taskFlow.deadline
        })
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.message || 'Gagal menambah tugas');
      appendMessage('Tugas berhasil ditambahkan.', 'bot');
      addToHistory('Tugas berhasil ditambahkan.', 'bot');
    } catch (err) {
      appendMessage('Gagal menambah tugas. Coba lagi ya.', 'bot');
      addToHistory('Gagal menambah tugas. Coba lagi ya.', 'bot');
    } finally {
      setLoading(false);
    }
  }

  function startTaskFlow() {
    taskFlow.active = true;
    taskFlow.step = 1;
    taskFlow.mataKuliah = '';
    taskFlow.jenis = '';
    taskFlow.deadline = '';
    appendMessage('Masukkan mata kuliah:', 'bot');
    addToHistory('Masukkan mata kuliah:', 'bot');
  }

  function openModal() {
    modal.classList.add('show');
    modal.setAttribute('aria-hidden', 'false');
    inputEl.focus();
  }
  function closeModal() {
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
  }

  toggle.addEventListener('click', openModal);
  closeBtn.addEventListener('click', closeModal);

  sendBtn.addEventListener('click', () => {
    const text = inputEl.value.trim();
    if (!text) return;
    inputEl.value = '';
    if (taskFlow.active) {
      if (taskFlow.step === 1) {
        taskFlow.mataKuliah = text;
        taskFlow.step = 2;
        appendMessage(text, 'user');
        addToHistory(text, 'user');
        appendMessage('Jenis tugas apa? (contoh: Laporan/Quiz/Praktik)', 'bot');
        addToHistory('Jenis tugas apa? (contoh: Laporan/Quiz/Praktik)', 'bot');
        return;
      }
      if (taskFlow.step === 2) {
        taskFlow.jenis = text;
        taskFlow.step = 3;
        appendMessage(text, 'user');
        addToHistory(text, 'user');
        appendMessage('Deadline (YYYY-MM-DD):', 'bot');
        addToHistory('Deadline (YYYY-MM-DD):', 'bot');
        return;
      }
      if (taskFlow.step === 3) {
        taskFlow.deadline = text;
        taskFlow.active = false;
        taskFlow.step = 0;
        appendMessage(text, 'user');
        addToHistory(text, 'user');
        quickAddTask();
        return;
      }
    }
    if (/^tambah tugas/i.test(text)) {
      startTaskFlow();
      return;
    }
    sendMessage(text);
  });

  inputEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendBtn.click();
    }
  });

  chips.forEach(chip => {
    chip.addEventListener('click', () => {
      const prompt = chip.dataset.prompt || '';
      if (prompt) {
        inputEl.value = '';
        openModal();
        if (/tambah tugas/i.test(prompt)) {
          startTaskFlow();
        } else {
          sendMessage(prompt);
        }
      }
    });
  });

  renderHistory(loadHistory());
})();
