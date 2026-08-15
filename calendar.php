<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$user = [
    'name'     => $_SESSION['user_name'],
    'initials' => strtoupper(substr(explode(' ', $_SESSION['user_name'])[0], 0, 1) . substr(explode(' ', $_SESSION['user_name'])[1] ?? '', 0, 1)),
    'role'     => 'Personal'
];

// ... rest of your dashboard code

// ── DB connection ──
$host   = 'localhost';
$dbname = 'syncube';
$dbuser = 'root';
$dbpass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // Graceful fallback for demo
    $pdo = null;
}

// ── Priority → colour map ──
// We store priority in the description field as a prefix [HIGH], [MEDIUM], [LOW]
// OR we add a virtual colour via a priority column if you add one later.
// For now we derive it from the title prefix or a separate field if present.
// Colour categories:
$priority_colors = [
    'high'    => ['label' => 'High',    'bg' => '#f0e4e4', 'dot' => '#c4856a', 'text' => '#9e4e38'],
    'medium'  => ['label' => 'Medium',  'bg' => '#e4eee5', 'dot' => '#8aab8d', 'text' => '#4d7a51'],
    'low'     => ['label' => 'Low',     'bg' => '#eceaf5', 'dot' => '#9b93cc', 'text' => '#5d54a0'],
    'personal'=> ['label' => 'Personal','bg' => '#f5eee8', 'dot' => '#c4a882', 'text' => '#8a6a42'],
];

// ── Handle AJAX: fetch events for a month/range ──
if (isset($_GET['action']) && $_GET['action'] === 'get_events' && $pdo) {
    header('Content-Type: application/json');
    $uid   = (int)$_SESSION['user_id'];
    $year  = (int)($_GET['year']  ?? date('Y'));
    $month = (int)($_GET['month'] ?? date('n'));
    $start = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $end   = date('Y-m-t', strtotime($start));
    $stmt  = $pdo->prepare("SELECT * FROM calendarevent WHERE user_id = ? AND event_date BETWEEN ? AND ? ORDER BY event_date, start_time");
    $stmt->execute([$uid, $start, $end]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// ── Handle AJAX: create event ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_event' && $pdo) {
    header('Content-Type: application/json');
    $uid   = (int)$_SESSION['user_id'];
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $date  = $_POST['event_date'] ?? '';
    $start = $_POST['start_time'] ?? '00:00';
    $end   = $_POST['end_time']   ?? '00:00';
    $loc   = trim($_POST['location'] ?? '');
    $pri   = $_POST['priority'] ?? 'medium';
    // Store priority as a JSON prefix in description for simplicity
    $full_desc = json_encode(['priority' => $pri, 'note' => $desc]);
    if (!$title || !$date) { echo json_encode(['success' => false, 'msg' => 'Title and date required']); exit; }
    $stmt = $pdo->prepare("INSERT INTO calendarevent (user_id, title, description, event_date, start_time, end_time, location) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$uid, $title, $full_desc, $date, $start, $end, $loc]);
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

// ── Handle AJAX: delete event ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_event' && $pdo) {
    header('Content-Type: application/json');
    $uid = (int)$_SESSION['user_id'];
    $eid = (int)($_POST['event_id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM calendarevent WHERE event_id = ? AND user_id = ?");
    $stmt->execute([$eid, $uid]);
    echo json_encode(['success' => true]);
    exit;
}

$current_month = (int)date('n');
$current_year  = (int)date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>SYNCUBE — Calendar</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --sage:#8a9e8c;--sage-light:#c8d8c9;--sage-pale:#eef3ee;
  --stone:#a89f96;--stone-light:#e8e3de;--stone-pale:#f4f1ee;
  --cream:#faf8f5;--warm-white:#f7f5f2;
  --text-dark:#2c2a27;--text-mid:#6b6760;--text-muted:#a09d9a;
  --accent:#7c8f7e;--accent-warm:#c4a882;
  --border:rgba(44,42,39,0.08);--border-soft:rgba(44,42,39,0.05);
  --shadow-soft:0 2px 20px rgba(44,42,39,0.06);
  --shadow-card:0 1px 8px rgba(44,42,39,0.07);
  --radius-sm:10px;--radius-md:16px;--radius-lg:22px;
  --nav-height:64px;
  --ff-display:'Cormorant Garamond',Georgia,serif;
  --ff-body:'DM Sans',system-ui,sans-serif;
  --transition:0.22s ease;
  --sidebar-w:260px;
}
html{scroll-behavior:smooth;}
body{font-family:var(--ff-body);background:var(--cream);color:var(--text-dark);min-height:100vh;font-size:14px;line-height:1.6;-webkit-font-smoothing:antialiased;overflow:hidden;}

/* ─── NAV ─── */
nav{
  position:fixed;top:0;left:0;right:0;height:var(--nav-height);
  background:rgba(250,248,245,0.92);backdrop-filter:blur(16px);
  border-bottom:1px solid var(--border);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 28px;z-index:100;
}
.nav-logo{font-family:var(--ff-display);font-size:22px;font-weight:400;color:var(--text-dark);letter-spacing:.08em;text-decoration:none;display:flex;align-items:center;gap:8px;}
.nav-logo span{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--sage);}
.nav-links{display:flex;align-items:center;gap:4px;list-style:none;}
.nav-links a{font-size:13px;font-weight:400;color:var(--text-mid);text-decoration:none;padding:6px 14px;border-radius:var(--radius-sm);transition:all var(--transition);}
.nav-links a:hover,.nav-links a.active{color:var(--text-dark);background:var(--stone-light);}
.nav-links a.active{font-weight:500;}
.profile-wrap{position:relative;}
.profile-btn{display:flex;align-items:center;gap:9px;cursor:pointer;padding:6px 12px 6px 6px;border-radius:40px;border:1px solid var(--border);background:var(--warm-white);transition:all var(--transition);font-size:13px;color:var(--text-mid);}
.profile-btn:hover{background:var(--stone-light);}
.profile-avatar{width:30px;height:30px;border-radius:50%;background:var(--sage-light);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:500;color:var(--accent);letter-spacing:.05em;}
.chevron{width:14px;height:14px;opacity:.45;transition:transform var(--transition);}
.profile-wrap.open .chevron{transform:rotate(180deg);}
.dropdown{position:absolute;top:calc(100% + 8px);right:0;background:var(--warm-white);border:1px solid var(--border);border-radius:var(--radius-md);box-shadow:var(--shadow-soft);min-width:180px;padding:8px;opacity:0;transform:translateY(-6px);pointer-events:none;transition:all var(--transition);z-index:200;}
.profile-wrap.open .dropdown{opacity:1;transform:translateY(0);pointer-events:all;}
.dropdown-header{padding:8px 10px 10px;border-bottom:1px solid var(--border);margin-bottom:6px;}
.dropdown-header strong{display:block;font-size:13px;font-weight:500;color:var(--text-dark);}
.dropdown-header span{font-size:11px;color:var(--text-muted);}
.dropdown a{display:flex;align-items:center;gap:8px;padding:8px 10px;border-radius:8px;font-size:13px;color:var(--text-mid);text-decoration:none;transition:all var(--transition);}
.dropdown a:hover{background:var(--stone-light);color:var(--text-dark);}
.dropdown a.logout{color:#b85c5c;}
.dropdown a.logout:hover{background:#fdf0f0;color:#a34444;}
.dropdown .divider{height:1px;background:var(--border);margin:6px 0;}

/* ─── LAYOUT ─── */
.page-body{display:flex;height:calc(100vh - var(--nav-height));margin-top:var(--nav-height);overflow:hidden;}

/* ─── LEFT SIDEBAR ─── */
.sidebar{
  width:var(--sidebar-w);flex-shrink:0;
  background:var(--warm-white);
  border-right:1px solid var(--border);
  display:flex;flex-direction:column;
  padding:24px 18px;
  overflow-y:auto;
  gap:24px;
}

/* New Event Button */
.btn-new-event{
  display:flex;align-items:center;justify-content:center;gap:8px;
  width:100%;padding:10px 0;
  background:var(--accent);color:#fff;
  border:none;border-radius:40px;
  font-family:var(--ff-body);font-size:13px;font-weight:400;
  cursor:pointer;transition:all var(--transition);letter-spacing:.03em;
}
.btn-new-event:hover{background:#6d7f70;}
.btn-new-event svg{flex-shrink:0;}

/* Mini Calendar */
.mini-cal-header{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:10px;
}
.mini-cal-title{
  font-family:var(--ff-display);font-size:16px;font-weight:400;
  color:var(--text-dark);letter-spacing:.02em;
}
.mini-nav-btn{
  width:26px;height:26px;border-radius:50%;border:1px solid var(--border);
  background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;
  color:var(--text-muted);transition:all var(--transition);
}
.mini-nav-btn:hover{background:var(--stone-light);color:var(--text-dark);}
.mini-cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;text-align:center;}
.mini-day-label{font-size:10px;color:var(--text-muted);padding:4px 0;letter-spacing:.05em;font-weight:500;}
.mini-day{
  width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-size:12px;color:var(--text-mid);cursor:pointer;transition:all var(--transition);margin:auto;
}
.mini-day:hover{background:var(--stone-light);color:var(--text-dark);}
.mini-day.today{background:var(--sage);color:#fff;font-weight:500;}
.mini-day.selected{background:var(--stone-light);color:var(--text-dark);font-weight:500;}
.mini-day.other-month{color:var(--text-muted);opacity:.4;}
.mini-day.has-event::after{content:'';display:block;width:4px;height:4px;border-radius:50%;background:var(--accent-warm);margin:-3px auto 0;position:relative;top:2px;}

/* Legend */
.legend-title{font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px;font-weight:500;}
.legend-list{display:flex;flex-direction:column;gap:7px;}
.legend-item{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text-mid);}
.legend-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;}

/* ─── MAIN CALENDAR AREA ─── */
.cal-main{flex:1;display:flex;flex-direction:column;overflow:hidden;}

/* Calendar toolbar */
.cal-toolbar{
  display:flex;align-items:center;gap:12px;padding:16px 24px;
  border-bottom:1px solid var(--border);background:var(--warm-white);
  flex-shrink:0;
}
.cal-title{
  font-family:var(--ff-display);font-size:24px;font-weight:300;
  color:var(--text-dark);letter-spacing:-.01em;
  min-width:220px;
}
.cal-nav-btn{
  width:32px;height:32px;border-radius:50%;border:1px solid var(--border);
  background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;
  color:var(--text-mid);transition:all var(--transition);
}
.cal-nav-btn:hover{background:var(--stone-light);}
.btn-today{
  padding:6px 16px;border-radius:40px;border:1px solid var(--border);
  background:transparent;font-family:var(--ff-body);font-size:12px;
  color:var(--text-mid);cursor:pointer;transition:all var(--transition);
}
.btn-today:hover{background:var(--stone-light);color:var(--text-dark);}
.view-switcher{display:flex;border:1px solid var(--border);border-radius:40px;overflow:hidden;margin-left:auto;}
.view-btn{
  padding:6px 14px;font-family:var(--ff-body);font-size:12px;
  border:none;background:transparent;color:var(--text-mid);cursor:pointer;transition:all var(--transition);
}
.view-btn:hover{background:var(--stone-light);color:var(--text-dark);}
.view-btn.active{background:var(--sage);color:#fff;}

/* ─── CALENDAR VIEWS ─── */
.cal-view-wrap{flex:1;overflow:auto;position:relative;}
.cal-view{display:none;}
.cal-view.active{display:block;}

/* Month view */
.month-grid{
  display:grid;grid-template-columns:repeat(7,1fr);
  height:100%;
}
.month-header{
  display:grid;grid-template-columns:repeat(7,1fr);
  border-bottom:1px solid var(--border);
  flex-shrink:0;
  background:var(--warm-white);
  position:sticky;top:0;z-index:2;
}
.month-header-day{
  padding:10px 0;text-align:center;
  font-size:11px;letter-spacing:.08em;text-transform:uppercase;
  color:var(--text-muted);font-weight:500;
  border-right:1px solid var(--border-soft);
}
.month-header-day:last-child{border-right:none;}
.month-cell{
  border-right:1px solid var(--border-soft);border-bottom:1px solid var(--border-soft);
  padding:8px 8px 6px;min-height:110px;cursor:pointer;
  transition:background var(--transition);vertical-align:top;position:relative;
}
.month-cell:nth-child(7n){border-right:none;}
.month-cell:hover{background:rgba(138,158,140,0.04);}
.month-cell.today{background:var(--sage-pale);}
.month-cell.other-month{background:var(--stone-pale);opacity:.6;}
.month-day-num{
  font-size:12px;font-weight:400;color:var(--text-mid);
  width:24px;height:24px;display:flex;align-items:center;justify-content:center;
  border-radius:50%;margin-bottom:4px;
}
.month-cell.today .month-day-num{background:var(--sage);color:#fff;font-weight:500;}
.month-events{display:flex;flex-direction:column;gap:2px;}
.month-event-pill{
  font-size:10px;padding:2px 7px;border-radius:20px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  cursor:pointer;transition:opacity var(--transition);
  line-height:1.6;
}
.month-event-pill:hover{opacity:.8;}
.more-events{font-size:10px;color:var(--text-muted);padding:1px 6px;cursor:pointer;}
.more-events:hover{color:var(--text-dark);}

/* Week / Work Week view */
.week-grid-wrap{min-width:600px;}
.week-header-row{
  display:grid;border-bottom:1px solid var(--border);
  background:var(--warm-white);position:sticky;top:0;z-index:2;
}
.week-time-col{width:52px;flex-shrink:0;}
.week-day-header{
  text-align:center;padding:10px 4px;border-left:1px solid var(--border-soft);
}
.week-day-header .wdh-day{font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);}
.week-day-header .wdh-num{
  font-family:var(--ff-display);font-size:22px;font-weight:300;color:var(--text-mid);line-height:1.1;
}
.week-day-header.today .wdh-num{
  width:34px;height:34px;background:var(--sage);color:#fff;border-radius:50%;
  display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:16px;
}
.week-body{display:flex;}
.week-times{width:52px;flex-shrink:0;}
.week-time-slot{height:56px;display:flex;align-items:flex-start;padding-top:4px;padding-right:8px;justify-content:flex-end;}
.week-time-label{font-size:10px;color:var(--text-muted);letter-spacing:.04em;white-space:nowrap;}
.week-day-col{flex:1;border-left:1px solid var(--border-soft);position:relative;min-width:0;}
.week-hour-line{height:56px;border-bottom:1px solid var(--border-soft);}
.week-event{
  position:absolute;left:3px;right:3px;border-radius:8px;
  padding:3px 7px;font-size:11px;overflow:hidden;cursor:pointer;
  transition:opacity var(--transition);z-index:1;
}
.week-event:hover{opacity:.85;}
.week-event .ev-title{font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.week-event .ev-time{opacity:.75;font-size:10px;}
.current-time-line{
  position:absolute;left:0;right:0;height:2px;background:#c4856a;z-index:3;
  pointer-events:none;
}
.current-time-line::before{
  content:'';position:absolute;left:-4px;top:-4px;
  width:10px;height:10px;border-radius:50%;background:#c4856a;
}

/* Day view */
.day-grid-wrap{max-width:700px;margin:0 auto;padding:0 24px;}
.day-header{
  font-family:var(--ff-display);font-size:32px;font-weight:300;
  color:var(--text-dark);padding:20px 0 12px;
  border-bottom:1px solid var(--border);margin-bottom:0;
}
.day-body{display:flex;}
.day-times{width:52px;flex-shrink:0;}
.day-time-slot{height:60px;display:flex;align-items:flex-start;padding-top:5px;padding-right:8px;justify-content:flex-end;}
.day-events-col{flex:1;position:relative;border-left:1px solid var(--border-soft);}
.day-hour-line{height:60px;border-bottom:1px solid var(--border-soft);}
.day-event{
  position:absolute;left:8px;right:8px;border-radius:10px;
  padding:6px 10px;font-size:12px;cursor:pointer;
  transition:opacity var(--transition);z-index:1;
}
.day-event:hover{opacity:.85;}
.day-event .ev-title{font-weight:500;}
.day-event .ev-time{opacity:.75;font-size:11px;margin-top:2px;}
.day-event .ev-loc{opacity:.65;font-size:10px;margin-top:1px;}

/* ─── MODAL ─── */
.modal-overlay{
  position:fixed;inset:0;background:rgba(44,42,39,.35);
  backdrop-filter:blur(4px);z-index:500;
  display:none;align-items:center;justify-content:center;
}
.modal-overlay.open{display:flex;}
.modal{
  background:var(--warm-white);border-radius:var(--radius-lg);
  border:1px solid var(--border);box-shadow:0 20px 60px rgba(44,42,39,.15);
  width:100%;max-width:460px;padding:32px;
  animation:modalIn .25s ease;
  max-height:90vh;overflow-y:auto;
}
@keyframes modalIn{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:translateY(0);}}
.modal-title{font-family:var(--ff-display);font-size:26px;font-weight:300;color:var(--text-dark);margin-bottom:22px;letter-spacing:-.01em;}
.form-group{margin-bottom:16px;}
.form-label{display:block;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px;font-weight:500;}
.form-input,.form-select,.form-textarea{
  width:100%;padding:10px 14px;border:1px solid var(--border);
  border-radius:var(--radius-sm);background:var(--cream);
  font-family:var(--ff-body);font-size:13px;color:var(--text-dark);
  outline:none;transition:border-color var(--transition);
}
.form-input:focus,.form-select:focus,.form-textarea:focus{border-color:var(--sage);}
.form-textarea{resize:vertical;min-height:72px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.priority-selector{display:flex;gap:8px;}
.priority-pill{
  flex:1;padding:8px 0;border-radius:40px;border:1.5px solid var(--border);
  background:transparent;font-family:var(--ff-body);font-size:12px;
  cursor:pointer;transition:all var(--transition);text-align:center;color:var(--text-mid);
}
.priority-pill:hover{border-color:var(--sage);color:var(--accent);}
.priority-pill.selected-high{background:#f0e4e4;border-color:#c4856a;color:#9e4e38;}
.priority-pill.selected-medium{background:#e4eee5;border-color:#8aab8d;color:#4d7a51;}
.priority-pill.selected-low{background:#eceaf5;border-color:#9b93cc;color:#5d54a0;}
.priority-pill.selected-personal{background:#f5eee8;border-color:#c4a882;color:#8a6a42;}
.modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:24px;}
.btn-cancel{padding:9px 22px;border-radius:40px;border:1px solid var(--border);background:transparent;font-family:var(--ff-body);font-size:13px;color:var(--text-mid);cursor:pointer;transition:all var(--transition);}
.btn-cancel:hover{background:var(--stone-light);}
.btn-save{padding:9px 22px;border-radius:40px;border:none;background:var(--accent);color:#fff;font-family:var(--ff-body);font-size:13px;cursor:pointer;transition:all var(--transition);}
.btn-save:hover{background:#6d7f70;}

/* Event detail modal */
.ev-detail-header{display:flex;align-items:flex-start;gap:12px;margin-bottom:16px;}
.ev-detail-dot{width:12px;height:12px;border-radius:50%;flex-shrink:0;margin-top:5px;}
.ev-detail-title{font-family:var(--ff-display);font-size:24px;font-weight:300;color:var(--text-dark);line-height:1.2;}
.ev-detail-row{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:var(--text-mid);margin-bottom:10px;}
.ev-detail-row svg{flex-shrink:0;margin-top:1px;opacity:.6;}
.btn-delete{padding:9px 22px;border-radius:40px;border:1px solid rgba(180,70,70,.25);background:transparent;font-family:var(--ff-body);font-size:13px;color:#b85c5c;cursor:pointer;transition:all var(--transition);margin-right:auto;}
.btn-delete:hover{background:#fdf0f0;}

/* Loading */
.cal-loading{display:flex;align-items:center;justify-content:center;height:200px;color:var(--text-muted);font-family:var(--ff-display);font-size:18px;font-weight:300;}

/* Scrollbar */
::-webkit-scrollbar{width:5px;height:5px;}
::-webkit-scrollbar-track{background:transparent;}
::-webkit-scrollbar-thumb{background:var(--stone-light);border-radius:10px;}
</style>
</head>
<body>

<!-- ─── NAV ─── -->
<nav>
  <a href="dashboard.php" class="nav-logo"><span></span> syncube</a>
  <ul class="nav-links">
    <li><a href="dashboard.php">Dashboard</a></li>
    <li><a href="workspace.php">Workspace</a></li>
    <li><a href="calendar.php" class="active">Calendar</a></li>
    <li><a href="journal.php">Journal</a></li>
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

<!-- ─── PAGE BODY ─── -->
<div class="page-body">

  <!-- LEFT SIDEBAR -->
  <aside class="sidebar">
    <!-- New Event -->
    <button class="btn-new-event" onclick="openCreateModal()">
      <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
      New Event
    </button>

    <!-- Mini Calendar -->
    <div>
      <div class="mini-cal-header">
        <button class="mini-nav-btn" onclick="miniNavMonth(-1)">
          <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M8 2L4 6l4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div class="mini-cal-title" id="mini-title"></div>
        <button class="mini-nav-btn" onclick="miniNavMonth(1)">
          <svg width="12" height="12" viewBox="0 0 12 12" fill="none"><path d="M4 2l4 4-4 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </div>
      <div class="mini-cal-grid" id="mini-grid"></div>
    </div>

    <!-- Legend -->
    <div>
      <div class="legend-title">Priority</div>
      <div class="legend-list">
        <div class="legend-item"><div class="legend-dot" style="background:#c4856a"></div> High Priority</div>
        <div class="legend-item"><div class="legend-dot" style="background:#8aab8d"></div> Medium Priority</div>
        <div class="legend-item"><div class="legend-dot" style="background:#9b93cc"></div> Low Priority</div>
        <div class="legend-item"><div class="legend-dot" style="background:#c4a882"></div> Personal</div>
      </div>
    </div>
  </aside>

  <!-- MAIN CALENDAR -->
  <div class="cal-main">
    <!-- Toolbar -->
    <div class="cal-toolbar">
      <button class="cal-nav-btn" onclick="navigate(-1)">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M9 2L5 7l4 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <button class="cal-nav-btn" onclick="navigate(1)">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M5 2l4 5-4 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </button>
      <div class="cal-title" id="cal-title"></div>
      <button class="btn-today" onclick="goToday()">Today</button>
      <div class="view-switcher">
        <button class="view-btn" onclick="setView('day',this)">Day</button>
        <button class="view-btn" onclick="setView('workweek',this)">Work Week</button>
        <button class="view-btn" onclick="setView('week',this)">Week</button>
        <button class="view-btn active" onclick="setView('month',this)">Month</button>
      </div>
    </div>

    <!-- Calendar view container -->
    <div class="cal-view-wrap" id="cal-view-wrap">
      <div class="cal-loading" id="cal-loading">Loading your calendar…</div>
    </div>
  </div>
</div>

<!-- ─── CREATE EVENT MODAL ─── -->
<div class="modal-overlay" id="createModal">
  <div class="modal">
    <div class="modal-title">New Event</div>
    <div class="form-group">
      <label class="form-label">Title</label>
      <input type="text" class="form-input" id="ev-title" placeholder="Add a title…"/>
    </div>
    <div class="form-group">
      <label class="form-label">Date</label>
      <input type="date" class="form-input" id="ev-date"/>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Start Time</label>
        <input type="time" class="form-input" id="ev-start"/>
      </div>
      <div class="form-group">
        <label class="form-label">End Time</label>
        <input type="time" class="form-input" id="ev-end"/>
      </div>
    </div>
    <div class="form-group">
      <label class="form-label">Location</label>
      <input type="text" class="form-input" id="ev-location" placeholder="Optional location…"/>
    </div>
    <div class="form-group">
      <label class="form-label">Description</label>
      <textarea class="form-textarea" id="ev-desc" placeholder="Optional notes…"></textarea>
    </div>
    <div class="form-group">
      <label class="form-label">Priority / Category</label>
      <div class="priority-selector">
        <button class="priority-pill selected-high" data-p="high" onclick="selectPriority('high')">🔴 High</button>
        <button class="priority-pill" data-p="medium" onclick="selectPriority('medium')">🟢 Medium</button>
        <button class="priority-pill" data-p="low" onclick="selectPriority('low')">🟣 Low</button>
        <button class="priority-pill" data-p="personal" onclick="selectPriority('personal')">🟡 Personal</button>
      </div>
    </div>
    <div class="modal-actions">
      <button class="btn-cancel" onclick="closeModal('createModal')">Cancel</button>
      <button class="btn-save" onclick="saveEvent()">Save Event</button>
    </div>
  </div>
</div>

<!-- ─── EVENT DETAIL MODAL ─── -->
<div class="modal-overlay" id="detailModal">
  <div class="modal">
    <div class="ev-detail-header">
      <div class="ev-detail-dot" id="det-dot"></div>
      <div class="ev-detail-title" id="det-title"></div>
    </div>
    <div class="ev-detail-row">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><rect x="2" y="3" width="10" height="9" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M5 2v2M9 2v2M2 6h10" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      <span id="det-date"></span>
    </div>
    <div class="ev-detail-row" id="det-time-row">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.3"/><path d="M7 4.5V7l1.5 1.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      <span id="det-time"></span>
    </div>
    <div class="ev-detail-row" id="det-loc-row">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1.5C5.07 1.5 3.5 3.07 3.5 5c0 2.8 3.5 7 3.5 7s3.5-4.2 3.5-7c0-1.93-1.57-3.5-3.5-3.5z" stroke="currentColor" stroke-width="1.3"/><circle cx="7" cy="5" r="1.2" stroke="currentColor" stroke-width="1.1"/></svg>
      <span id="det-loc"></span>
    </div>
    <div class="ev-detail-row" id="det-desc-row">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 3h10M2 6h8M2 9h6" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      <span id="det-desc"></span>
    </div>
    <div class="modal-actions">
      <button class="btn-delete" id="det-delete-btn" onclick="deleteEvent()">Delete</button>
      <button class="btn-cancel" onclick="closeModal('detailModal')">Close</button>
    </div>
  </div>
</div>

<script>
// ─── STATE ───
const TODAY    = new Date();
TODAY.setHours(0,0,0,0);
let viewDate   = new Date(TODAY);
let currentView = 'month';
let events     = []; // raw event objects
let selectedPriority = 'high';
let activeEventId = null;

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const DAYS   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

const COLORS = {
  high:     { bg:'#f0e4e4', dot:'#c4856a', text:'#9e4e38' },
  medium:   { bg:'#e4eee5', dot:'#8aab8d', text:'#4d7a51' },
  low:      { bg:'#eceaf5', dot:'#9b93cc', text:'#5d54a0' },
  personal: { bg:'#f5eee8', dot:'#c4a882', text:'#8a6a42' },
};

function getPriority(ev) {
  try { const d = JSON.parse(ev.description || '{}'); return d.priority || 'medium'; }
  catch(e) { return 'medium'; }
}
function getNote(ev) {
  try { const d = JSON.parse(ev.description || '{}'); return d.note || ''; }
  catch(e) { return ev.description || ''; }
}

// ─── FETCH EVENTS ───
async function fetchEvents(year, month) {
  try {
    const r = await fetch(`calendar.php?action=get_events&year=${year}&month=${month}`);
    const contentType = r.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
      return await r.json();
    }
    // Server returned HTML (e.g. auth redirect) — use demo data
    throw new Error('not json');
  } catch(e) {
    // Demo fallback — sample events so UI is never empty
    const pad = n => String(n).padStart(2,'0');
    const y = year, m = pad(month);
    const d = Math.min(TODAY.getDate(), 28);
    return [
      { event_id:1, title:'Design Review',     event_date:`${y}-${m}-${pad(d)}`,   start_time:'10:00:00', end_time:'11:00:00', location:'Zoom', description:JSON.stringify({priority:'high',note:'Bring mockups'}) },
      { event_id:2, title:'Team Standup',       event_date:`${y}-${m}-${pad(Math.min(d+1,28))}`, start_time:'09:30:00', end_time:'09:45:00', location:'', description:JSON.stringify({priority:'medium',note:''}) },
      { event_id:3, title:'Client Review',event_date:`${y}-${m}-${pad(Math.min(d+2,28))}`, start_time:'15:00:00', end_time:'16:00:00', location:'', description:JSON.stringify({priority:'low',note:'Focus on methodology'}) },
    ];
  }
}

// ─── RENDER DISPATCHER ───
async function render() {
  const loadingEl = document.getElementById('cal-loading');
  if (loadingEl) loadingEl.style.display = 'flex';
  const y = viewDate.getFullYear(), m = viewDate.getMonth() + 1;
  events = await fetchEvents(y, m);
  if (loadingEl) loadingEl.style.display = 'none';
  updateMiniCal(y, m - 1);
  if (currentView === 'month') renderMonth(y, m - 1);
  else if (currentView === 'week') renderWeek(false);
  else if (currentView === 'workweek') renderWeek(true);
  else if (currentView === 'day') renderDay();
}

// ─── MONTH VIEW ───
function renderMonth(year, month) {
  const title = MONTHS[month] + ' ' + year;
  document.getElementById('cal-title').textContent = title;

  const first    = new Date(year, month, 1);
  const lastDay  = new Date(year, month + 1, 0).getDate();
  const startDow = first.getDay(); // 0=Sun which column the 1st falls on

  // Total grid cells = leading blanks + all days of month, padded to full rows
  const totalCells = Math.ceil((startDow + lastDay) / 7) * 7;
  const rows = totalCells / 7;

  let html = `<div class="month-view-wrap" style="display:flex;flex-direction:column;height:100%;">`;

  // Day-of-week header
  html += `<div class="month-header">`;
  DAYS.forEach(d => html += `<div class="month-header-day">${d}</div>`);
  html += `</div>`;

  // Grid body — fills remaining height
  html += `<div class="month-body-grid" style="display:grid;grid-template-columns:repeat(7,1fr);grid-template-rows:repeat(${rows},1fr);flex:1;">`;

  for (let i = 0; i < totalCells; i++) {
    const dayNum = i - startDow + 1; // 1-based date, negative = before month

    if (dayNum < 1 || dayNum > lastDay) {
      // Empty filler cell (no date shown — clean look)
      html += `<div class="month-cell month-cell-empty"></div>`;
      continue;
    }

    const date    = new Date(year, month, dayNum);
    const isToday = date.toDateString() === TODAY.toDateString();
    const key     = fmtDate(date);
    const dayEvs  = events.filter(e => e.event_date === key);
    const cls     = ['month-cell', isToday ? 'today' : ''].filter(Boolean).join(' ');

    html += `<div class="${cls}" onclick="clickDay('${key}')">`;
    html += `<div class="month-day-num">${dayNum}</div>`;
    html += `<div class="month-events">`;
    dayEvs.slice(0, 3).forEach(ev => {
      const p = getPriority(ev);
      const c = COLORS[p] || COLORS.medium;
      html += `<div class="month-event-pill" style="background:${c.bg};color:${c.text};" onclick="event.stopPropagation();showDetail(${ev.event_id})">${ev.title}</div>`;
    });
    if (dayEvs.length > 3) html += `<div class="more-events">+${dayEvs.length - 3} more</div>`;
    html += `</div></div>`;
  }

  html += `</div></div>`;
  document.getElementById('cal-view-wrap').innerHTML = html;
}

// ─── WEEK / WORKWEEK VIEW ───
function renderWeek(workOnly) {
  const base = new Date(viewDate);
  const dow = base.getDay();
  base.setDate(base.getDate() - dow);
  const cols = workOnly ? [1,2,3,4,5] : [0,1,2,3,4,5,6];
  const dates = cols.map(i => { const d = new Date(base); d.setDate(d.getDate() + i); return d; });

  const first = dates[0], last = dates[dates.length - 1];
  document.getElementById('cal-title').textContent =
    MONTHS[first.getMonth()] + ' ' + first.getDate() + ' – ' +
    (first.getMonth() !== last.getMonth() ? MONTHS[last.getMonth()] + ' ' : '') +
    last.getDate() + ', ' + last.getFullYear();

  const gridCols = `52px repeat(${dates.length}, 1fr)`;

  let html = `<div class="week-grid-wrap">`;
  html += `<div class="week-header-row" style="display:grid;grid-template-columns:${gridCols};">`;
  html += `<div style="width:52px;"></div>`;
  dates.forEach(d => {
    const isTod = d.toDateString() === TODAY.toDateString();
    html += `<div class="week-day-header${isTod ? ' today' : ''}">
      <div class="wdh-day">${DAYS[d.getDay()]}</div>
      <div class="wdh-num">${d.getDate()}</div>
    </div>`;
  });
  html += `</div>`;

  html += `<div class="week-body">`;
  html += `<div class="week-times">`;
  for (let h = 0; h < 24; h++) {
    const lbl = h === 0 ? '' : (h < 12 ? h + ' AM' : h === 12 ? '12 PM' : (h-12) + ' PM');
    html += `<div class="week-time-slot"><span class="week-time-label">${lbl}</span></div>`;
  }
  html += `</div>`;

  dates.forEach(d => {
    const key = fmtDate(d);
    const isTod = d.toDateString() === TODAY.toDateString();
    const dayEvs = events.filter(e => e.event_date === key);
    html += `<div class="week-day-col" onclick="clickDayAt('${key}',event)">`;
    for (let h = 0; h < 24; h++) html += `<div class="week-hour-line"></div>`;
    if (isTod) {
      const now = new Date();
      const pct = (now.getHours() * 60 + now.getMinutes()) / (24 * 60) * 100;
      html += `<div class="current-time-line" style="top:${pct}%"></div>`;
    }
    dayEvs.forEach(ev => {
      const p = getPriority(ev);
      const c = COLORS[p] || COLORS.medium;
      const [sh, sm] = (ev.start_time || '00:00').split(':').map(Number);
      const [eh, em] = (ev.end_time   || '01:00').split(':').map(Number);
      const top  = ((sh * 60 + sm) / (24 * 60)) * 100;
      const dur  = Math.max(((eh * 60 + em) - (sh * 60 + sm)), 30);
      const ht   = (dur / (24 * 60)) * 100;
      html += `<div class="week-event" style="top:${top}%;height:${ht}%;background:${c.bg};color:${c.text};" onclick="event.stopPropagation();showDetail(${ev.event_id})">
        <div class="ev-title">${ev.title}</div>
        <div class="ev-time">${fmtTime(ev.start_time)}</div>
      </div>`;
    });
    html += `</div>`;
  });

  html += `</div></div>`;
  document.getElementById('cal-view-wrap').innerHTML = html;
}

// ─── DAY VIEW ───
function renderDay() {
  const key = fmtDate(viewDate);
  document.getElementById('cal-title').textContent =
    DAYS[viewDate.getDay()] + ', ' + MONTHS[viewDate.getMonth()] + ' ' + viewDate.getDate() + ', ' + viewDate.getFullYear();

  const dayEvs = events.filter(e => e.event_date === key);

  let html = `<div class="day-grid-wrap"><div class="day-header">${MONTHS[viewDate.getMonth()]} ${viewDate.getDate()}</div>`;
  html += `<div class="day-body"><div class="day-times">`;
  for (let h = 0; h < 24; h++) {
    const lbl = h === 0 ? '' : (h < 12 ? h + ' AM' : h === 12 ? '12 PM' : (h-12) + ' PM');
    html += `<div class="day-time-slot"><span class="week-time-label">${lbl}</span></div>`;
  }
  html += `</div><div class="day-events-col" onclick="clickDayAt('${key}',event)">`;
  for (let h = 0; h < 24; h++) html += `<div class="day-hour-line"></div>`;
  // current time
  if (key === fmtDate(TODAY)) {
    const now = new Date();
    const pct = (now.getHours() * 60 + now.getMinutes()) / (24 * 60) * 100;
    html += `<div class="current-time-line" style="top:${pct}%"></div>`;
  }
  dayEvs.forEach(ev => {
    const p = getPriority(ev);
    const c = COLORS[p] || COLORS.medium;
    const [sh, sm] = (ev.start_time || '00:00').split(':').map(Number);
    const [eh, em] = (ev.end_time   || '01:00').split(':').map(Number);
    const top = ((sh * 60 + sm) / (24 * 60)) * 100;
    const dur = Math.max(((eh * 60 + em) - (sh * 60 + sm)), 45);
    const ht  = (dur / (24 * 60)) * 100;
    const loc = ev.location ? `<div class="ev-loc">📍 ${ev.location}</div>` : '';
    html += `<div class="day-event" style="top:${top}%;height:${ht}%;background:${c.bg};color:${c.text};" onclick="event.stopPropagation();showDetail(${ev.event_id})">
      <div class="ev-title">${ev.title}</div>
      <div class="ev-time">${fmtTime(ev.start_time)} – ${fmtTime(ev.end_time)}</div>
      ${loc}
    </div>`;
  });
  html += `</div></div></div>`;
  document.getElementById('cal-view-wrap').innerHTML = html;
}

// ─── MINI CALENDAR ───
let miniYear, miniMonth;
function updateMiniCal(y, m) {
  miniYear = y; miniMonth = m;
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  document.getElementById('mini-title').textContent = MONTHS[m] + ' ' + y;
  const first = new Date(y, m, 1);
  const last  = new Date(y, m + 1, 0);
  const startDay = first.getDay();
  const eventDates = new Set(events.map(e => e.event_date));
  let html = DAYS.map(d => `<div class="mini-day-label">${d[0]}</div>`).join('');
  for (let i = 0; i < startDay; i++) {
    const d = new Date(y, m, -startDay + i + 1);
    html += `<div class="mini-day other-month">${d.getDate()}</div>`;
  }
  for (let d = 1; d <= last.getDate(); d++) {
    const date = new Date(y, m, d);
    const key  = fmtDate(date);
    const isToday = date.toDateString() === TODAY.toDateString();
    const isSel   = date.toDateString() === viewDate.toDateString();
    const hasEv   = eventDates.has(key);
    const cls = ['mini-day', isToday ? 'today' : '', isSel && !isToday ? 'selected' : '', hasEv ? 'has-event' : ''].filter(Boolean).join(' ');
    html += `<div class="${cls}" onclick="selectMiniDay(${y},${m},${d})">${d}</div>`;
  }
  document.getElementById('mini-grid').innerHTML = html;
}
function miniNavMonth(dir) {
  miniMonth += dir;
  if (miniMonth < 0) { miniMonth = 11; miniYear--; }
  if (miniMonth > 11) { miniMonth = 0; miniYear++; }
  viewDate = new Date(miniYear, miniMonth, 1);
  render();
}
function selectMiniDay(y, m, d) {
  viewDate = new Date(y, m, d);
  if (currentView === 'month') render();
  else renderDay(); // jump to day view for that day
  if (currentView !== 'day') { setView('day'); return; }
  render();
}

// ─── NAVIGATION ───
function navigate(dir) {
  if (currentView === 'month') {
    viewDate.setMonth(viewDate.getMonth() + dir);
  } else if (currentView === 'week' || currentView === 'workweek') {
    viewDate.setDate(viewDate.getDate() + dir * 7);
  } else {
    viewDate.setDate(viewDate.getDate() + dir);
  }
  render();
}
function goToday() { viewDate = new Date(TODAY); render(); }
function setView(v, btn) {
  currentView = v;
  document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
  if (btn) btn.classList.add('active');
  render();
}

// ─── HELPERS ───
function fmtDate(d) {
  return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}
function fmtTime(t) {
  if (!t) return '';
  const [h, m] = t.split(':').map(Number);
  const ampm = h < 12 ? 'AM' : 'PM';
  const hr = h % 12 || 12;
  return hr + ':' + String(m).padStart(2,'0') + ' ' + ampm;
}
function fmtDateLong(s) {
  const [y, m, d] = s.split('-').map(Number);
  return DAYS[new Date(y,m-1,d).getDay()] + ', ' + MONTHS[m-1] + ' ' + d + ', ' + y;
}

// ─── CLICK DAY (create event) ───
function clickDay(dateStr) {
  openCreateModal(dateStr);
}
function clickDayAt(dateStr, e) {
  const col = e.currentTarget;
  const rect = col.getBoundingClientRect();
  const y = e.clientY - rect.top;
  const totalH = col.offsetHeight;
  const hour = Math.floor((y / totalH) * 24);
  const min  = Math.round(((y / totalH) * 24 - hour) * 60 / 15) * 15;
  const hStr = String(hour).padStart(2,'0') + ':' + String(min).padStart(2,'0');
  openCreateModal(dateStr, hStr);
}

// ─── CREATE MODAL ───
function openCreateModal(dateStr, startTime) {
  document.getElementById('ev-title').value = '';
  document.getElementById('ev-date').value  = dateStr || fmtDate(TODAY);
  document.getElementById('ev-start').value = startTime || '09:00';
  document.getElementById('ev-end').value   = '';
  document.getElementById('ev-location').value = '';
  document.getElementById('ev-desc').value  = '';
  selectPriority('high');
  document.getElementById('createModal').classList.add('open');
  setTimeout(() => document.getElementById('ev-title').focus(), 100);
}
function selectPriority(p) {
  selectedPriority = p;
  document.querySelectorAll('.priority-pill').forEach(b => {
    b.className = 'priority-pill';
    if (b.dataset.p === p) b.classList.add('selected-' + p);
  });
}
async function saveEvent() {
  const title = document.getElementById('ev-title').value.trim();
  const date  = document.getElementById('ev-date').value;
  if (!title || !date) { alert('Please add a title and date.'); return; }

  const startTime = document.getElementById('ev-start').value;
  const endTime   = document.getElementById('ev-end').value;
  const location  = document.getElementById('ev-location').value;
  const desc      = document.getElementById('ev-desc').value;

  // Build a local event object so it shows immediately
  const localEv = {
    event_id:   Date.now(), // temp id
    title,
    event_date: date,
    start_time: startTime ? startTime + ':00' : '00:00:00',
    end_time:   endTime   ? endTime   + ':00' : '00:00:00',
    location,
    description: JSON.stringify({ priority: selectedPriority, note: desc })
  };

  // Try to save to DB
  const body = new FormData();
  body.append('action',      'create_event');
  body.append('title',       title);
  body.append('event_date',  date);
  body.append('start_time',  startTime);
  body.append('end_time',    endTime);
  body.append('location',    location);
  body.append('description', desc);
  body.append('priority',    selectedPriority);

  try {
    const r = await fetch('calendar.php', { method:'POST', body });
    const contentType = r.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
      const d = await r.json();
      if (d.success) {
        localEv.event_id = d.id; // use real DB id
      }
    }
  } catch(e) {
    // No DB — continue with local event only
  }

  // Add to local events array and re-render (no full re-fetch needed)
  events.push(localEv);
  closeModal('createModal');
  if (currentView === 'month') renderMonth(viewDate.getFullYear(), viewDate.getMonth());
  else if (currentView === 'week') renderWeek(false);
  else if (currentView === 'workweek') renderWeek(true);
  else renderDay();
  updateMiniCal(viewDate.getFullYear(), viewDate.getMonth());
}

// ─── DETAIL MODAL ───
function showDetail(id) {
  const ev = events.find(e => e.event_id === id || +e.event_id === +id);
  if (!ev) return;
  activeEventId = id;
  const p = getPriority(ev);
  const c = COLORS[p] || COLORS.medium;
  document.getElementById('det-dot').style.background  = c.dot;
  document.getElementById('det-title').textContent     = ev.title;
  document.getElementById('det-date').textContent      = fmtDateLong(ev.event_date);
  const hasTime = ev.start_time && ev.start_time !== '00:00:00';
  const timeRow = document.getElementById('det-time-row');
  if (hasTime) {
    document.getElementById('det-time').textContent = fmtTime(ev.start_time) + (ev.end_time && ev.end_time !== '00:00:00' ? ' – ' + fmtTime(ev.end_time) : '');
    timeRow.style.display = 'flex';
  } else { timeRow.style.display = 'none'; }
  const locRow = document.getElementById('det-loc-row');
  if (ev.location) { document.getElementById('det-loc').textContent = ev.location; locRow.style.display = 'flex'; }
  else locRow.style.display = 'none';
  const note = getNote(ev);
  const descRow = document.getElementById('det-desc-row');
  if (note) { document.getElementById('det-desc').textContent = note; descRow.style.display = 'flex'; }
  else descRow.style.display = 'none';
  document.getElementById('detailModal').classList.add('open');
}
async function deleteEvent() {
  if (!activeEventId || !confirm('Delete this event?')) return;
  // Remove from local array immediately
  events = events.filter(e => +e.event_id !== +activeEventId);
  // Try DB delete
  try {
    const body = new FormData();
    body.append('action', 'delete_event');
    body.append('event_id', activeEventId);
    await fetch('calendar.php', { method:'POST', body });
  } catch(e) {}
  closeModal('detailModal');
  if (currentView === 'month') renderMonth(viewDate.getFullYear(), viewDate.getMonth());
  else if (currentView === 'week') renderWeek(false);
  else if (currentView === 'workweek') renderWeek(true);
  else renderDay();
  updateMiniCal(viewDate.getFullYear(), viewDate.getMonth());
}

function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Click outside to close
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

// ─── PROFILE DROPDOWN ───
function toggleDropdown() { document.getElementById('profileWrap').classList.toggle('open'); }
document.addEventListener('click', e => { if (!e.target.closest('#profileWrap')) document.getElementById('profileWrap').classList.remove('open'); });

// ─── KEYBOARD ───
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open')); }
  if (e.key === 'ArrowLeft' && !document.querySelector('.modal-overlay.open')) navigate(-1);
  if (e.key === 'ArrowRight' && !document.querySelector('.modal-overlay.open')) navigate(1);
  if ((e.key === 'n' || e.key === 'N') && !document.querySelector('.modal-overlay.open') && e.target.tagName !== 'INPUT') openCreateModal();
});

// ─── INIT ───
render();
</script>
</body>
</html>