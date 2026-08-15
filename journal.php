<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];

$user = [
    'name'     => $_SESSION['user_name'],
    'initials' => strtoupper(substr(explode(' ', $_SESSION['user_name'])[0], 0, 1) . substr(explode(' ', $_SESSION['user_name'])[1] ?? '', 0, 1)),
    'role'     => 'Personal'
];


$DB_HOST = 'localhost';
$DB_NAME = 'syncube';   // <-- change to your actual database name
$DB_USER = 'root';         // <-- change to your actual DB username
$DB_PASS = '';             // <-- change to your actual DB password

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Per-user upload folders so files never collide between accounts
$entry_upload_dir   = 'uploads/' . $user_id . '/entries';
$gallery_upload_dir = 'uploads/' . $user_id . '/gallery';
if (!is_dir($entry_upload_dir))   mkdir($entry_upload_dir, 0777, true);
if (!is_dir($gallery_upload_dir)) mkdir($gallery_upload_dir, 0777, true);

$redirect_hash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $form_type = $_POST['form_type'] ?? '';

    // ── 1. NEW JOURNAL ENTRY ───────────────────────────────────────
    if ($form_type === 'journal_entry' && isset($_POST['title'], $_POST['content'], $_POST['mood'])) {

        $title   = trim($_POST['title']);
        $content = trim($_POST['content']);
        $mood    = trim($_POST['mood']);

        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext       = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $safe_name = uniqid('entry_', true) . '.' . $ext;
            $target    = $entry_upload_dir . '/' . $safe_name;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                $image_path = $target;
            }
        }

        if ($title !== '' && $content !== '') {
            $stmt = $pdo->prepare(
                "INSERT INTO JournalEntries (user_id, title, content, mood, image_path, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $stmt->execute([$user_id, $title, $content, $mood, $image_path]);
        }

        $redirect_hash = '#journal-archives';
    }

    // ── 2. GALLERY IMAGE UPLOAD (Visual Space) ─────────────────────
    elseif ($form_type === 'gallery_upload'
        && isset($_FILES['gallery_image'])
        && $_FILES['gallery_image']['error'] === UPLOAD_ERR_OK) {

        $ext       = pathinfo($_FILES['gallery_image']['name'], PATHINFO_EXTENSION);
        $safe_name = uniqid('gallery_', true) . '.' . $ext;
        $target    = $gallery_upload_dir . '/' . $safe_name;

        if (move_uploaded_file($_FILES['gallery_image']['tmp_name'], $target)) {
            $stmt = $pdo->prepare(
                "INSERT INTO GalleryImages (user_id, image_path, uploaded_at) VALUES (?, ?, NOW())"
            );
            $stmt->execute([$user_id, $target]);
        }

        $redirect_hash = '#visual-space';
    }

    // ── 3. MOOD LOG ENTRY ───────────────────────────────────────────
    elseif ($form_type === 'mood_log' && isset($_POST['mood_emoji']) && trim($_POST['mood_emoji']) !== '') {

        $emoji = trim($_POST['mood_emoji']);
        $label = trim($_POST['mood_label'] ?? '');
        $desc  = trim($_POST['mood_desc'] ?? '');

        $stmt = $pdo->prepare(
            "INSERT INTO MoodLogs (user_id, emoji, mood_label, description, logged_at) VALUES (?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$user_id, $emoji, $label, $desc]);

        $redirect_hash = '#mood-log';
    }

    header('Location: journal.php' . $redirect_hash);
    exit();
}

// ── FETCH EVERYTHING FOR DISPLAY (always from the DB, per logged-in user) ──
$stmt = $pdo->prepare("SELECT * FROM JournalEntries WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$entries = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM GalleryImages WHERE user_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$user_id]);
$gallery = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM MoodLogs WHERE user_id = ? ORDER BY logged_at DESC LIMIT 8");
$stmt->execute([$user_id]);
$mood_logs = $stmt->fetchAll();

// Motivational quotes context
$quotes = [
    "Your words outline your growth. Write to remember, reflect to transform.",
    "Breathe in intention, breathe out judgment.",
    "Every day holds a quiet magic waiting to be cataloged.",
];
$quote = $quotes[date('N') % count($quotes)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>SYNCUBE — Journal</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet" />
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --sage: #8a9e8c;
    --sage-light: #c8d8c9;
    --sage-pale: #eef3ee;
    --stone: #a89f96;
    --stone-light: #e8e3de;
    --stone-pale: #f4f1ee;
    --cream: #faf8f5;
    --warm-white: #f7f5f2;
    --text-dark: #2c2a27;
    --text-mid: #6b6760;
    --text-muted: #a09d9a;
    --accent: #7c8f7e;
    --border: rgba(44,42,39,0.08);
    --border-soft: rgba(44,42,39,0.05);
    --shadow-soft: 0 2px 20px rgba(44,42,39,0.06);
    --shadow-card: 0 1px 8px rgba(44,42,39,0.07);
    --radius-sm: 10px;
    --radius-md: 16px;
    --radius-lg: 22px;
    --nav-height: 64px;
    --ff-display: 'Cormorant Garamond', Georgia, serif;
    --ff-body: 'DM Sans', system-ui, sans-serif;
    --transition: 0.22s ease;
  }

  html, body {
    height: 100%;
    overflow: hidden;
    background: var(--cream);
    font-family: var(--ff-body);
    color: var(--text-dark);
    font-size: 14px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
  }

  .app-container {
    display: flex;
    flex-direction: column;
    height: 100vh;
  }

  .main-content {
    flex: 1;
    overflow-y: auto;
    padding: 40px 36px 60px;
    margin-top: var(--nav-height);
  }

  .main-content::-webkit-scrollbar { width: 6px; }
  .main-content::-webkit-scrollbar-track { background: transparent; }
  .main-content::-webkit-scrollbar-thumb { background: rgba(44, 42, 39, 0.1); border-radius: 10px; }
  .main-content::-webkit-scrollbar-thumb:hover { background: rgba(44, 42, 39, 0.2); }

  .content-inner { max-width: 1200px; margin: 0 auto; width: 100%; }

  /* ─── NAV ─── */
  nav {
    position: fixed; top: 0; left: 0; right: 0; height: var(--nav-height);
    background: rgba(250,248,245,0.92); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
    border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 36px; z-index: 100;
  }
  .nav-logo { font-family: var(--ff-display); font-size: 22px; font-weight: 400; color: var(--text-dark); letter-spacing: 0.08em; text-decoration: none; display: flex; align-items: center; gap: 8px; }
  .nav-logo span { display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: var(--sage); }
  .nav-links { display: flex; align-items: center; gap: 4px; list-style: none; }
  .nav-links a { font-family: var(--ff-body); font-size: 13px; font-weight: 400; color: var(--text-mid); text-decoration: none; padding: 6px 14px; border-radius: var(--radius-sm); transition: all var(--transition); }
  .nav-links a:hover, .nav-links a.active { color: var(--text-dark); background: var(--stone-light); }
  .nav-links a.active { font-weight: 500; }

  .profile-wrap { position: relative; }
  .profile-btn { display: flex; align-items: center; gap: 9px; cursor: pointer; padding: 6px 12px 6px 6px; border-radius: 40px; border: 1px solid var(--border); background: var(--warm-white); font-size: 13px; color: var(--text-mid); user-select: none; }
  .profile-avatar { width: 30px; height: 30px; border-radius: 50%; background: var(--sage-light); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 500; color: var(--accent); flex-shrink: 0; }
  .chevron { width: 14px; height: 14px; opacity: 0.45; transition: transform var(--transition); }
  .profile-wrap.open .chevron { transform: rotate(180deg); }
  
  .dropdown { position: absolute; top: calc(100% + 8px); right: 0; background: var(--warm-white); border: 1px solid var(--border); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); min-width: 180px; padding: 8px; opacity: 0; transform: translateY(-6px); pointer-events: none; transition: all var(--transition); z-index: 200; }
  .profile-wrap.open .dropdown { opacity: 1; transform: translateY(0); pointer-events: all; }
  .dropdown-header { padding: 8px 10px 10px; border-bottom: 1px solid var(--border); margin-bottom: 6px; }
  .dropdown-header strong { display: block; font-size: 13px; color: var(--text-dark); }
  .dropdown-header span { font-size: 11px; color: var(--text-muted); }
  .dropdown a { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 8px; font-size: 13px; color: var(--text-mid); text-decoration: none; }
  .dropdown a:hover { background: var(--stone-light); color: var(--text-dark); }
  .dropdown .divider { height: 1px; background: var(--border); margin: 6px 0; }

  /* ─── GREETING ROW ─── */
  .greeting-row { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 36px; }
  .greeting-text h1 { font-family: var(--ff-display); font-size: 38px; font-weight: 300; color: var(--text-dark); line-height: 1.15; }
  .greeting-text h1 em { font-style: italic; color: var(--accent); }
  .greeting-text p { font-size: 13px; color: var(--text-muted); margin-top: 6px; font-style: italic; font-family: var(--ff-display); font-size: 16px; }
  
  .date-display { text-align: right; flex-shrink: 0; }
  #live-time { font-family: var(--ff-display); font-size: 46px; font-weight: 300; color: var(--text-dark); line-height: 1; letter-spacing: -0.02em; }
  #live-date { font-size: 12px; color: var(--text-muted); margin-top: 4px; letter-spacing: 0.08em; text-transform: uppercase; }

  /* ─── GRID SYSTEMS ─── */
  .grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 18px; width: 100%; }
  .card { background: var(--warm-white); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 24px; box-shadow: var(--shadow-card); }
  .card-label { font-size: 10px; font-weight: 500; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 14px; display: flex; align-items: center; gap: 6px; }
  .card-label::before { content: ''; display: inline-block; width: 4px; height: 4px; border-radius: 50%; background: var(--sage); }

  .write-card { grid-column: 1 / 3; }
  .gallery-card { grid-column: 3 / 4; }
  .mood-card { grid-column: 1 / 4; }
  .archives-card { grid-column: 1 / 4; }

  /* ─── MOOD LOG ─── */
  .moji-row { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
  .moji-btn {
    font-size: 22px; background: none;
    border: 2px solid transparent; border-radius: 50%;
    width: 40px; height: 40px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all var(--transition); opacity: 0.5;
  }
  .moji-btn:hover { opacity: 1; transform: scale(1.12); }
  .moji-btn.selected { opacity: 1; border-color: var(--sage); background: var(--sage-pale); transform: scale(1.1); }
  .moji-note {
    width: 100%; height: 52px; padding: 8px 12px;
    border: 1px solid var(--border); border-radius: var(--radius-sm);
    background: var(--cream); font-family: var(--ff-body); font-size: 12px;
    color: var(--text-dark); resize: none; outline: none;
    transition: border-color var(--transition); margin-bottom: 10px;
  }
  .moji-note:focus { border-color: var(--sage); }
  .moji-log-list { display: flex; flex-direction: column; gap: 6px; max-height: 160px; overflow-y: auto; }
  .moji-entry {
    display: flex; align-items: center; gap: 9px;
    padding: 7px 11px; border-radius: 10px;
    background: var(--cream); border: 1px solid var(--border-soft);
    font-size: 12px;
  }
  .moji-entry-emoji { font-size: 15px; flex-shrink: 0; }
  .moji-entry-text { flex: 1; color: var(--text-mid); font-style: italic; font-size: 11px; }
  .moji-entry-time { font-size: 10px; color: var(--text-muted); flex-shrink: 0; }
  .btn { padding: 8px 24px; border-radius: 40px; border: 1px solid var(--accent); background: var(--accent); color: #fff; cursor: pointer; font-size: 12.5px; }
  .btn.primary { background: var(--accent); }

  /* ─── DIGITAL GALLERY SCROLL CONTAINER ─── */
  .hero-gallery { display: flex; flex-direction: column; gap: 14px; height: 100%; justify-content: flex-start; }
  
  .gallery-grid-container { 
    display: flex; 
    flex-direction: column; 
    gap: 10px; 
    max-height: 290px; 
    overflow-y: auto; 
    padding-right: 4px;
  }
  .gallery-grid-container::-webkit-scrollbar { width: 4px; }
  .gallery-grid-container::-webkit-scrollbar-thumb { background: rgba(44, 42, 39, 0.1); border-radius: 4px; }

  .gallery-img { width: 100%; height: 120px; object-fit: cover; border-radius: var(--radius-md); border: 1px solid var(--border); transition: all 0.25s ease; }

  /* FORM ELEMENTS */
  .form-group { margin-bottom: 18px; }
  .form-group label { display: block; font-size: 11px; font-weight: 500; color: var(--text-mid); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; }
  .input-control { width: 100%; font-family: var(--ff-body); font-size: 13.5px; color: var(--text-dark); background: var(--cream); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 10px 14px; outline: none; }
  textarea.input-control { min-height: 140px; resize: vertical; }

  .emotion-container { display: flex; flex-wrap: wrap; gap: 8px; }
  .emotion-option { position: relative; }
  .emotion-option input[type="radio"] { position: absolute; opacity: 0; }
  .emotion-label { display: inline-flex; align-items: center; padding: 6px 14px; background: var(--cream); border: 1px solid var(--border-soft); border-radius: 40px; font-size: 12.5px; color: var(--text-mid); cursor: pointer; }
  .emotion-option input[type="radio"]:checked + .emotion-label { background: var(--sage-pale); color: var(--accent); border-color: var(--sage-light); }

  /* CUSTOM PHOTO UPLOAD CSS */
  .file-input-custom { display: flex; align-items: center; justify-content: center; gap: 8px; background: var(--cream); border: 1px dashed var(--stone); border-radius: var(--radius-sm); padding: 12px; color: var(--text-mid); cursor: pointer; text-align: center; font-size: 12px; transition: background var(--transition); }
  .file-input-custom:hover { background: var(--stone-pale); }

  /* BUTANG ADD TO GALLERY */
  .btn-upload-preview { width: 100%; padding: 8px; margin-top: 8px; border-radius: 8px; border: 1px solid var(--stone); background: var(--stone-light); color: var(--text-dark); font-size: 12px; cursor: pointer; font-weight: 500; transition: all var(--transition); }
  .btn-upload-preview:hover { background: var(--stone); color: #fff; }

  .form-actions { display: flex; justify-content: flex-end; margin-top: 20px; }
  .btn-submit { padding: 8px 24px; border-radius: 40px; border: 1px solid var(--accent); background: var(--accent); color: #fff; cursor: pointer; font-size: 12.5px; }

  /* LOG ENTRY ITEMS */
  .entries-log { display: flex; flex-direction: column; gap: 14px; }
  .entry-item { background: var(--cream); border: 1px solid var(--border-soft); border-radius: 12px; padding: 20px; }
  .entry-flex { display: flex; gap: 20px; justify-content: space-between; }
  .entry-main-text { flex: 1; }
  .entry-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 8px; }
  .entry-title { font-family: var(--ff-display); font-size: 20px; color: var(--text-dark); }
  .entry-date { font-size: 11px; color: var(--text-muted); }
  .entry-mood-badge { display: inline-block; font-size: 10px; background: var(--stone-light); color: var(--text-mid); padding: 2px 8px; border-radius: 20px; margin-top: 4px; }
  .entry-content { font-size: 13px; color: var(--text-mid); white-space: pre-wrap; }
  .entry-empty { font-size: 12.5px; color: var(--text-muted); font-style: italic; padding: 8px 0; }
  
  .entry-attached-img { width: 100px; height: 100px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border); flex-shrink: 0; }

  @media (max-width: 900px) {
    nav { padding: 0 18px; }
    .grid { grid-template-columns: 1fr 1fr; }
    .write-card, .archives-card, .mood-card { grid-column: 1 / 3; }
    .gallery-card { display: none; }
  }
</style>
</head>
<body>

<div class="app-container">
  <nav>
    <a href="dashboard.php" class="nav-logo"><span></span> syncube</a>
    <ul class="nav-links">
      <li><a href="dashboard.php">Dashboard</a></li>
      <li><a href="workspace.php">Workspace</a></li>
      <li><a href="calendar.php">Calendar</a></li>
      <li><a href="journal.php" class="active">Journal</a></li>
      <li>
      <div class="profile-wrap" id="profileWrap">
        <div class="profile-btn" onclick="toggleDropdown()">
          <div class="profile-avatar"><?= htmlspecialchars($user['initials']) ?></div>
          <?= htmlspecialchars($user['name']) ?>
          <svg class="chevron" viewBox="0 0 16 16" fill="none"><path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div class="dropdown" id="dropdown">
          <div class="dropdown-header">
            <strong><?= htmlspecialchars($user['name']) ?></strong>
            <span><?= htmlspecialchars($user['role']) ?> Account</span>
          </div>
          <a href="profile.php"><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5" r="3" stroke="currentColor" stroke-width="1.4"/><path d="M2 13c0-3.3 2.7-5 6-5s6 1.7 6 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg> My Profile</a>
          <div class="divider"></div>
          <a href="logout.html" class="logout"><svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M6 2H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3M10 11l3-3-3-3M13 8H6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg> Log out</a>
        </div>
      </div>
    </li>
  </ul>
</nav>

  <div class="main-content">
    <div class="content-inner">

      <div class="greeting-row">
        <div class="greeting-text">
          <h1>Daily <em>Reflection</em>.</h1>
          <p>"<?= htmlspecialchars($quote) ?>"</p>
        </div>
        <div class="date-display">
          <div id="live-time">—</div>
          <div id="live-date">—</div>
        </div>
      </div>

      <div class="grid">

        <!-- ═══════════════ WRITE NEW ENTRY (own form → journal_entry) ═══════════════ -->
        <form action="journal.php" method="POST" enctype="multipart/form-data" class="card write-card">
          <input type="hidden" name="form_type" value="journal_entry">
          <div class="card-label">Write New Entry</div>
          <div class="form-group">
            <label for="title">Entry Title</label>
            <input type="text" id="title" name="title" class="input-control" placeholder="Title your thoughts..." required />
          </div>
          <div class="form-group">
            <label>How are you feeling today?</label>
            <div class="emotion-container">
              <div class="emotion-option">
                <input type="radio" id="mood-focused" name="mood" value="🧠 Focused" checked>
                <label for="mood-focused" class="emotion-label">🧠 Focused</label>
              </div>
              <div class="emotion-option">
                <input type="radio" id="mood-grateful" name="mood" value="✨ Grateful">
                <label for="mood-grateful" class="emotion-label">✨ Grateful</label>
              </div>
              <div class="emotion-option">
                <input type="radio" id="mood-calm" name="mood" value="🌿 Calm">
                <label for="mood-calm" class="emotion-label">🌿 Calm</label>
              </div>
              <div class="emotion-option">
                <input type="radio" id="mood-tired" name="mood" value="💤 Tired">
                <label for="mood-tired" class="emotion-label">💤 Tired</label>
              </div>
              <div class="emotion-option">
                <input type="radio" id="mood-reflective" name="mood" value="💭 Reflective">
                <label for="mood-reflective" class="emotion-label">💭 Reflective</label>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label for="content">Your Reflection</label>
            <textarea id="content" name="content" class="input-control" placeholder="Start typing..." required></textarea>
          </div>
          <div class="form-group">
            <label for="image-upload">Attach a Photo (optional)</label>
            <label for="image-upload" class="file-input-custom">
              <span id="file-status">📸 Click to choose picture</span>
              <input type="file" id="image-upload" name="image" accept="image/*" style="display: none;" />
            </label>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-submit">Log Entry</button>
          </div>
        </form>

        <!-- ═══════════════ VISUAL SPACE / DIGITAL GALLERY (own form → gallery_upload) ═══════════════ -->
        <div class="card gallery-card" id="visual-space">
          <div class="card-label">Visual Space</div>
          <div class="hero-gallery">
            <form action="journal.php" method="POST" enctype="multipart/form-data">
              <input type="hidden" name="form_type" value="gallery_upload">
              <div class="form-group" style="margin-bottom: 5px;">
                <label>Select Space Photo</label>
                <label for="gallery-upload" class="file-input-custom">
                  <span id="gallery-file-status">📸 Click to choose picture</span>
                  <input type="file" id="gallery-upload" name="gallery_image" accept="image/*" style="display: none;" required />
                </label>
                <button type="submit" class="btn-upload-preview">Add to Digital Gallery</button>
              </div>
            </form>

            <div>
              <label style="font-size: 11px; font-weight: 500; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 6px;">Inspirations & Workspace</label>

              <div class="gallery-grid-container" id="digitalGallery">
                <?php foreach ($gallery as $img): ?>
                  <img src="<?= htmlspecialchars($img['image_path']) ?>" class="gallery-img" alt="User uploaded inspiration">
                <?php endforeach; ?>
                <!-- Default illustrative image, kept as a fallback so the space is never empty -->
                <img src="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=300&q=80" class="gallery-img" alt="Beach Inspiration">
              </div>
            </div>
          </div>
        </div>

        <!-- ═══════════════ MOOD LOG (own form → mood_log) ═══════════════ -->
        <div class="card mood-card" id="mood-log">
          <div class="card-label">Mood Log</div>
          <form action="journal.php" method="POST" id="moodForm">
            <input type="hidden" name="form_type" value="mood_log">
            <input type="hidden" name="mood_emoji" id="mood_emoji_input" value="">
            <input type="hidden" name="mood_label" id="mood_label_input" value="">
            <div class="moji-row">
              <button type="button" class="moji-btn" data-moji="😄" data-label="Great" onclick="selectMoji(this)" title="Great">😄</button>
              <button type="button" class="moji-btn" data-moji="🙂" data-label="Good" onclick="selectMoji(this)" title="Good">🙂</button>
              <button type="button" class="moji-btn" data-moji="😐" data-label="Okay" onclick="selectMoji(this)" title="Okay">😐</button>
              <button type="button" class="moji-btn" data-moji="😔" data-label="Low" onclick="selectMoji(this)" title="Low">😔</button>
              <button type="button" class="moji-btn" data-moji="😤" data-label="Stressed" onclick="selectMoji(this)" title="Stressed">😤</button>
            </div>
            <textarea class="moji-note" name="mood_desc" id="moji-note" placeholder="How are you feeling today?"></textarea>
            <div style="display:flex;gap:7px;margin-bottom:12px">
              <button type="submit" class="btn primary" style="flex:1" onclick="return validateMoodForm()">Log Moji</button>
            </div>
          </form>
          <div class="moji-log-list" id="moji-log">
            <?php if (empty($mood_logs)): ?>
              <div class="entry-empty">No mood logs yet — pick an emoji above to get started.</div>
            <?php else: ?>
              <?php foreach ($mood_logs as $m): ?>
                <div class="moji-entry">
                  <span class="moji-entry-emoji"><?= htmlspecialchars($m['emoji']) ?></span>
                  <span class="moji-entry-text"><?= $m['description'] !== '' ? htmlspecialchars($m['description']) : '—' ?></span>
                  <span class="moji-entry-time"><?= date('M d, g:i A', strtotime($m['logged_at'])) ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- ═══════════════ ARCHIVES AREA (read-only, always pulled from the DB) ═══════════════ -->
        <div id="journal-archives" class="card archives-card">
          <div class="card-label">Journal Archives</div>
          <div class="entries-log">
            <?php if (empty($entries)): ?>
              <div class="entry-empty">No journal entries yet — write your first reflection above.</div>
            <?php endif; ?>
            <?php foreach ($entries as $entry): ?>
              <div class="entry-item">
                <div class="entry-flex">
                  <div class="entry-main-text">
                    <div class="entry-header">
                      <h3 class="entry-title"><?= htmlspecialchars($entry['title']) ?></h3>
                      <div class="entry-meta">
                        <div class="entry-date"><?= date('F d, Y', strtotime($entry['created_at'])) ?></div>
                        <div class="entry-mood-badge"><?= htmlspecialchars($entry['mood']) ?></div>
                      </div>
                    </div>
                    <div class="entry-content"><?= htmlspecialchars($entry['content']) ?></div>
                  </div>

                  <?php if (!empty($entry['image_path']) && file_exists($entry['image_path'])): ?>
                    <img src="<?= htmlspecialchars($entry['image_path']) ?>" class="entry-attached-img" alt="Attached workspace snapshot">
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
function updateClock() {
  const now = new Date();
  document.getElementById('live-time').textContent = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit', hour12:false});
  document.getElementById('live-date').textContent = now.toLocaleDateString([], {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'});
}
updateClock();
setInterval(updateClock, 1000);

function toggleDropdown() { document.getElementById('profileWrap').classList.toggle('open'); }
document.addEventListener('click', e => { if (!e.target.closest('#profileWrap')) document.getElementById('profileWrap').classList.remove('open'); });

// Tukar teks status apabila gambar dipilih (journal entry photo)
document.getElementById('image-upload').addEventListener('change', function() {
  if (this.files && this.files.length > 0) {
    document.getElementById('file-status').textContent = "📂 Ready: " + this.files[0].name;
  }
});

// Tukar teks status apabila gambar dipilih (gallery photo)
document.getElementById('gallery-upload').addEventListener('change', function() {
  if (this.files && this.files.length > 0) {
    document.getElementById('gallery-file-status').textContent = "📂 Ready: " + this.files[0].name;
  }
});

// ═══════════════════════════════════════
// MOOD LOG — selects the emoji, then submits to the server so it
// actually persists in the MoodLogs table (survives logout/login).
// ═══════════════════════════════════════
function selectMoji(btn) {
  document.querySelectorAll('.moji-btn').forEach(b => b.classList.remove('selected'));
  btn.classList.add('selected');
  document.getElementById('mood_emoji_input').value = btn.dataset.moji;
  document.getElementById('mood_label_input').value = btn.dataset.label;
}

function validateMoodForm() {
  if (!document.getElementById('mood_emoji_input').value) {
    alert('Please pick a mood emoji first!');
    return false;
  }
  return true;
}

// Auto smooth scroll to whichever section the last form submission redirected to
window.addEventListener('DOMContentLoaded', () => {
  const hash = window.location.hash;
  if (hash) {
    const target = document.querySelector(hash);
    const scrollContainer = document.querySelector('.main-content');

    if (target && scrollContainer) {
      setTimeout(() => {
        const targetPosition = target.offsetTop - 20;
        scrollContainer.scrollTo({ top: targetPosition, behavior: 'smooth' });
      }, 300);
    }
  }
});
</script>
</body>
</html>