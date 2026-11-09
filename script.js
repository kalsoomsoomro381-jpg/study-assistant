/* =========================================================
   AI Study Assistant - front-end
   Real: PDF upload, text extraction, chat with PDF, chat history,
   summary, quiz (MCQs and short questions), saved notes
   ========================================================= */

// ---------- Fake data ----------
let pdfs = [];   // loaded from the server (api/documents.php)

let notes = [];          // loaded from the server (api/notes.php)
let chatHistory = [];    // loaded from the server (api/chats.php)
let currentChatId = null;

// ---------- Session expired? (server answers 401) ----------
const _fetch = window.fetch.bind(window);
window.fetch = async (...args) => {
  const res = await _fetch(...args);
  if (res.status === 401 && currentUser) onLoggedOut(true);
  return res;
};

// ---------- Helpers ----------
const $ = (id) => document.getElementById(id);
const qsa = (sel) => document.querySelectorAll(sel);

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

function formatSize(bytes) {
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
  return (bytes / (1024 * 1024)).toFixed(1) + " MB";
}

// ---------- Navigation ----------
const titles = {
  dashboard: "Dashboard",
  pdfs: "My PDFs",
  chat: "Chat with PDF",
  summary: "Summary & Notes",
  quiz: "Quiz & Practice",
  notes: "Saved Notes",
  history: "History"
};

function showView(name) {
  // without login only the Dashboard (with the welcome card) is available
  if (!currentUser && name !== "dashboard") {
    openAuth("login");
    name = "dashboard";
  }
  qsa(".view").forEach((v) => v.classList.remove("active"));
  $("view-" + name).classList.add("active");
  qsa(".nav-btn").forEach((b) => b.classList.toggle("active", b.dataset.view === name));
  $("pageTitle").textContent = titles[name];
  $("sidebar").classList.remove("open");
  renderAll();
}

qsa(".nav-btn").forEach((btn) => {
  btn.addEventListener("click", () => showView(btn.dataset.view));
});
$("hamburger").addEventListener("click", () => $("sidebar").classList.toggle("open"));

// ---------- Render ----------
function fileItemHtml(p, withDelete) {
  return `
    <div class="file-item">
      <div class="file-icon"><svg class="ico" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/></svg></div>
      <div class="file-info">
        <div class="file-name">${escapeHtml(p.name)}</div>
        <div class="muted">${p.size}${p.pages ? " • " + p.pages + " pages" : ""}</div>
      </div>
      <span class="badge ${p.status === "Ready" ? "" : "uploading"}">${p.status}</span>
      ${withDelete ? `<button class="btn btn-small btn-light" data-prev="${p.id}">Preview text</button><button class="btn btn-small btn-danger" data-del="${p.id}">Delete</button>` : ""}
    </div>`;
}

function renderPdfs() {
  $("recentPdfs").innerHTML = pdfs.length
    ? pdfs.slice(-3).reverse().map((p) => fileItemHtml(p, false)).join("")
    : `<div class="empty">No PDFs yet.</div>`;

  $("allPdfs").innerHTML = pdfs.length
    ? pdfs.map((p) => fileItemHtml(p, true)).join("")
    : `<div class="empty">No PDFs yet.</div>`;

  qsa("[data-del]").forEach((b) =>
    b.addEventListener("click", async () => {
      if (!confirm("Delete this PDF?")) return;
      const fd = new FormData();
      fd.append("id", b.dataset.del);
      try {
        const res = await fetch("api/delete.php", { method: "POST", body: fd });
        const data = await res.json();
        if (!data.ok) { alert(data.error || "Could not delete."); return; }
        $("uploadMsg").innerHTML = "";
        await loadPdfs();
      } catch (e) { alert("Server not reachable. Is Apache running?"); }
    })
  );

  qsa("[data-prev]").forEach((b) =>
    b.addEventListener("click", async () => {
      const box = $("previewBox");
      box.hidden = false;
      box.textContent = "Loading...";
      try {
        const res = await fetch("api/preview.php?id=" + b.dataset.prev);
        const data = await res.json();
        if (!data.ok) { box.textContent = data.error; return; }
        box.textContent = data.pages.length
          ? data.pages.map((p) => "PAGE " + p.page + "\n" + (p.text || "(no text on this page)")).join("\n\n----------\n\n")
          : "No text was extracted from this PDF.";
      } catch (e) { box.textContent = "Server not reachable."; }
    })
  );

  const options = pdfs.length
    ? pdfs.map((p) => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join("")
    : `<option value="">No PDF uploaded</option>`;
  ["chatPdf", "summaryPdf", "quizPdf"].forEach((id) => {
    const sel = $(id);
    const old = sel.value;
    sel.innerHTML = options;
    if (old) sel.value = old;
  });
}

function renderNotes() {
  $("notesList").innerHTML = notes.length
    ? notes.map((n) => `
        <div class="list-item note-item">
          <div class="note-main">
            <strong>${escapeHtml(n.title)}</strong>
            <div class="muted">${escapeHtml(n.type)} • ${formatDate(n.date)}</div>
            <div class="note-body" id="note-body-${n.id}">${formatAnswer(n.content.length > 260 ? n.content.slice(0, 260) + "..." : n.content)}</div>
          </div>
          <div class="row" style="margin:0">
            ${n.content.length > 260 ? `<button class="btn btn-small btn-light" data-toggle-note="${n.id}">Show more</button>` : ""}
            <button class="btn btn-small btn-light" data-copy-note="${n.id}">Copy</button>
            <button class="btn btn-small btn-light" data-dl-note="${n.id}">Download</button>
            <button class="btn btn-small btn-danger" data-del-note="${n.id}">Delete</button>
          </div>
        </div>`).join("")
    : `<div class="empty">No saved notes yet. Generate a summary or quiz and press "Save Note".</div>`;

  const find = (id) => notes.find((x) => x.id === Number(id));

  qsa("[data-toggle-note]").forEach((b) =>
    b.addEventListener("click", () => {
      const n = find(b.dataset.toggleNote);
      const box = $("note-body-" + n.id);
      const open = b.textContent === "Show less";
      box.innerHTML = formatAnswer(open && n.content.length > 260 ? n.content.slice(0, 260) + "..." : n.content);
      b.textContent = open ? "Show more" : "Show less";
    })
  );
  qsa("[data-copy-note]").forEach((b) => b.addEventListener("click", () => copyText(find(b.dataset.copyNote).content, b)));
  qsa("[data-dl-note]").forEach((b) =>
    b.addEventListener("click", () => {
      const n = find(b.dataset.dlNote);
      downloadText(safeFileName(n.title) + ".txt", n.title + "\n\n" + n.content);
    })
  );
  qsa("[data-del-note]").forEach((b) =>
    b.addEventListener("click", async () => {
      if (!confirm("Delete this note?")) return;
      const fd = new FormData();
      fd.append("action", "delete");
      fd.append("id", b.dataset.delNote);
      try { await fetch("api/notes.php", { method: "POST", body: fd }); } catch (e) {}
      await loadNotes();
    })
  );
}

function safeFileName(t) {
  return String(t).replace(/[^\w\- ]+/g, "").trim().replace(/\s+/g, "-").slice(0, 60) || "note";
}

async function loadNotes() {
  try {
    const res = await fetch("api/notes.php");
    const data = await res.json();
    notes = data.ok ? data.notes : [];
  } catch (e) {
    notes = [];
  }
  renderNotes();
  renderStats();
}

function renderHistory() {
  $("historyList").innerHTML = chatHistory.length
    ? chatHistory.map((h) => `
        <div class="list-item">
          <div>
            <strong>${escapeHtml(h.title)}</strong>
            <div class="muted">${escapeHtml(h.pdf)} • ${formatDate(h.date)}</div>
          </div>
          <div class="row" style="margin:0">
            <button class="btn btn-small btn-light" data-open-chat="${h.id}">Open</button>
            <button class="btn btn-small btn-danger" data-del-chat="${h.id}">Delete</button>
          </div>
        </div>`).join("")
    : `<div class="empty">No chats yet. Ask a question about your PDF and it will be saved here.</div>`;

  qsa("[data-open-chat]").forEach((b) => b.addEventListener("click", () => openChat(Number(b.dataset.openChat))));
  qsa("[data-del-chat]").forEach((b) =>
    b.addEventListener("click", async () => {
      if (!confirm("Delete this chat?")) return;
      const fd = new FormData();
      fd.append("action", "delete");
      fd.append("id", b.dataset.delChat);
      try {
        await fetch("api/chats.php", { method: "POST", body: fd });
        if (currentChatId === Number(b.dataset.delChat)) startNewChat();
        await loadChats();
      } catch (e) { alert("Server not reachable."); }
    })
  );
}

function formatDate(d) {
  if (!d) return "";
  const dt = new Date(String(d).replace(" ", "T"));
  return isNaN(dt) ? "" : dt.toLocaleDateString();
}

async function loadChats() {
  try {
    const res = await fetch("api/chats.php");
    const data = await res.json();
    chatHistory = data.ok ? data.chats : [];
  } catch (e) {
    chatHistory = [];
  }
  renderHistory();
  renderStats();
}

function renderStats() {
  $("statPdfs").textContent = pdfs.length;
  $("statChats").textContent = chatHistory.length;
  $("statNotes").textContent = notes.length;
  $("statPages").textContent = pdfs.reduce((sum, p) => sum + (p.pages || 0), 0);
}

function renderAll() {
  renderPdfs();
  renderNotes();
  renderHistory();
  renderStats();
}

// ---------- Copy / download ----------
function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const old = btn.textContent;
    btn.textContent = "Copied!";
    setTimeout(() => (btn.textContent = old), 1200);
  });
}

function downloadText(filename, text) {
  const blob = new Blob([text], { type: "text/plain" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

// ---------- Upload (fake progress) ----------
const dropzone = $("dropzone");
const fileInput = $("fileInput");

$("browseBtn").addEventListener("click", () => fileInput.click());
fileInput.addEventListener("change", () => handleFile(fileInput.files[0]));

["dragenter", "dragover"].forEach((evt) =>
  dropzone.addEventListener(evt, (e) => {
    e.preventDefault();
    dropzone.classList.add("dragover");
  })
);
["dragleave", "drop"].forEach((evt) =>
  dropzone.addEventListener(evt, (e) => {
    e.preventDefault();
    dropzone.classList.remove("dragover");
  })
);
dropzone.addEventListener("drop", (e) => handleFile(e.dataTransfer.files[0]));

async function loadPdfs() {
  try {
    const res = await fetch("api/documents.php");
    const data = await res.json();
    pdfs = data.ok ? data.documents : [];
  } catch (e) {
    pdfs = [];
  }
  renderAll();
}

function handleFile(file) {
  const msg = $("uploadMsg");
  if (!file) return;

  if (file.name.toLowerCase().slice(-4) !== ".pdf") {
    msg.innerHTML = `<span class="error-text">Please upload a PDF file only.</span>`;
    return;
  }
  if (file.size > 10 * 1024 * 1024) {
    msg.innerHTML = `<span class="error-text">File is too big. Maximum size is 10 MB.</span>`;
    return;
  }

  msg.innerHTML = `
    <div class="file-item">
      <div class="file-icon"><svg class="ico" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/></svg></div>
      <div class="file-info">
        <div class="file-name">${escapeHtml(file.name)}</div>
        <div class="muted">${formatSize(file.size)}</div>
        <div class="progress"><div class="progress-bar" id="pBar"></div></div>
      </div>
      <span class="badge uploading" id="pBadge">Uploading... 0%</span>
    </div>`;

  const fd = new FormData();
  fd.append("pdf", file);

  const xhr = new XMLHttpRequest();
  xhr.open("POST", "api/upload.php");

  xhr.upload.onprogress = (e) => {
    if (!e.lengthComputable) return;
    const percent = Math.round((e.loaded / e.total) * 100);
    $("pBar").style.width = percent + "%";
    $("pBadge").textContent = "Uploading... " + percent + "%";
  };

  xhr.onload = () => {
    if (xhr.status === 401 && currentUser) onLoggedOut(true);
    let data = null;
    try { data = JSON.parse(xhr.responseText); } catch (e) {}
    if (data && data.ok) {
      $("pBar").style.width = "100%";
      $("pBadge").textContent = data.document.status;
      $("pBadge").classList.remove("uploading");
      loadPdfs();
      // the PDF now shows under "Recent PDFs", so remove this progress box after a moment
      setTimeout(() => { if (msg.querySelector("#pBar")) msg.innerHTML = ""; }, 2500);
    } else {
      const why = data && data.error ? data.error : "Server error. Check that Apache and MySQL are running.";
      msg.innerHTML = `<span class="error-text">${escapeHtml(why)}</span>`;
    }
  };
  xhr.onerror = () => {
    msg.innerHTML = `<span class="error-text">Server not reachable. Open the site from http://localhost/study-assistant/ and start Apache.</span>`;
  };

  xhr.send(fd);
  fileInput.value = "";
}

// ---------- Chat (real AI) ----------
const messagesBox = $("messages");
let chatBusy = false;

// show **bold** and bullet points nicely, but stay safe (escape first)
function formatAnswer(text) {
  return escapeHtml(text)
    .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>")
    .replace(/^[ \t]*[*-] /gm, "• ");
}

function addMessage(role, text, pages) {
  const div = document.createElement("div");
  div.className = "msg " + role;
  div.innerHTML = `<div class="msg-text">${role === "ai" ? formatAnswer(text) : escapeHtml(text)}</div>`;

  if (role === "ai" && pages && pages.length) {
    const refs = document.createElement("div");
    refs.className = "page-refs";
    refs.innerHTML = `<span class="muted">Source:</span> ` +
      pages.map((p) => `<span class="page-badge">Page ${p}</span>`).join("");
    div.appendChild(refs);
  }

  if (role === "ai") {
    const actions = document.createElement("div");
    actions.className = "msg-actions";
    actions.innerHTML = `
      <button class="btn btn-small btn-light">Copy</button>
      <button class="btn btn-small btn-light">Download</button>`;
    const [copyBtn, dlBtn] = actions.querySelectorAll("button");
    copyBtn.addEventListener("click", () => copyText(text, copyBtn));
    dlBtn.addEventListener("click", () => downloadText("answer.txt", text));
    div.appendChild(actions);
  }

  messagesBox.appendChild(div);
  messagesBox.scrollTop = messagesBox.scrollHeight;
  return div;
}

function addErrorMessage(text) {
  const div = document.createElement("div");
  div.className = "msg ai error";
  div.textContent = text;
  messagesBox.appendChild(div);
  messagesBox.scrollTop = messagesBox.scrollHeight;
}

function setChatBusy(busy) {
  chatBusy = busy;
  $("chatInput").disabled = busy;
  $("chatForm").querySelector("button").disabled = busy;
  if (!busy) $("chatInput").focus();
}

async function sendQuestion(question) {
  question = question.trim();
  if (!question || chatBusy) return;

  const docId = $("chatPdf").value;
  if (!pdfs.length || !docId) {
    addMessage("ai", "Please upload a PDF first from the Dashboard.", []);
    return;
  }

  addMessage("user", question);
  const typing = addMessage("ai", "Reading your PDF and thinking...", []);
  typing.querySelector(".msg-text").classList.add("typing");
  typing.querySelector(".msg-actions").remove();
  setChatBusy(true);

  try {
    const fd = new FormData();
    fd.append("document_id", docId);
    fd.append("question", question);
    if (currentChatId) fd.append("chat_id", currentChatId);

    const res = await fetch("api/chat.php", { method: "POST", body: fd });
    let data = null;
    try { data = await res.json(); } catch (e) {}
    typing.remove();

    if (data && data.ok) {
      currentChatId = data.chat_id;
      addMessage("ai", data.answer, data.pages);
      loadChats();
    } else {
      addErrorMessage((data && data.error) || "Server error. Check that Apache and MySQL are running.");
    }
  } catch (e) {
    typing.remove();
    addErrorMessage("Server not reachable. Open the site from http://localhost/study-assistant/ and start Apache.");
  } finally {
    setChatBusy(false);
  }
}

$("chatForm").addEventListener("submit", (e) => {
  e.preventDefault();
  const input = $("chatInput");
  const q = input.value;
  input.value = "";
  sendQuestion(q);
});

qsa(".chip").forEach((c) => c.addEventListener("click", () => sendQuestion(c.dataset.q)));

function greeting() {
  const who = currentUser ? currentUser.name.split(" ")[0] : "there";
  return `Hi ${who}! Ask me anything about your PDF. I will show page numbers with my answers.`;
}

// New chat: nothing is saved until you ask the first question
function startNewChat() {
  currentChatId = null;
  messagesBox.innerHTML = "";
  addMessage("ai", greeting(), []);
}

// choosing another PDF starts a fresh chat
$("chatPdf").addEventListener("change", () => { if (currentChatId) startNewChat(); });

async function openChat(id) {
  try {
    const res = await fetch("api/chats.php?id=" + id);
    const data = await res.json();
    if (!data.ok) { alert(data.error || "Could not open the chat."); return; }

    currentChatId = data.chat.id;
    if (data.chat.document_id && pdfs.some((p) => p.id === data.chat.document_id)) {
      $("chatPdf").value = String(data.chat.document_id);
    }
    messagesBox.innerHTML = "";
    data.messages.forEach((m) => addMessage(m.role, m.content, m.pages));
    if (!data.messages.length) addMessage("ai", greeting(), []);
    showView("chat");
  } catch (e) { alert("Server not reachable."); }
}

$("newChatBtn").addEventListener("click", () => { startNewChat(); showView("chat"); });
$("newChatTop").addEventListener("click", () => { startNewChat(); showView("chat"); });
$("clearChatBtn").addEventListener("click", async () => {
  if (currentChatId) {
    const fd = new FormData();
    fd.append("action", "clear");
    fd.append("id", currentChatId);
    try { await fetch("api/chats.php", { method: "POST", body: fd }); } catch (e) {}
  }
  messagesBox.innerHTML = "";
  addMessage("ai", greeting(), []);
});

// ---------- Helpers for the AI pages (summary, quiz) ----------
async function callAI(url, fields) {
  const fd = new FormData();
  Object.keys(fields).forEach((k) => fd.append(k, fields[k]));
  try {
    const res = await fetch(url, { method: "POST", body: fd });
    let data = null;
    try { data = await res.json(); } catch (e) {}
    return data || { ok: false, error: "Server error. Check that Apache and MySQL are running." };
  } catch (e) {
    return { ok: false, error: "Server not reachable. Open the site from http://localhost/study-assistant/ and start Apache." };
  }
}

async function saveNote(title, content, type, docId, btn) {
  const data = await callAI("api/notes.php", { action: "save", title, content, type, document_id: docId || 0 });
  const old = btn.textContent;
  btn.textContent = data.ok ? "Saved!" : "Could not save";
  setTimeout(() => (btn.textContent = old), 1500);
  if (data.ok) loadNotes();
}

function pageBadge(p) {
  return p ? ` <span class="page-badge">Page ${p}</span>` : "";
}

// ---------- Summary ----------
let summary = { text: "", title: "", docId: 0 };

$("genSummary").addEventListener("click", async () => {
  const docId = $("summaryPdf").value;
  if (!pdfs.length || !docId) { $("summaryOut").textContent = "Please upload a PDF first."; return; }

  const btn = $("genSummary");
  btn.disabled = true;
  $("summaryBtns").hidden = true;
  $("summaryOut").innerHTML = `<span class="typing">Reading your PDF and writing notes... this can take 10 to 40 seconds.</span>`;

  const data = await callAI("api/summary.php", { document_id: docId, mode: $("summaryMode").value });
  btn.disabled = false;

  if (!data.ok) {
    $("summaryOut").innerHTML = `<span class="error-text">${escapeHtml(data.error)}</span>`;
    return;
  }
  summary = { text: data.summary, title: data.title, docId };
  const warn = data.truncated
    ? `\n\n<span class="muted">Note: this PDF is very long, so only the first ${data.pages_used} of ${data.total_pages} pages were used.</span>`
    : "";
  $("summaryOut").innerHTML = formatAnswer(data.summary) + warn;
  $("summaryBtns").hidden = false;
});
$("copySummary").addEventListener("click", (e) => copyText(summary.text, e.target));
$("downloadSummary").addEventListener("click", () => downloadText(safeFileName(summary.title) + ".txt", summary.title + "\n\n" + summary.text));
$("saveSummary").addEventListener("click", (e) => saveNote(summary.title, summary.text, "summary", summary.docId, e.target));

// ---------- Quiz ----------
let quiz = { questions: [], index: 0, score: 0, title: "", docId: 0 };

function quizToText(q) {
  return q.title + "\n\n" + q.questions.map((x, i) =>
    `${i + 1}. ${x.question}\n` +
    (x.options ? x.options.map((o, j) => `   ${"ABCD"[j]}) ${o}`).join("\n") + `\n   Answer: ${"ABCD"[x.correct]}) ${x.options[x.correct]}\n   ${x.explanation || ""}` : `   Answer: ${x.answer}`) +
    (x.page ? ` (Page ${x.page})` : "")
  ).join("\n\n");
}

async function fetchQuiz(type) {
  const docId = $("quizPdf").value;
  if (!pdfs.length || !docId) { $("quizArea").textContent = "Please upload a PDF first."; return null; }

  $("startQuiz").disabled = true;
  $("showShort").disabled = true;
  $("quizArea").innerHTML = `<span class="typing">Writing questions from your PDF... this can take 10 to 40 seconds.</span>`;

  const data = await callAI("api/quiz.php", { document_id: docId, type, count: $("quizCount").value });
  $("startQuiz").disabled = false;
  $("showShort").disabled = false;

  if (!data.ok) {
    $("quizArea").innerHTML = `<span class="error-text">${escapeHtml(data.error)}</span>`;
    return null;
  }
  quiz = { questions: data.questions, index: 0, score: 0, title: data.title, docId };
  return data;
}

$("startQuiz").addEventListener("click", async () => {
  if (await fetchQuiz("mcq")) showQuestion();
});

function showQuestion() {
  const area = $("quizArea");
  const total = quiz.questions.length;

  if (quiz.index >= total) {
    const pct = Math.round((quiz.score / total) * 100);
    area.innerHTML = `
      <div class="quiz-q">Quiz finished! 🎉</div>
      <p>Your score: <strong>${quiz.score} / ${total}</strong> (${pct}%)</p>
      <div class="row">
        <button class="btn btn-primary" id="quizAgain">New questions</button>
        <button class="btn btn-accent" id="quizSave">Save Questions as Note</button>
      </div>`;
    $("quizAgain").addEventListener("click", async () => { if (await fetchQuiz("mcq")) showQuestion(); });
    $("quizSave").addEventListener("click", (e) => saveNote(quiz.title, quizToText(quiz), "quiz", quiz.docId, e.target));
    return;
  }

  const item = quiz.questions[quiz.index];
  area.innerHTML = `
    <div class="muted">Question ${quiz.index + 1} of ${total}</div>
    <div class="quiz-q">${escapeHtml(item.question)}</div>
    ${item.options.map((o, i) => `<button class="option" data-i="${i}">${escapeHtml(o)}</button>`).join("")}
    <div class="explain" id="explain"></div>`;

  area.querySelectorAll(".option").forEach((btn) =>
    btn.addEventListener("click", () => {
      const chosen = Number(btn.dataset.i);
      const buttons = area.querySelectorAll(".option");
      buttons.forEach((b) => (b.disabled = true));
      buttons[item.correct].classList.add("correct");
      if (chosen === item.correct) quiz.score++;
      else btn.classList.add("wrong");
      $("explain").innerHTML = escapeHtml(item.explanation || "") + pageBadge(item.page) +
        `<br><button class="btn btn-small btn-primary" id="nextQ" style="margin-top:10px">${quiz.index + 1 === total ? "See score" : "Next"}</button>`;
      $("nextQ").addEventListener("click", () => { quiz.index++; showQuestion(); });
    })
  );
}

$("showShort").addEventListener("click", async () => {
  if (!(await fetchQuiz("short"))) return;

  $("quizArea").innerHTML = quiz.questions.map((s, i) => `
    <div style="margin-bottom:16px">
      <div class="quiz-q">${i + 1}. ${escapeHtml(s.question)}</div>
      <button class="btn btn-small btn-light" data-show="${i}">Show answer</button>
      <div class="explain" id="ans-${i}" hidden>${escapeHtml(s.answer)}${pageBadge(s.page)}</div>
    </div>`).join("") + `
    <div class="row">
      <button class="btn btn-light" id="shortCopy">Copy</button>
      <button class="btn btn-light" id="shortDl">Download</button>
      <button class="btn btn-accent" id="shortSave">Save Questions as Note</button>
    </div>`;

  qsa("[data-show]").forEach((b) =>
    b.addEventListener("click", () => {
      const box = $("ans-" + b.dataset.show);
      box.hidden = !box.hidden;
      b.textContent = box.hidden ? "Show answer" : "Hide answer";
    })
  );
  $("shortCopy").addEventListener("click", (e) => copyText(quizToText(quiz), e.target));
  $("shortDl").addEventListener("click", () => downloadText(safeFileName(quiz.title) + ".txt", quizToText(quiz)));
  $("shortSave").addEventListener("click", (e) => saveNote(quiz.title, quizToText(quiz), "quiz", quiz.docId, e.target));
});

// =========================================================
// THEME: dark / light (saved in localStorage)
// =========================================================
const themeBtn = $("themeToggle");

function applyTheme(theme) {
  document.documentElement.setAttribute("data-theme", theme);
  // In dark mode show the sun (click = go light), in light mode show the moon (click = go dark)
  themeBtn.textContent = theme === "dark" ? "☀️" : "🌙";
  themeBtn.title = theme === "dark" ? "Switch to light mode" : "Switch to dark mode";
  themeBtn.setAttribute("aria-label", themeBtn.title);
}

themeBtn.addEventListener("click", () => {
  const next = document.documentElement.getAttribute("data-theme") === "dark" ? "light" : "dark";
  applyTheme(next);
  try { localStorage.setItem("sm_theme", next); } catch (e) {}
});

applyTheme(document.documentElement.getAttribute("data-theme") || "light");

// =========================================================
// LOGIN / SIGN UP (real: PHP sessions + MySQL, see api/auth.php)
// =========================================================
let currentUser = null;

function renderAuth() {
  const loggedIn = !!currentUser;
  document.body.classList.toggle("logged-out", !loggedIn);
  $("authGuest").hidden = loggedIn;
  $("authUser").hidden = !loggedIn;
  $("welcomeLogin").hidden = loggedIn;

  if (loggedIn) {
    $("userName").textContent = currentUser.name;
    $("userAvatar").textContent = currentUser.name.trim().charAt(0).toUpperCase();
    $("welcomeTitle").textContent = "Welcome back, " + currentUser.name.split(" ")[0] + " 👋";
    $("welcomeText").textContent = "Upload a PDF and start learning faster.";
  } else {
    $("welcomeTitle").textContent = "Welcome to AI Study Assistant 👋";
    $("welcomeText").textContent = "Login or create a free account to upload PDFs, chat with them and save your notes.";
  }
}

function openAuth(tab) {
  switchTab(tab);
  $("loginError").textContent = "";
  $("signupError").textContent = "";
  $("authModal").hidden = false;
}
function closeAuth() { $("authModal").hidden = true; }

function switchTab(tab) {
  qsa(".tab").forEach((t) => t.classList.toggle("active", t.dataset.tab === tab));
  $("loginForm").hidden = tab !== "login";
  $("signupForm").hidden = tab !== "signup";
}

// after login / sign up: load this user's own data
function onLoggedIn(user) {
  currentUser = user;
  closeAuth();
  renderAuth();
  startNewChat();
  loadPdfs();
  loadChats();
  loadNotes();
  showView("dashboard");
}

// after logout (or when the session ended): forget everything on screen
function onLoggedOut(expired) {
  currentUser = null;
  pdfs = [];
  notes = [];
  chatHistory = [];
  currentChatId = null;
  renderAuth();
  startNewChat();
  showView("dashboard");
  if (expired) {
    openAuth("login");
    $("loginError").textContent = "Your session ended. Please login again.";
  }
}

qsa(".tab").forEach((t) => t.addEventListener("click", () => switchTab(t.dataset.tab)));
$("openLogin").addEventListener("click", () => openAuth("login"));
$("openSignup").addEventListener("click", () => openAuth("signup"));
$("welcomeLogin").addEventListener("click", () => openAuth("login"));
$("ctaSignup").addEventListener("click", () => openAuth("signup"));
$("authClose").addEventListener("click", closeAuth);
$("authModal").addEventListener("click", (e) => { if (e.target.id === "authModal") closeAuth(); });
document.addEventListener("keydown", (e) => { if (e.key === "Escape") closeAuth(); });

// name: letters only (numbers and symbols are removed while typing)
const NAME_BAD = /[^\p{L}\p{M} .'\-]/gu;
$("signName").addEventListener("input", (e) => {
  const clean = e.target.value.replace(NAME_BAD, "");
  if (clean !== e.target.value) e.target.value = clean;
});

// eye button: show / hide password
qsa(".eye-btn").forEach((btn) => {
  btn.addEventListener("click", () => {
    const input = btn.parentElement.querySelector("input");
    const show = input.type === "password";
    input.type = show ? "text" : "password";
    btn.classList.toggle("showing", show);
    btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
    btn.title = show ? "Hide password" : "Show password";
  });
});

$("signupForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const name = $("signName").value.trim();
  const email = $("signEmail").value.trim().toLowerCase();
  const pass = $("signPass").value;
  const pass2 = $("signPass2").value;
  const err = $("signupError");

  if (name.length < 2 || NAME_BAD.test(name)) { NAME_BAD.lastIndex = 0; err.textContent = "Name can contain letters only (no numbers)."; return; }
  NAME_BAD.lastIndex = 0;
  if (!/^\S+@\S+\.\S+$/.test(email)) { err.textContent = "Please enter a valid email."; return; }
  if (pass.length < 8) { err.textContent = "Password must be at least 8 characters."; return; }
  if (pass !== pass2) { err.textContent = "Passwords do not match."; return; }

  const btn = e.target.querySelector("button[type=submit]");
  btn.disabled = true;
  const data = await callAI("api/auth.php", { action: "register", name, email, password: pass });
  btn.disabled = false;

  if (!data.ok) { err.textContent = data.error || "Could not create the account."; return; }
  $("signupForm").reset();
  onLoggedIn(data.user);
});

$("loginForm").addEventListener("submit", async (e) => {
  e.preventDefault();
  const email = $("loginEmail").value.trim().toLowerCase();
  const pass = $("loginPass").value;
  const err = $("loginError");

  if (!email || !pass) { err.textContent = "Please enter email and password."; return; }

  const btn = e.target.querySelector("button[type=submit]");
  btn.disabled = true;
  const data = await callAI("api/auth.php", { action: "login", email, password: pass });
  btn.disabled = false;

  if (!data.ok) { err.textContent = data.error || "Could not login."; return; }
  $("loginForm").reset();
  onLoggedIn(data.user);
});

$("logoutBtn").addEventListener("click", async () => {
  await callAI("api/auth.php", { action: "logout" });
  onLoggedOut(false);
});

// is there a login already (cookie from an earlier visit)?
async function restoreSession() {
  try {
    const res = await fetch("api/auth.php?action=me");
    const data = await res.json();
    if (data.ok && data.user) currentUser = data.user;
  } catch (e) {}
}

// ---------- Start ----------
(async function start() {
  await restoreSession();
  renderAuth();
  addMessage("ai", greeting(), []);
  renderAll();
  if (currentUser) {
    loadPdfs();
    loadChats();
    loadNotes();
  }
})();
