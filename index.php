<?php $appName = "AI Study Assistant"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($appName) ?> - PDF Chatbot</title>
  <script>
    /* Apply saved theme before the page paints (prevents a white flash) */
    (function () {
      try {
        var t = localStorage.getItem("sm_theme");
        if (!t) t = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
        document.documentElement.setAttribute("data-theme", t);
      } catch (e) {
        document.documentElement.setAttribute("data-theme", "light");
      }
    })();
  </script>
  <link rel="icon" type="image/svg+xml" href="favicon.svg">
  <meta name="theme-color" content="#4f46e5">
  <link rel="stylesheet" href="style.css">
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs><linearGradient id="icoGrad" gradientUnits="userSpaceOnUse" x1="2" y1="2" x2="22" y2="22"><stop offset="0" stop-color="#4f7cff"/><stop offset=".55" stop-color="#8b5cf6"/><stop offset="1" stop-color="#d946ef"/></linearGradient><linearGradient id="icoGradLight" gradientUnits="userSpaceOnUse" x1="2" y1="2" x2="22" y2="22"><stop offset="0" stop-color="#a5b4fc"/><stop offset="1" stop-color="#f0abfc"/></linearGradient></defs></svg>
<div class="app">

  <!-- ===== SIDEBAR ===== -->
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <img class="brand-logo" src="logo.svg" alt="AI Study Assistant logo">
      <div class="brand-text">
        <?php $w = explode(' ', $appName, 2); ?>
        <span class="brand-name"><em><?= htmlspecialchars($w[0]) ?></em> <?= htmlspecialchars($w[1] ?? '') ?></span>
        <small class="brand-tag">Smart PDF learning</small>
      </div>
    </div>
    <nav class="menu">
      <button class="nav-btn active" data-view="dashboard"><svg class="ico" viewBox="0 0 24 24"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/></svg>Dashboard</button>
      <button class="nav-btn" data-view="pdfs"><svg class="ico" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></svg>My PDFs</button>
      <button class="nav-btn" data-view="chat"><svg class="ico" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M7 13l3-7 3 7M8.3 10.6h3.4"/><path d="M16.5 6v7"/></svg>Chat with PDF</button>
      <button class="nav-btn" data-view="summary"><svg class="ico" viewBox="0 0 24 24"><path d="M2 5h7a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H2z"/><path d="M22 5h-7a3 3 0 0 0-3 3v13a2 2 0 0 1 2-2h8z"/></svg>Summary & Notes</button>
      <button class="nav-btn" data-view="quiz"><svg class="ico" viewBox="0 0 24 24"><path d="M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2"/><rect x="8.5" y="2" width="7" height="4" rx="1"/><path d="M8 11l1.3 1.3L11.5 10M8 17l1.3 1.3L11.5 16M14 11.5h3M14 17.5h3"/></svg>Quiz & Practice</button>
      <button class="nav-btn" data-view="notes"><svg class="ico" viewBox="0 0 24 24"><path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>Saved Notes</button>
      <button class="nav-btn" data-view="history"><svg class="ico" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg>History</button>
    </nav>
    <div class="sidebar-footer">Learn faster from your own PDFs</div>
  </aside>

  <!-- ===== MAIN ===== -->
  <div class="main">
    <header class="topbar">
      <button class="hamburger" id="hamburger">☰</button>
      <h1 id="pageTitle">Dashboard</h1>
      <div class="topbar-right">
        <button class="btn btn-primary" id="newChatTop">+ New Chat</button>
        <button class="theme-toggle" id="themeToggle" aria-label="Switch theme" title="Switch theme">🌙</button>

        <!-- Shown when logged out -->
        <div class="auth-guest" id="authGuest">
          <button class="btn btn-outline" id="openLogin">Login</button>
          <button class="btn btn-gradient" id="openSignup">Sign Up</button>
        </div>

        <!-- Shown when logged in -->
        <div class="auth-user" id="authUser" hidden>
          <div class="avatar" id="userAvatar">U</div>
          <span class="user-name" id="userName"></span>
          <button class="btn btn-light btn-small" id="logoutBtn">Logout</button>
        </div>
      </div>
    </header>

    <main class="content">

      <!-- DASHBOARD -->
      <section class="view active" id="view-dashboard">
        <div class="welcome">
          <h2 id="welcomeTitle">Welcome to <?= htmlspecialchars($appName) ?> 👋</h2>
          <p id="welcomeText">Upload a PDF and start learning faster.</p>
          <button class="btn btn-white" id="welcomeLogin">Login or Sign Up</button>
        </div>


        <!-- Landing content: only for visitors who are not logged in -->
        <div class="guest-only landing">
          <h3 class="land-title">Everything you need to study smarter</h3>
          <div class="features">
            <div class="feature"><span class="f-ico"><svg class="ico" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 17v-6M9 13.5l3-3 3 3"/></svg></span><h4>Upload your PDF</h4><p>Drag and drop your notes, books or slides. Text is read automatically.</p></div>
            <div class="feature"><span class="f-ico"><svg class="ico" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M7 13l3-7 3 7M8.3 10.6h3.4"/><path d="M16.5 6v7"/></svg></span><h4>Chat with your PDF</h4><p>Ask questions and get simple answers with page numbers.</p></div>
            <div class="feature"><span class="f-ico"><svg class="ico" viewBox="0 0 24 24"><path d="M2 5h7a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H2z"/><path d="M22 5h-7a3 3 0 0 0-3 3v13a2 2 0 0 1 2-2h8z"/></svg></span><h4>AI Summary and Notes</h4><p>Get short notes and important points in seconds. Copy or download them.</p></div>
            <div class="feature"><span class="f-ico"><svg class="ico" viewBox="0 0 24 24"><path d="M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2"/><rect x="8.5" y="2" width="7" height="4" rx="1"/><path d="M8 11l1.3 1.3L11.5 10M8 17l1.3 1.3L11.5 16M14 11.5h3M14 17.5h3"/></svg></span><h4>MCQs and Practice</h4><p>Make quizzes and short questions from your own PDF to test yourself.</p></div>
          </div>

          <h3 class="land-title">How it works</h3>
          <div class="steps-row">
            <div class="step-box"><span class="step-num">1</span><strong>Create a free account</strong><span>Sign up in under a minute.</span></div>
            <div class="step-box"><span class="step-num">2</span><strong>Upload a PDF</strong><span>Your files stay private.</span></div>
            <div class="step-box"><span class="step-num">3</span><strong>Learn faster</strong><span>Chat, summarise and practise.</span></div>
          </div>

          <div class="cta-band">
            <div><strong>Ready to start?</strong><br><span>It is free. Your PDFs, chats and notes stay private to your account.</span></div>
            <button class="btn btn-white" id="ctaSignup">Create Free Account</button>
          </div>
        </div>

        <div class="stats auth-only">
          <div class="stat s-indigo"><span class="stat-ico"><svg class="ico" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></svg></span><div><span class="stat-num" id="statPdfs">0</span><span class="stat-label">PDFs</span></div></div>
          <div class="stat s-violet"><span class="stat-ico"><svg class="ico" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H8l-5 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M7 13l3-7 3 7M8.3 10.6h3.4"/><path d="M16.5 6v7"/></svg></span><div><span class="stat-num" id="statChats">0</span><span class="stat-label">Chats</span></div></div>
          <div class="stat s-amber"><span class="stat-ico"><svg class="ico" viewBox="0 0 24 24"><path d="M2 5h7a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H2z"/><path d="M22 5h-7a3 3 0 0 0-3 3v13a2 2 0 0 1 2-2h8z"/></svg></span><div><span class="stat-num" id="statNotes">0</span><span class="stat-label">Saved Notes</span></div></div>
          <div class="stat s-sky"><span class="stat-ico"><svg class="ico" viewBox="0 0 24 24"><rect x="3.5" y="15" width="4" height="6" rx="1"/><rect x="10" y="12" width="4" height="9" rx="1"/><rect x="16.5" y="9" width="4" height="12" rx="1"/><path d="M3.5 8l5-4 3.5 2.5L20 2M16 2h4v4"/></svg></span><div><span class="stat-num" id="statPages">0</span><span class="stat-label">PDF Pages</span></div></div>
        </div>

        <div class="card auth-only">
          <h3><span class="h-ico"><svg class="ico" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 17v-6M9 13.5l3-3 3 3"/></svg></span> Upload a PDF</h3>
          <div class="dropzone" id="dropzone">
            <div class="drop-icon"><svg class="ico" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 17v-6M9 13.5l3-3 3 3"/></svg></div>
            <p><strong>Drag & drop</strong> your PDF here</p>
            <p class="muted">or</p>
            <button class="btn btn-primary" id="browseBtn">Browse File</button>
            <input type="file" id="fileInput" accept="application/pdf" hidden>
            <p class="muted drop-hint">PDF only • up to 10 MB</p>
          </div>
          <div id="uploadMsg" class="upload-msg"></div>
        </div>

        <div class="card auth-only">
          <h3><span class="h-ico"><svg class="ico" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg></span> Recent PDFs</h3>
          <div id="recentPdfs"></div>
        </div>
      </section>

      <!-- MY PDFS -->
      <section class="view" id="view-pdfs">
        <div class="card">
          <h3><span class="h-ico"><svg class="ico" viewBox="0 0 24 24"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/></svg></span> All uploaded PDFs</h3>
          <div id="allPdfs"></div>
          <div class="output" id="previewBox" hidden></div>
        </div>
      </section>

      <!-- CHAT -->
      <section class="view" id="view-chat">
        <div class="chat-box">
          <div class="chat-head">
            <div>
              <label for="chatPdf" class="muted">Chatting with:</label>
              <select id="chatPdf"></select>
            </div>
            <div class="chat-actions">
              <button class="btn btn-light" id="newChatBtn">New Chat</button>
              <button class="btn btn-light" id="clearChatBtn">Clear Chat</button>
            </div>
          </div>

          <div class="messages" id="messages"></div>

          <div class="chips">
            <button class="chip" data-q="Explain Chapter 2">Explain Chapter 2</button>
            <button class="chip" data-q="Explain this simply with an example">Explain simply</button>
            <button class="chip" data-q="Can you tell me more about this topic?">Ask follow-up</button>
          </div>

          <form class="chat-form" id="chatForm">
            <input type="text" id="chatInput" placeholder="Ask a question about your PDF..." autocomplete="off">
            <button class="btn btn-primary" type="submit">Send</button>
          </form>
        </div>
      </section>

      <!-- SUMMARY -->
      <section class="view" id="view-summary">
        <div class="card">
          <h3><span class="h-ico"><svg class="ico" viewBox="0 0 24 24"><path d="M2 5h7a3 3 0 0 1 3 3v13a2 2 0 0 0-2-2H2z"/><path d="M22 5h-7a3 3 0 0 0-3 3v13a2 2 0 0 1 2-2h8z"/></svg></span> AI Summary & Notes</h3>
          <p class="muted">Pick a PDF and what you want. The AI reads your PDF and writes simple notes with page numbers.</p>
          <div class="row">
            <select id="summaryPdf"></select>
            <select id="summaryMode">
              <option value="full">Full summary and notes</option>
              <option value="points">Important points only</option>
              <option value="difficult">Difficult topics explained simply</option>
            </select>
            <button class="btn btn-primary" id="genSummary">Generate</button>
          </div>
          <div class="output" id="summaryOut">Your summary will appear here.</div>
          <div class="row" id="summaryBtns" hidden>
            <button class="btn btn-light" id="copySummary">Copy</button>
            <button class="btn btn-light" id="downloadSummary">Download</button>
            <button class="btn btn-accent" id="saveSummary">Save Note</button>
          </div>
        </div>
      </section>

      <!-- QUIZ -->
      <section class="view" id="view-quiz">
        <div class="card">
          <h3><span class="h-ico"><svg class="ico" viewBox="0 0 24 24"><path d="M8 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2"/><rect x="8.5" y="2" width="7" height="4" rx="1"/><path d="M8 11l1.3 1.3L11.5 10M8 17l1.3 1.3L11.5 16M14 11.5h3M14 17.5h3"/></svg></span> Quiz & Practice Questions</h3>
          <div class="row">
            <select id="quizPdf"></select>
            <select id="quizCount">
              <option value="5">5 questions</option>
              <option value="10">10 questions</option>
            </select>
            <button class="btn btn-primary" id="startQuiz">Start MCQ Quiz</button>
            <button class="btn btn-light" id="showShort">Short Questions</button>
          </div>
          <div class="output" id="quizArea">Choose an option above to begin.</div>
        </div>
      </section>

      <!-- SAVED NOTES -->
      <section class="view" id="view-notes">
        <div class="card">
          <h3><span class="h-ico"><svg class="ico" viewBox="0 0 24 24"><path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg></span> Saved Notes</h3>
          <div id="notesList"></div>
        </div>
      </section>

      <!-- HISTORY -->
      <section class="view" id="view-history">
        <div class="card">
          <h3><span class="h-ico"><svg class="ico" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg></span> Chat History</h3>
          <div id="historyList"></div>
        </div>
      </section>

    </main>
  </div>
</div>

<!-- ===== LOGIN / SIGN UP MODAL ===== -->
<div class="modal" id="authModal" hidden>
  <div class="modal-box">
    <button class="modal-close" id="authClose" aria-label="Close">✕</button>
    <img class="modal-logo" src="logo.svg" alt="AI Study Assistant logo">

    <div class="tabs">
      <button class="tab active" data-tab="login" type="button">Login</button>
      <button class="tab" data-tab="signup" type="button">Sign Up</button>
    </div>

    <form class="auth-form" id="loginForm" novalidate>
      <label for="loginEmail">Email</label>
      <input type="email" id="loginEmail" placeholder="you@example.com" autocomplete="email">
      <label for="loginPass">Password</label>
      <div class="pass-wrap"><input type="password" id="loginPass" placeholder="Your password" autocomplete="current-password"><button type="button" class="eye-btn" aria-label="Show password" title="Show password"><svg class="eye-on" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-off" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a19.8 19.8 0 0 1 5.06-5.94M9.9 4.24A10.9 10.9 0 0 1 12 5c7 0 11 7 11 7a19.9 19.9 0 0 1-3.17 4.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button></div>
      <div class="form-error" id="loginError"></div>
      <button class="btn btn-primary btn-block" type="submit">Login</button>
    </form>

    <form class="auth-form" id="signupForm" novalidate hidden>
      <label for="signName">Full Name</label>
      <input type="text" id="signName" name="sa_fullname_x" placeholder="Your name (letters only)" autocomplete="off" autocapitalize="words" spellcheck="false" maxlength="60">
      <label for="signEmail">Email</label>
      <input type="email" id="signEmail" placeholder="you@example.com" autocomplete="off">
      <label for="signPass">Password (min 8 characters)</label>
      <div class="pass-wrap"><input type="password" id="signPass" placeholder="Create a password" autocomplete="new-password"><button type="button" class="eye-btn" aria-label="Show password" title="Show password"><svg class="eye-on" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-off" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a19.8 19.8 0 0 1 5.06-5.94M9.9 4.24A10.9 10.9 0 0 1 12 5c7 0 11 7 11 7a19.9 19.9 0 0 1-3.17 4.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button></div>
      <label for="signPass2">Confirm Password</label>
      <div class="pass-wrap"><input type="password" id="signPass2" placeholder="Repeat the password" autocomplete="new-password"><button type="button" class="eye-btn" aria-label="Show password" title="Show password"><svg class="eye-on" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-off" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a19.8 19.8 0 0 1 5.06-5.94M9.9 4.24A10.9 10.9 0 0 1 12 5c7 0 11 7 11 7a19.9 19.9 0 0 1-3.17 4.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg></button></div>
      <div class="form-error" id="signupError"></div>
      <button class="btn btn-primary btn-block" type="submit">Create Account</button>
    </form>

    <p class="muted demo-note">Your password is never saved as plain text. Only a secure hash is stored in the database.</p>
  </div>
</div>

<script src="script.js"></script>
<script>
  /* Safety check: if script.js did not load, buttons cannot work. Tell the user clearly. */
  if (typeof showView === "undefined") {
    document.body.insertAdjacentHTML("afterbegin",
      '<div style="position:fixed;top:0;left:0;right:0;z-index:999;background:#b91c1c;color:#fff;padding:12px 16px;font:600 15px Arial">' +
      'script.js did not load correctly. Make sure script.js is in the SAME folder as index.php, then press Ctrl + F5.</div>');
  }
</script>
</body>
</html>
