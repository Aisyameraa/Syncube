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

$userId = $_SESSION['user_id'];

// ─────────────────────────────────────────────────────────────
// DATABASE CONNECTION
// Change these 3 values to match your real database.
// ─────────────────────────────────────────────────────────────
$DB_HOST = 'localhost';
$DB_NAME = 'syncube';   // <-- your actual database name
$DB_USER = 'root';      // <-- your actual DB username
$DB_PASS = '';          // <-- your actual DB password

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// Make sure the tables for the Project Board and Pinboard exist.
// (Safe to run on every request — IF NOT EXISTS makes it a no-op after the first time.)
$pdo->exec("CREATE TABLE IF NOT EXISTS kanban_cards (
    card_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    column_name ENUM('ongoing','incomplete','completed') NOT NULL,
    title VARCHAR(255) NOT NULL,
    tag VARCHAR(50) DEFAULT NULL,
    position INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_kanban_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS pinboard_notes (
    note_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    note_text TEXT NOT NULL,
    color VARCHAR(20) NOT NULL DEFAULT 'yellow',
    created_at DATETIME NOT NULL,
    INDEX idx_pinboard_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ─────────────────────────────────────────────────────────────
// HANDLE TASK & REMINDER FORM SUBMISSIONS (same page, no AJAX)
// ─────────────────────────────────────────────────────────────
$ajaxActions = ['add_kanban_card', 'move_kanban_card', 'delete_kanban_card', 'add_sticky', 'delete_sticky'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], $ajaxActions, true)) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false];

    if ($action === 'add_kanban_card') {
        $column = in_array($_POST['column'] ?? '', ['ongoing', 'incomplete', 'completed'], true) ? $_POST['column'] : null;
        $title  = trim($_POST['title'] ?? '');
        $tag    = trim($_POST['tag'] ?? '');
        if ($column && $title !== '') {
            $posStmt = $pdo->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM kanban_cards WHERE user_id = :uid AND column_name = :col');
            $posStmt->execute([':uid' => $userId, ':col' => $column]);
            $nextPos = (int)$posStmt->fetchColumn();

            $stmt = $pdo->prepare(
                "INSERT INTO kanban_cards (user_id, column_name, title, tag, position, created_at, updated_at)
                 VALUES (:uid, :col, :title, :tag, :pos, NOW(), NOW())"
            );
            $stmt->execute([
                ':uid'   => $userId,
                ':col'   => $column,
                ':title' => $title,
                ':tag'   => $tag !== '' ? $tag : null,
                ':pos'   => $nextPos,
            ]);
            $response = ['success' => true, 'card_id' => (int)$pdo->lastInsertId()];
        }
    } elseif ($action === 'move_kanban_card') {
        $cardId = (int)($_POST['card_id'] ?? 0);
        $column = in_array($_POST['column'] ?? '', ['ongoing', 'incomplete', 'completed'], true) ? $_POST['column'] : null;
        if ($cardId && $column) {
            $posStmt = $pdo->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM kanban_cards WHERE user_id = :uid AND column_name = :col');
            $posStmt->execute([':uid' => $userId, ':col' => $column]);
            $nextPos = (int)$posStmt->fetchColumn();

            $stmt = $pdo->prepare('UPDATE kanban_cards SET column_name = :col, position = :pos, updated_at = NOW() WHERE card_id = :id AND user_id = :uid');
            $stmt->execute([':col' => $column, ':pos' => $nextPos, ':id' => $cardId, ':uid' => $userId]);
            $response = ['success' => true];
        }
    } elseif ($action === 'delete_kanban_card') {
        $cardId = (int)($_POST['card_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM kanban_cards WHERE card_id = :id AND user_id = :uid');
        $stmt->execute([':id' => $cardId, ':uid' => $userId]);
        $response = ['success' => true];
    } elseif ($action === 'add_sticky') {
        $text  = trim($_POST['text'] ?? '');
        $color = in_array($_POST['color'] ?? '', ['yellow', 'green', 'pink', 'blue', 'lavender'], true) ? $_POST['color'] : 'yellow';
        if ($text !== '') {
            $stmt = $pdo->prepare('INSERT INTO pinboard_notes (user_id, note_text, color, created_at) VALUES (:uid, :text, :color, NOW())');
            $stmt->execute([':uid' => $userId, ':text' => $text, ':color' => $color]);
            $response = ['success' => true, 'note_id' => (int)$pdo->lastInsertId()];
        }
    } elseif ($action === 'delete_sticky') {
        $noteId = (int)($_POST['note_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM pinboard_notes WHERE note_id = :id AND user_id = :uid');
        $stmt->execute([':id' => $noteId, ':uid' => $userId]);
        $response = ['success' => true];
    }

    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_task') {
        $title    = trim($_POST['title'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', ['Low','Medium','High'], true) ? $_POST['priority'] : 'Medium';
        $category = trim($_POST['category'] ?? '');
        $due      = trim($_POST['due_date'] ?? '');
        $dueValue = null;
        if ($due !== '') {
            $ts = strtotime($due);
            if ($ts !== false) $dueValue = date('Y-m-d H:i:s', $ts);
        }
        if ($title !== '') {
            $stmt = $pdo->prepare(
                "INSERT INTO tasks (user_id, title, description, category, priority, status, due_date, created_at, updated_at)
                 VALUES (:uid, :title, '', :category, :priority, 'Pending', :due, NOW(), NOW())"
            );
            $stmt->execute([
                ':uid'      => $userId,
                ':title'    => $title,
                ':category' => $category !== '' ? $category : null,
                ':priority' => $priority,
                ':due'      => $dueValue,
            ]);
        }
    } elseif ($action === 'toggle_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT status FROM tasks WHERE task_id = :id AND user_id = :uid');
        $stmt->execute([':id' => $taskId, ':uid' => $userId]);
        $row = $stmt->fetch();
        if ($row) {
            $newStatus = $row['status'] === 'Completed' ? 'Pending' : 'Completed';
            $upd = $pdo->prepare('UPDATE tasks SET status = :status, updated_at = NOW() WHERE task_id = :id AND user_id = :uid');
            $upd->execute([':status' => $newStatus, ':id' => $taskId, ':uid' => $userId]);
        }
    } elseif ($action === 'delete_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE task_id = :id AND user_id = :uid');
        $stmt->execute([':id' => $taskId, ':uid' => $userId]);
    } elseif ($action === 'clear_done_tasks') {
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE user_id = :uid AND status = 'Completed'");
        $stmt->execute([':uid' => $userId]);
    } elseif ($action === 'add_reminder') {
        $title   = trim($_POST['title'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $when    = trim($_POST['reminder_datetime'] ?? '');
        if ($title !== '' && $when !== '') {
            $ts = strtotime($when);
            if ($ts !== false) {
                $stmt = $pdo->prepare(
                    "INSERT INTO reminders (user_id, title, message, reminder_datetime, status, created_at)
                     VALUES (:uid, :title, :message, :dt, 'Pending', NOW())"
                );
                $stmt->execute([
                    ':uid'     => $userId,
                    ':title'   => $title,
                    ':message' => $message,
                    ':dt'      => date('Y-m-d H:i:s', $ts),
                ]);
            }
        }
    } elseif ($action === 'delete_reminder') {
        $id = (int)($_POST['reminder_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM reminders WHERE reminder_id = :id AND user_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
    }

    // Redirect back so refreshing the page never re-submits the form
    header('Location: workspace.php');
    exit;
}

// Auto-mark any reminder whose time has passed as "Sent"
$pdo->prepare("UPDATE reminders SET status = 'Sent' WHERE user_id = :uid AND status = 'Pending' AND reminder_datetime <= NOW()")
    ->execute([':uid' => $userId]);

// ─────────────────────────────────────────────────────────────
// FETCH TASKS & REMINDERS FOR DISPLAY
// ─────────────────────────────────────────────────────────────
$tasksStmt = $pdo->prepare(
    "SELECT * FROM tasks WHERE user_id = :uid
     ORDER BY (status = 'Completed') ASC, due_date IS NULL, due_date ASC, created_at DESC"
);
$tasksStmt->execute([':uid' => $userId]);
$tasks = $tasksStmt->fetchAll();

$remindersStmt = $pdo->prepare(
    "SELECT * FROM reminders WHERE user_id = :uid ORDER BY reminder_datetime ASC"
);
$remindersStmt->execute([':uid' => $userId]);
$reminders = $remindersStmt->fetchAll();

$pendingTaskCount = 0;
foreach ($tasks as $t) { if ($t['status'] !== 'Completed') $pendingTaskCount++; }

// ─────────────────────────────────────────────────────────────
// FETCH PROJECT BOARD (KANBAN) & PINBOARD NOTES FOR DISPLAY
// ─────────────────────────────────────────────────────────────
$kanbanStmt = $pdo->prepare('SELECT * FROM kanban_cards WHERE user_id = :uid ORDER BY column_name, position ASC');
$kanbanStmt->execute([':uid' => $userId]);
$kanbanData = ['ongoing' => [], 'incomplete' => [], 'completed' => []];
foreach ($kanbanStmt->fetchAll() as $row) {
    $kanbanData[$row['column_name']][] = [
        'id'    => (int)$row['card_id'],
        'title' => $row['title'],
        'tag'   => $row['tag'],
    ];
}

$stickiesStmt = $pdo->prepare('SELECT * FROM pinboard_notes WHERE user_id = :uid ORDER BY created_at DESC');
$stickiesStmt->execute([':uid' => $userId]);
$stickiesData = [];
foreach ($stickiesStmt->fetchAll() as $row) {
    $stickiesData[] = [
        'id'    => (int)$row['note_id'],
        'text'  => $row['note_text'],
        'color' => $row['color'],
        'ts'    => strtotime($row['created_at']) * 1000,
    ];
}

// ─────────────────────────────────────────────────────────────
// WORKSPACE SNAPSHOT (used in the bottom-grid summary card)
// ─────────────────────────────────────────────────────────────
$totalTaskCount     = count($tasks);
$completedTaskCount = $totalTaskCount - $pendingTaskCount;
$completionPercent  = $totalTaskCount > 0 ? round(($completedTaskCount / $totalTaskCount) * 100) : 0;

$priorityCounts = ['High' => 0, 'Medium' => 0, 'Low' => 0];
foreach ($tasks as $t) {
    if ($t['status'] !== 'Completed' && isset($priorityCounts[$t['priority']])) {
        $priorityCounts[$t['priority']]++;
    }
}

$nextReminder = null;
foreach ($reminders as $r) {
    if ($r['status'] === 'Pending') { $nextReminder = $r; break; }
}

// ... rest of your dashboard code

$quotes = [
    "Small steps every day lead to big changes over time.",
    "You don't have to be perfect, you just have to be present.",
    "Rest is not quitting — it's part of the journey.",
    "One task at a time. Breathe. You've got this.",
    "Progress, not perfection.",
];
$quote = $quotes[date('N') % count($quotes)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>SYNCUBE — Workspace</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet"/>
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
  --accent-warm: #c4a882;
  --accent-blush: #d4b5a8;
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

html { scroll-behavior: smooth; }

body {
  font-family: var(--ff-body);
  background: var(--cream);
  color: var(--text-dark);
  min-height: 100vh;
  font-size: 14px;
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden; /* NO horizontal scroll */
}

/* ─── SCROLLBAR ─── */
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--stone-light); border-radius: 10px; }

/* ─── NAV ─── */
nav {
  position: fixed;
  top: 0; left: 0; right: 0;
  height: var(--nav-height);
  background: rgba(250,248,245,0.92);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 36px;
  z-index: 100;
}

.nav-logo {
  font-family: var(--ff-display);
  font-size: 22px;
  font-weight: 400;
  color: var(--text-dark);
  letter-spacing: 0.08em;
  text-decoration: none;
  display: flex; align-items: center; gap: 8px;
}
.nav-logo span {
  display: inline-block;
  width: 7px; height: 7px;
  border-radius: 50%;
  background: var(--sage);
}

.nav-links { display: flex; align-items: center; gap: 4px; list-style: none; }

.nav-links a {
  font-family: var(--ff-body);
  font-size: 13px; font-weight: 400;
  color: var(--text-mid);
  text-decoration: none;
  padding: 6px 14px;
  border-radius: var(--radius-sm);
  transition: all var(--transition);
  letter-spacing: 0.01em;
}
.nav-links a:hover, .nav-links a.active { color: var(--text-dark); background: var(--stone-light); }
.nav-links a.active { font-weight: 500; }

.profile-wrap { position: relative; }

.profile-btn {
  display: flex; align-items: center; gap: 9px;
  cursor: pointer;
  padding: 6px 12px 6px 6px;
  border-radius: 40px;
  border: 1px solid var(--border);
  background: var(--warm-white);
  transition: all var(--transition);
  font-family: var(--ff-body); font-size: 13px;
  color: var(--text-mid); font-weight: 400;
  user-select: none;
}
.profile-btn:hover { background: var(--stone-light); border-color: rgba(44,42,39,0.14); }

.profile-avatar {
  width: 30px; height: 30px; border-radius: 50%;
  background: var(--sage-light);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 500;
  color: var(--accent); letter-spacing: 0.05em; flex-shrink: 0;
}

.chevron { width: 14px; height: 14px; opacity: 0.45; transition: transform var(--transition); }
.profile-wrap.open .chevron { transform: rotate(180deg); }

.dropdown {
  position: absolute; top: calc(100% + 8px); right: 0;
  background: var(--warm-white);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-soft);
  min-width: 180px; padding: 8px;
  opacity: 0; transform: translateY(-6px);
  pointer-events: none;
  transition: all var(--transition); z-index: 200;
}
.profile-wrap.open .dropdown { opacity: 1; transform: translateY(0); pointer-events: all; }
.dropdown-header { padding: 8px 10px 10px; border-bottom: 1px solid var(--border); margin-bottom: 6px; }
.dropdown-header strong { display: block; font-size: 13px; font-weight: 500; color: var(--text-dark); }
.dropdown-header span { font-size: 11px; color: var(--text-muted); }
.dropdown a { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 8px; font-size: 13px; color: var(--text-mid); text-decoration: none; transition: all var(--transition); }
.dropdown a:hover { background: var(--stone-light); color: var(--text-dark); }
.dropdown a.logout { color: #b85c5c; }
.dropdown a.logout:hover { background: #fdf0f0; color: #a34444; }
.dropdown .divider { height: 1px; background: var(--border); margin: 6px 0; }

/* ─── MAIN ─── */
main {
  margin-top: var(--nav-height);
  padding: 40px 36px 60px;
  max-width: 1200px;
  margin-left: auto;
  margin-right: auto;
  width: 100%;
}

/* ─── GREETING ─── */
.greeting-row {
  display: flex; align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 36px;
}
.greeting-text h1 {
  font-family: var(--ff-display);
  font-size: 38px; font-weight: 300;
  color: var(--text-dark); line-height: 1.15;
  letter-spacing: -0.01em;
}
.greeting-text h1 em { font-style: italic; color: var(--accent); }
.greeting-text p {
  font-size: 16px; color: var(--text-muted);
  margin-top: 6px; font-style: italic;
  font-family: var(--ff-display); font-weight: 300;
}
.date-display { text-align: right; flex-shrink: 0; }
#live-time {
  font-family: var(--ff-display); font-size: 46px;
  font-weight: 300; color: var(--text-dark);
  line-height: 1; letter-spacing: -0.02em;
}
#live-date {
  font-size: 12px; color: var(--text-muted);
  margin-top: 4px; letter-spacing: 0.08em;
  text-transform: uppercase; font-weight: 400;
}

/* ─── SECTION LABEL ─── */
.section-label {
  font-size: 10px; font-weight: 500;
  letter-spacing: 0.14em; text-transform: uppercase;
  color: var(--text-muted); margin-bottom: 14px;
  display: flex; align-items: center; gap: 8px;
}
.section-label::before {
  content: ''; display: inline-block;
  width: 4px; height: 4px; border-radius: 50%;
  background: var(--sage); flex-shrink: 0;
}

/* ─── CARDS ─── */
.card {
  background: var(--warm-white);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 24px;
  box-shadow: var(--shadow-card);
  transition: box-shadow var(--transition);
}
.card:hover { box-shadow: 0 4px 24px rgba(44,42,39,0.09); }

.card-label {
  font-size: 10px; font-weight: 500;
  letter-spacing: 0.12em; text-transform: uppercase;
  color: var(--text-muted); margin-bottom: 14px;
  display: flex; align-items: center; gap: 6px;
}
.card-label::before {
  content: ''; display: inline-block;
  width: 4px; height: 4px; border-radius: 50%;
  background: var(--sage); flex-shrink: 0;
}

/* ─── BUTTONS ─── */
.btn {
  padding: 8px 20px;
  border-radius: 40px;
  border: 1px solid var(--border);
  background: var(--cream); color: var(--text-mid);
  font-family: var(--ff-body); font-size: 12px;
  font-weight: 400; cursor: pointer;
  transition: all var(--transition); letter-spacing: 0.03em;
}
.btn:hover { background: var(--stone-light); color: var(--text-dark); border-color: rgba(44,42,39,0.15); }
.btn.primary { background: var(--accent); color: #fff; border-color: var(--accent); }
.btn.primary:hover { background: #6d7f70; }
.btn.sm { padding: 5px 14px; font-size: 11px; }
.btn.icon { padding: 6px 10px; }

/* ─── WORKSPACE TOP GRID ─── */
.top-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 18px;
  margin-bottom: 28px;
}

/* ─── TODO CARD ─── */
.todo-card { grid-column: 1 / 3; }

.todo-input-row { display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
.todo-input-row:last-of-type { margin-bottom: 16px; }
.todo-input-row input, .todo-input-row select {
  flex: 1; padding: 9px 14px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--cream);
  font-family: var(--ff-body); font-size: 13px;
  color: var(--text-dark); outline: none;
  transition: border-color var(--transition);
  min-width: 0;
}
.todo-input-row input:focus, .todo-input-row select:focus { border-color: var(--sage); }
.todo-input-row select { flex: 1 1 110px; color: var(--text-muted); }
.todo-input-row input[type="datetime-local"] { flex: 1 1 170px; color: var(--text-muted); }

.todo-list { display: flex; flex-direction: column; gap: 7px; max-height: 280px; overflow-y: auto; }

.todo-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 13px; border-radius: 11px;
  background: var(--cream); border: 1px solid var(--border-soft);
  transition: all var(--transition); cursor: default;
  user-select: none;
}
.todo-item:hover { background: var(--sage-pale); border-color: var(--border); }
.todo-item.done { opacity: 0.45; }
.todo-item.done .todo-text { text-decoration: line-through; }

.todo-check {
  width: 17px; height: 17px; border-radius: 50%;
  border: 1.5px solid var(--stone); background: transparent;
  cursor: pointer; transition: all var(--transition);
  flex-shrink: 0; display: flex; align-items: center; justify-content: center;
  padding: 0; appearance: none; -webkit-appearance: none;
}
.todo-check.checked { background: var(--sage); border-color: var(--sage); }
.todo-check.checked::after {
  content: ''; display: block;
  width: 4px; height: 8px;
  border: 1.5px solid #fff;
  border-top: none; border-left: none;
  transform: rotate(45deg) translateY(-1px);
}
.todo-text { flex: 1; font-size: 13px; color: var(--text-dark); }
.todo-priority { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.todo-priority.high { background: #c4856a; }
.todo-priority.medium { background: #8aab8d; }
.todo-priority.low { background: #b0bfb1; }
.todo-del {
  background: none; border: none; cursor: pointer;
  color: var(--text-muted); opacity: 0; padding: 2px 5px;
  border-radius: 6px; font-size: 13px; transition: all var(--transition);
}
.todo-item:hover .todo-del { opacity: 1; }
.todo-del:hover { background: #f5ddd9; color: #b85c5c; }
.todo-footer {
  display: flex; align-items: center;
  justify-content: space-between;
  margin-top: 12px; font-size: 11px; color: var(--text-muted);
}

/* ─── REMINDERS CARD ─── */
.reminders-card {}
.reminder-input-group { display: flex; flex-direction: column; gap: 7px; margin-bottom: 14px; }
.reminder-input-group input {
  width: 100%; padding: 9px 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--cream);
  font-family: var(--ff-body); font-size: 12px;
  color: var(--text-dark); outline: none;
  transition: border-color var(--transition);
}
.reminder-input-group input:focus { border-color: var(--sage); }
.reminder-input-group textarea {
  width: 100%; padding: 9px 12px; min-height: 44px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--cream);
  font-family: var(--ff-body); font-size: 12px;
  color: var(--text-dark); outline: none; resize: none;
  transition: border-color var(--transition);
}
.reminder-input-group textarea:focus { border-color: var(--sage); }
.reminder-list { display: flex; flex-direction: column; gap: 7px; max-height: 180px; overflow-y: auto; }
.reminder-item {
  display: flex; align-items: flex-start; gap: 9px;
  padding: 9px 11px; border-radius: 11px;
  background: var(--cream); border: 1px solid var(--border-soft);
  transition: all var(--transition);
}
.reminder-item:hover { background: var(--sage-pale); border-color: var(--border); }
.reminder-bell { font-size: 13px; flex-shrink: 0; margin-top: 1px; }
.reminder-info { flex: 1; min-width: 0; }
.reminder-title { font-size: 12px; color: var(--text-dark); }
.reminder-time { font-size: 10px; color: var(--text-muted); margin-top: 1px; }
.reminder-del {
  background: none; border: none; cursor: pointer;
  color: var(--text-muted); opacity: 0;
  padding: 2px 4px; border-radius: 5px;
  font-size: 12px; transition: all var(--transition); flex-shrink: 0;
}
.reminder-item:hover .reminder-del { opacity: 1; }
.reminder-del:hover { background: #f5ddd9; color: #b85c5c; }

/* ═══════════════════════════════════════
   KANBAN BOARD
═══════════════════════════════════════ */
.kanban-section { margin-bottom: 28px; }
.kanban-board { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }

.kanban-col {
  background: var(--warm-white);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 20px;
  box-shadow: var(--shadow-card);
  min-height: 280px;
}

.kanban-col-header {
  display: flex; align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
}

.kanban-col-title {
  font-size: 11px; font-weight: 500;
  letter-spacing: 0.1em; text-transform: uppercase;
  display: flex; align-items: center; gap: 8px;
}

.col-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.col-dot.ongoing { background: #c4a882; }
.col-dot.incomplete { background: #c4856a; }
.col-dot.completed { background: #8aab8d; }

.col-count {
  font-size: 10px; font-weight: 400;
  background: var(--stone-light); color: var(--text-muted);
  padding: 1px 8px; border-radius: 20px;
}

.kanban-cards { display: flex; flex-direction: column; gap: 9px; min-height: 60px; }

.kanban-card {
  background: var(--cream);
  border: 1px solid var(--border-soft);
  border-radius: 12px; padding: 12px 14px;
  cursor: grab; transition: all var(--transition);
  position: relative;
}
.kanban-card:hover { box-shadow: 0 4px 16px rgba(44,42,39,0.1); border-color: var(--border); }
.kanban-card.dragging { opacity: 0.5; cursor: grabbing; }
.kanban-card-title { font-size: 13px; color: var(--text-dark); margin-bottom: 6px; }
.kanban-card-meta { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.kanban-tag {
  font-size: 10px; padding: 2px 8px; border-radius: 20px;
  background: var(--stone-light); color: var(--text-mid);
}
.kanban-tag.work { background: #e8f0e8; color: var(--accent); }
.kanban-tag.personal { background: #f0ebe6; color: var(--stone); }
.kanban-tag.urgent { background: #faeae5; color: #c4856a; }
.kanban-tag.learning { background: #eaeef8; color: #7080a8; }

.kanban-due { font-size: 10px; color: var(--text-muted); }
.kanban-card-del {
  position: absolute; top: 8px; right: 8px;
  background: none; border: none;
  font-size: 12px; color: var(--text-muted);
  cursor: pointer; opacity: 0; padding: 2px 5px;
  border-radius: 4px; transition: all var(--transition);
}
.kanban-card:hover .kanban-card-del { opacity: 1; }
.kanban-card-del:hover { background: #f5ddd9; color: #b85c5c; }

.kanban-add {
  margin-top: 10px;
  display: flex; flex-direction: column; gap: 7px;
}
.kanban-add input, .kanban-add select {
  width: 100%; padding: 7px 10px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--cream);
  font-family: var(--ff-body); font-size: 12px;
  color: var(--text-dark); outline: none;
  transition: border-color var(--transition);
}
.kanban-add input:focus, .kanban-add select:focus { border-color: var(--sage); }

.drop-zone { border: 2px dashed var(--sage-light); border-radius: 12px; padding: 10px; opacity: 0; transition: opacity 0.2s; }
.drop-zone.over { opacity: 1; }

/* ═══════════════════════════════════════
   CORKBOARD (Sticky Notes)
═══════════════════════════════════════ */
.corkboard-section { margin-bottom: 28px; }

.corkboard {
  background:
    repeating-linear-gradient(
      0deg,
      rgba(0,0,0,0.015) 0px, rgba(0,0,0,0.015) 1px,
      transparent 1px, transparent 32px
    ),
    repeating-linear-gradient(
      90deg,
      rgba(0,0,0,0.015) 0px, rgba(0,0,0,0.015) 1px,
      transparent 1px, transparent 32px
    ),
    linear-gradient(135deg, #c8a882 0%, #b8956e 30%, #c4a07a 60%, #b89060 100%);
  border-radius: var(--radius-lg);
  padding: 28px;
  min-height: 320px;
  box-shadow: inset 0 2px 8px rgba(0,0,0,0.12), 0 4px 20px rgba(0,0,0,0.08);
  border: 2px solid #a07848;
  position: relative;
}

/* Wood grain texture overlay */
.corkboard::before {
  content: '';
  position: absolute; inset: 0;
  border-radius: var(--radius-lg);
  background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3CfeColorMatrix type='saturate' values='0'/%3E%3C/filter%3E%3Crect width='200' height='200' filter='url(%23noise)' opacity='0.06'/%3E%3C/svg%3E");
  pointer-events: none;
}

.corkboard-header {
  display: flex; align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  position: relative; z-index: 1;
}

.corkboard-title {
  font-family: var(--ff-display);
  font-size: 18px; font-weight: 400;
  color: #5c3c18;
  letter-spacing: 0.04em;
  text-shadow: 0 1px 2px rgba(255,255,255,0.3);
}

.sticky-add-form {
  display: flex; gap: 8px; align-items: flex-end;
  position: relative; z-index: 1;
  margin-bottom: 20px;
}

.sticky-add-form textarea {
  flex: 1; padding: 9px 12px; height: 48px;
  border: 1px solid rgba(160,120,72,0.35);
  border-radius: 8px;
  background: rgba(255,255,255,0.55);
  font-family: var(--ff-body); font-size: 12px;
  color: var(--text-dark); resize: none; outline: none;
  transition: border-color var(--transition);
  backdrop-filter: blur(4px);
}
.sticky-add-form textarea:focus { border-color: rgba(160,120,72,0.7); background: rgba(255,255,255,0.7); }

.sticky-colors { display: flex; gap: 5px; }
.scp {
  width: 18px; height: 18px; border-radius: 50%;
  cursor: pointer; border: 2px solid transparent;
  transition: all var(--transition);
}
.scp:hover, .scp.sel { border-color: rgba(92,60,24,0.5); transform: scale(1.2); }
.scp.yellow { background: #fef3c7; }
.scp.green  { background: #d1fae5; }
.scp.pink   { background: #fce7f3; }
.scp.blue   { background: #dbeafe; }
.scp.lavender { background: #ede9fe; }

/* Sticky note grid */
.stickies-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  position: relative; z-index: 1;
}

.sticky-note {
  width: 152px; min-height: 140px;
  padding: 28px 14px 14px;
  border-radius: 3px;
  font-family: var(--ff-body); font-size: 12px;
  color: #3a2a18;
  line-height: 1.55;
  position: relative;
  box-shadow:
    2px 3px 6px rgba(0,0,0,0.18),
    inset 0 -1px 0 rgba(0,0,0,0.05);
  cursor: default;
  transition: transform var(--transition), box-shadow var(--transition);
  word-break: break-word;
}

.sticky-note:hover {
  transform: rotate(0deg) scale(1.03) translateY(-2px);
  box-shadow: 4px 8px 18px rgba(0,0,0,0.22);
  z-index: 2;
}

/* Random tilt classes */
.sticky-note.tilt-1 { transform: rotate(-2.2deg); }
.sticky-note.tilt-2 { transform: rotate(1.5deg); }
.sticky-note.tilt-3 { transform: rotate(-0.8deg); }
.sticky-note.tilt-4 { transform: rotate(2.4deg); }
.sticky-note.tilt-5 { transform: rotate(-1.6deg); }

.sticky-note.tilt-1:hover { transform: rotate(0deg) scale(1.03) translateY(-2px); }
.sticky-note.tilt-2:hover { transform: rotate(0deg) scale(1.03) translateY(-2px); }
.sticky-note.tilt-3:hover { transform: rotate(0deg) scale(1.03) translateY(-2px); }
.sticky-note.tilt-4:hover { transform: rotate(0deg) scale(1.03) translateY(-2px); }
.sticky-note.tilt-5:hover { transform: rotate(0deg) scale(1.03) translateY(-2px); }

/* Pushpin */
.sticky-note::before {
  content: '';
  position: absolute;
  top: -5px; left: 50%; transform: translateX(-50%);
  width: 14px; height: 14px;
  border-radius: 50%;
  background: radial-gradient(circle at 35% 35%, #e8504a, #a83030);
  box-shadow: 0 2px 5px rgba(0,0,0,0.35), inset 0 1px 2px rgba(255,255,255,0.3);
  z-index: 3;
}

/* Alternate pin colours */
.sticky-note:nth-child(3n)::before { background: radial-gradient(circle at 35% 35%, #4a90e2, #2a5fb8); }
.sticky-note:nth-child(5n)::before { background: radial-gradient(circle at 35% 35%, #7ec86e, #3a8a2a); }

.sticky-note.yellow   { background: linear-gradient(160deg, #fef9c3, #fef08a); }
.sticky-note.green    { background: linear-gradient(160deg, #d1fae5, #a7f3d0); }
.sticky-note.pink     { background: linear-gradient(160deg, #fce7f3, #fbcfe8); }
.sticky-note.blue     { background: linear-gradient(160deg, #dbeafe, #bfdbfe); }
.sticky-note.lavender { background: linear-gradient(160deg, #ede9fe, #ddd6fe); }

.sticky-note-del {
  position: absolute; top: 6px; right: 6px;
  background: none; border: none;
  font-size: 12px; color: rgba(92,60,24,0.4);
  cursor: pointer; opacity: 0; padding: 2px 4px;
  border-radius: 4px; transition: all var(--transition);
}
.sticky-note:hover .sticky-note-del { opacity: 1; }
.sticky-note-del:hover { background: rgba(184,92,92,0.15); color: #a34444; }

.sticky-note-time {
  font-size: 9px; color: rgba(92,60,24,0.45);
  margin-top: 8px; display: block;
}

.corkboard-empty {
  color: rgba(92,60,24,0.4);
  font-size: 13px; font-style: italic;
  text-align: center;
  padding: 20px 0;
  font-family: var(--ff-display);
}

/* ═══════════════════════════════════════
   BOTTOM GRID
═══════════════════════════════════════ */
.bottom-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 18px;
  margin-bottom: 28px;
}

/* ─── FOCUS SESSION ─── */
.focus-card {}
.focus-modes {
  display: flex; gap: 6px;
  margin-bottom: 14px; flex-wrap: wrap;
}
.focus-mode-btn {
  padding: 5px 12px; border-radius: 40px;
  border: 1px solid var(--border);
  background: var(--cream);
  font-size: 11px; color: var(--text-muted);
  cursor: pointer; transition: all var(--transition);
  font-family: var(--ff-body);
}
.focus-mode-btn:hover, .focus-mode-btn.active {
  background: var(--sage-pale); color: var(--accent);
  border-color: var(--sage-light);
}
.focus-bar { height: 3px; background: var(--stone-light); border-radius: 10px; margin: 10px 0 12px; overflow: hidden; }
.focus-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--accent) 0%, var(--sage-light) 100%);
  border-radius: 10px; transition: width 1s linear; width: 100%;
}
.focus-timer {
  font-family: var(--ff-display); font-size: 50px;
  font-weight: 300; color: var(--text-dark);
  text-align: center; letter-spacing: -0.01em;
  line-height: 1; margin: 4px 0;
}
.focus-lbl {
  text-align: center; font-size: 10px;
  color: var(--text-muted); margin-bottom: 12px;
  letter-spacing: 0.06em; text-transform: uppercase;
}
.focus-controls { display: flex; justify-content: center; gap: 8px; }
.session-dots { display: flex; gap: 5px; justify-content: center; margin-top: 12px; }
.sdot {
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--stone-light); transition: background var(--transition);
}
.sdot.done { background: var(--sage); }


/* ─── NOTES CARD ─── */
.notes-card {}
.notes-tabs { display: flex; gap: 4px; margin-bottom: 12px; flex-wrap: wrap; }
.note-tab {
  padding: 4px 12px; border-radius: 40px;
  border: 1px solid var(--border); background: var(--cream);
  font-size: 11px; color: var(--text-muted);
  cursor: pointer; transition: all var(--transition);
  font-family: var(--ff-body); white-space: nowrap; display: flex; align-items: center; gap: 5px;
}
.note-tab:hover, .note-tab.active { background: var(--sage-pale); color: var(--accent); border-color: var(--sage-light); }
.tab-del { font-size: 10px; opacity: 0.5; }
.tab-del:hover { opacity: 1; }
.notes-editor {
  width: 100%; height: 152px; padding: 12px;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm); background: var(--cream);
  font-family: var(--ff-body); font-size: 13px;
  color: var(--text-dark); resize: none; outline: none;
  line-height: 1.65; transition: border-color var(--transition);
}
.notes-editor:focus { border-color: var(--sage); }
.notes-footer {
  display: flex; align-items: center;
  justify-content: space-between;
  margin-top: 10px; font-size: 11px; color: var(--text-muted);
}

/* ─── WORKSPACE SNAPSHOT ─── */
.snapshot-card { display: flex; flex-direction: column; }
.snapshot-progress-top {
  display: flex; align-items: center; justify-content: space-between;
  font-size: 11px; color: var(--text-mid); margin-bottom: 7px;
}
.snapshot-progress-pct { font-weight: 500; color: var(--text-dark); font-size: 13px; }
.snapshot-bar { height: 6px; background: rgba(138,158,140,0.2); border-radius: 10px; overflow: hidden; }
.snapshot-bar-fill {
  height: 100%; border-radius: 10px;
  background: linear-gradient(90deg, var(--accent) 0%, var(--sage-light) 100%);
  transition: width 0.5s ease;
}
.snapshot-progress-sub { font-size: 10px; color: var(--text-muted); margin-top: 6px; }

.snapshot-priorities {
  display: flex; gap: 16px; flex-wrap: wrap;
  margin: 18px 0; padding: 14px 0;
  border-top: 1px solid rgba(138,158,140,0.2);
  border-bottom: 1px solid rgba(138,158,140,0.2);
}
.snapshot-priority-item {
  display: flex; align-items: center; gap: 6px;
  font-size: 12px; color: var(--text-mid);
}
.snapshot-priority-item strong { color: var(--text-dark); font-weight: 500; }

.snapshot-next-label {
  font-size: 10px; color: var(--text-muted);
  text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px;
}
.snapshot-next-title { font-size: 13px; color: var(--text-dark); }
.snapshot-next-time { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
.snapshot-next-empty { font-size: 12px; color: var(--text-muted); font-style: italic; }

/* ═══════════════════════════════════════
   MOTIVATION (replaces the old Spotify box — same size/style)
═══════════════════════════════════════ */
.motivation-section { margin-bottom: 28px; }

.motivation-card {
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
  border: 1px solid rgba(196,168,130,0.18);
  border-radius: var(--radius-lg);
  padding: 28px;
  color: #fff;
  box-shadow: 0 8px 32px rgba(0,0,0,0.2);
  min-height: 240px;
  display: flex; flex-direction: column;
}

.motivation-card .card-label {
  color: rgba(255,255,255,0.45);
  margin-bottom: 20px;
}

.motivation-card .card-label::before { background: var(--accent-warm); }

.motivation-body {
  flex: 1;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 22px; text-align: center; padding: 10px 0 6px;
}

.motivation-icon { font-size: 26px; opacity: 0.85; }

.motivation-text {
  font-family: var(--ff-display);
  font-size: 26px; font-weight: 300; font-style: italic;
  color: #fff; line-height: 1.5; max-width: 560px;
  letter-spacing: 0.01em;
}

.btn-motivation {
  display: flex; align-items: center; gap: 8px;
  padding: 11px 24px; border-radius: 40px;
  background: var(--accent-warm); color: #1a1a2e;
  font-family: var(--ff-body); font-size: 13px;
  font-weight: 500; border: none; cursor: pointer;
  transition: all 0.2s; letter-spacing: 0.02em;
}
.btn-motivation:hover { background: #d4b995; transform: scale(1.02); }

.motivation-disclaimer {
  margin-top: 14px;
  font-size: 10px; color: rgba(255,255,255,0.25);
  text-align: center; line-height: 1.5;
}

/* ─── QUOTE BAR ─── */
.quote-card {
  grid-column: 1 / 4;
  background: transparent; border: none; box-shadow: none;
  padding: 0 0 6px; text-align: center;
  display: flex; align-items: center; justify-content: center; gap: 14px;
}
.quote-card::before, .quote-card::after {
  content: '—'; color: var(--stone);
  font-family: var(--ff-display); font-size: 16px; opacity: 0.4;
}
.quote-card blockquote {
  font-family: var(--ff-display); font-size: 17px;
  font-weight: 300; font-style: italic;
  color: var(--text-mid); letter-spacing: 0.01em;
  max-width: 600px; line-height: 1.6;
}

/* ─── NOTIFICATION ─── */
.toast {
  position: fixed; top: 80px; right: 24px;
  background: var(--warm-white);
  border: 1px solid var(--sage-light);
  border-radius: var(--radius-md);
  padding: 16px 20px;
  box-shadow: 0 8px 32px rgba(44,42,39,0.12);
  max-width: 280px; z-index: 300;
  display: none; animation: slideIn 0.3s ease;
}
.toast.show { display: block; }
@keyframes slideIn {
  from { opacity: 0; transform: translateX(20px); }
  to   { opacity: 1; transform: translateX(0); }
}
.bn-title { font-size: 13px; font-weight: 500; color: var(--text-dark); margin-bottom: 4px; }
.bn-body  { font-size: 12px; color: var(--text-mid); line-height: 1.5; margin-bottom: 12px; }
.bn-actions { display: flex; gap: 8px; }
.bn-btn {
  padding: 6px 14px; border-radius: 40px;
  border: 1px solid var(--border); background: transparent;
  font-size: 11px; font-family: var(--ff-body);
  color: var(--text-mid); cursor: pointer; transition: all var(--transition);
}
.bn-btn.primary { background: var(--sage); color: #fff; border-color: var(--sage); }
.bn-btn.primary:hover { background: #7a8f7c; }
.bn-btn:hover { background: var(--stone-light); }

/* ─── RESPONSIVE ─── */
@media (max-width: 900px) {
  nav { padding: 0 18px; }
  main { padding: 30px 18px 50px; }
  .top-grid { grid-template-columns: 1fr 1fr; gap: 14px; }
  .todo-card { grid-column: 1 / 3; }
  .kanban-board { grid-template-columns: 1fr; gap: 12px; }
  .bottom-grid { grid-template-columns: 1fr 1fr; gap: 14px; }
  .greeting-text h1 { font-size: 28px; }
  #live-time { font-size: 34px; }
}

@media (max-width: 640px) {
  .nav-links { display: none; }
  .top-grid, .bottom-grid { grid-template-columns: 1fr; }
  .todo-card { grid-column: 1; }
  .kanban-board { grid-template-columns: 1fr; }
  .bottom-grid > * { grid-column: 1; }
}
</style>
</head>
<body>

<!-- ─── NAVIGATION ─── -->
<nav>
  <a href="dashboard.php" class="nav-logo"><span></span> syncube</a>
  <ul class="nav-links">
    <li><a href="dashboard.php">Dashboard</a></li>
    <li><a href="workspace.php" class="active">Workspace</a></li>
    <li><a href="calendar.php">Calendar</a></li>
    <li><a href="journal.php">Journal</a></li>
    <li>
      <div class="profile-wrap" id="profileWrap">
        <div class="profile-btn" onclick="toggleDropdown()">
          <div class="profile-avatar"><?= htmlspecialchars($user['initials']) ?></div>
          <?= htmlspecialchars($user['name']) ?>
          <svg class="chevron" viewBox="0 0 16 16" fill="none">
            <path d="M4 6L8 10L12 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="dropdown" id="dropdown">
          <div class="dropdown-header">
            <strong><?= htmlspecialchars($user['name']) ?></strong>
            <span><?= htmlspecialchars($user['role']) ?> Account</span>
          </div>
          <a href="profile.php">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5" r="3" stroke="currentColor" stroke-width="1.4"/><path d="M2 13c0-3.3 2.7-5 6-5s6 1.7 6 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            My Profile
          </a>
          <div class="divider"></div>
          <a href="logout.html" class="logout">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M6 2H3a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3M10 11l3-3-3-3M13 8H6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Log out
          </a>
        </div>
      </div>
    </li>
  </ul>
</nav>

<!-- ─── TOAST ─── -->
<div class="toast" id="toast">
  <div class="bn-title" id="toast-title">Reminder</div>
  <div class="bn-body" id="toast-body"></div>
  <div class="bn-actions">
    <button class="bn-btn primary" onclick="dismissToast()">Got it</button>
    <button class="bn-btn" onclick="dismissToast()">Dismiss</button>
  </div>
</div>

<!-- ─── MAIN ─── -->
<main>

  <!-- Greeting -->
  <div class="greeting-row">
    <div class="greeting-text">
      <h1>Your <em>Workspace</em>.</h1>
      <p>Everything you need, all in one place.</p>
    </div>
    <div class="date-display">
      <div id="live-time">—</div>
      <div id="live-date">—</div>
    </div>
  </div>

    <!-- ── CORKBOARD ── -->
  <div class="corkboard-section">
    <div class="section-label">Pinboard</div>
    <div class="corkboard">
      <div class="corkboard-header">
        <div class="corkboard-title">📌 My Board</div>
      </div>
      <div class="sticky-add-form">
        <textarea id="sticky-input" placeholder="Write a note and pin it to the board…"></textarea>
        <div style="display:flex;flex-direction:column;gap:7px;align-items:flex-start">
          <div class="sticky-colors">
            <div class="scp yellow sel" data-color="yellow" onclick="selectStickyColor(this)"></div>
            <div class="scp green"  data-color="green"  onclick="selectStickyColor(this)"></div>
            <div class="scp pink"   data-color="pink"   onclick="selectStickyColor(this)"></div>
            <div class="scp blue"   data-color="blue"   onclick="selectStickyColor(this)"></div>
            <div class="scp lavender" data-color="lavender" onclick="selectStickyColor(this)"></div>
          </div>
          <button class="btn primary sm" onclick="addSticky()">📌 Pin</button>
        </div>
      </div>
      <div class="stickies-grid" id="stickies-grid">
        <div class="corkboard-empty" id="cork-empty">No notes yet — pin something!</div>
      </div>
    </div>
  </div>


  <!-- ── TOP GRID: To-Do + Reminders ── -->
  <div class="top-grid">

    <!-- To-Do -->
    <div class="card todo-card">
      <div class="card-label">To-Do List</div>
      <form method="post" action="workspace.php">
        <input type="hidden" name="action" value="add_task"/>
        <div class="todo-input-row">
          <input type="text" name="title" placeholder="Add a new task…" required/>
          <button type="submit" class="btn primary">Add</button>
        </div>
        <div class="todo-input-row">
          <select name="priority" title="Priority">
            <option value="Medium">Medium priority</option>
            <option value="High">High priority</option>
            <option value="Low">Low priority</option>
          </select>
          <select name="category" title="Category">
            <option value="Personal">Personal</option>
            <option value="Work">Work</option>
            <option value="Study">Study</option>
          </select>
          <input type="datetime-local" name="due_date" title="Due date (optional)"/>
        </div>
      </form>

      <div class="todo-list">
        <?php if (empty($tasks)): ?>
          <p style="color:var(--text-muted);font-size:12px;text-align:center;padding:14px 0">No tasks yet — add one above.</p>
        <?php endif; ?>
        <?php foreach ($tasks as $t): $done = $t['status'] === 'Completed'; ?>
          <div class="todo-item<?= $done ? ' done' : '' ?>">
            <form method="post" action="workspace.php" style="display:contents">
              <input type="hidden" name="action" value="toggle_task"/>
              <input type="hidden" name="task_id" value="<?= (int)$t['task_id'] ?>"/>
              <button type="submit" class="todo-check<?= $done ? ' checked' : '' ?>" aria-label="Mark done"></button>
            </form>
            <span class="todo-text">
              <?= htmlspecialchars($t['title']) ?>
              <?php
                $meta = array_filter([
                  $t['category'] ?: null,
                  $t['due_date'] ? date('M j · g:i A', strtotime($t['due_date'])) : null,
                ]);
              ?>
              <?php if ($meta): ?>
                <br><small style="color:var(--text-muted);font-size:10px"><?= htmlspecialchars(implode(' · ', $meta)) ?></small>
              <?php endif; ?>
            </span>
            <div class="todo-priority <?= strtolower($t['priority']) ?>" title="<?= htmlspecialchars($t['priority']) ?> priority"></div>
            <form method="post" action="workspace.php" style="display:contents">
              <input type="hidden" name="action" value="delete_task"/>
              <input type="hidden" name="task_id" value="<?= (int)$t['task_id'] ?>"/>
              <button type="submit" class="todo-del">✕</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="todo-footer">
        <span><?= $pendingTaskCount ?> task<?= $pendingTaskCount !== 1 ? 's' : '' ?> remaining</span>
        <form method="post" action="workspace.php" style="display:contents">
          <input type="hidden" name="action" value="clear_done_tasks"/>
          <button type="submit" class="btn sm">Clear done</button>
        </form>
      </div>
    </div>

    <!-- Reminders -->
    <div class="card reminders-card">
      <div class="card-label">Reminders</div>
      <form method="post" action="workspace.php">
        <input type="hidden" name="action" value="add_reminder"/>
        <div class="reminder-input-group">
          <input type="text" name="title" placeholder="Reminder title…" required/>
          <textarea name="message" placeholder="Notes (optional)…"></textarea>
          <input type="datetime-local" name="reminder_datetime" required/>
          <button type="submit" class="btn primary" style="width:100%">Set Reminder</button>
        </div>
      </form>

      <div class="reminder-list">
        <?php if (empty($reminders)): ?>
          <p style="color:var(--text-muted);font-size:12px;text-align:center;padding:14px 0">No reminders yet.</p>
        <?php endif; ?>
        <?php foreach ($reminders as $r):
          $past = $r['status'] !== 'Pending';
        ?>
          <div class="reminder-item">
            <div class="reminder-bell"><?= $past ? '🔕' : '🔔' ?></div>
            <div class="reminder-info">
              <div class="reminder-title" style="<?= $past ? 'text-decoration:line-through;opacity:0.5' : '' ?>"><?= htmlspecialchars($r['title']) ?></div>
              <?php if (!empty($r['message'])): ?>
                <div class="reminder-time" style="font-style:italic"><?= htmlspecialchars($r['message']) ?></div>
              <?php endif; ?>
              <div class="reminder-time"><?= date('M j · g:i A', strtotime($r['reminder_datetime'])) ?></div>
            </div>
            <form method="post" action="workspace.php" style="display:contents">
              <input type="hidden" name="action" value="delete_reminder"/>
              <input type="hidden" name="reminder_id" value="<?= (int)$r['reminder_id'] ?>"/>
              <button type="submit" class="reminder-del">✕</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

  <!-- ── KANBAN BOARD ── -->
  <div class="kanban-section">
    <div class="section-label">Project Board</div>
    <div class="kanban-board">

      <!-- Ongoing -->
      <div class="kanban-col" id="col-ongoing" ondragover="colDragOver(event,'ongoing')" ondrop="colDrop(event,'ongoing')" ondragleave="colDragLeave(event)">
        <div class="kanban-col-header">
          <div class="kanban-col-title"><div class="col-dot ongoing"></div>Ongoing</div>
          <span class="col-count" id="count-ongoing">0</span>
        </div>
        <div class="kanban-cards" id="cards-ongoing"></div>
        <div class="kanban-add">
          <input type="text" placeholder="Add card…" id="add-ongoing" onkeydown="if(event.key==='Enter') addKanban('ongoing')"/>
          <div style="display:flex;gap:6px">
            <select id="tag-ongoing" style="flex:1;padding:5px 8px;border:1px solid var(--border);border-radius:8px;background:var(--cream);font-family:var(--ff-body);font-size:11px;color:var(--text-muted);outline:none">
              <option value="">No tag</option>
              <option value="work">Work</option>
              <option value="personal">Personal</option>
              <option value="urgent">Urgent</option>
              <option value="learning">Learning</option>
            </select>
            <button class="btn sm primary" onclick="addKanban('ongoing')">+ Add</button>
          </div>
        </div>
      </div>

      <!-- Incomplete -->
      <div class="kanban-col" id="col-incomplete" ondragover="colDragOver(event,'incomplete')" ondrop="colDrop(event,'incomplete')" ondragleave="colDragLeave(event)">
        <div class="kanban-col-header">
          <div class="kanban-col-title"><div class="col-dot incomplete"></div>Incomplete</div>
          <span class="col-count" id="count-incomplete">0</span>
        </div>
        <div class="kanban-cards" id="cards-incomplete"></div>
        <div class="kanban-add">
          <input type="text" placeholder="Add card…" id="add-incomplete" onkeydown="if(event.key==='Enter') addKanban('incomplete')"/>
          <div style="display:flex;gap:6px">
            <select id="tag-incomplete" style="flex:1;padding:5px 8px;border:1px solid var(--border);border-radius:8px;background:var(--cream);font-family:var(--ff-body);font-size:11px;color:var(--text-muted);outline:none">
              <option value="">No tag</option>
              <option value="work">Work</option>
              <option value="personal">Personal</option>
              <option value="urgent">Urgent</option>
              <option value="learning">Learning</option>
            </select>
            <button class="btn sm primary" onclick="addKanban('incomplete')">+ Add</button>
          </div>
        </div>
      </div>

      <!-- Completed -->
      <div class="kanban-col" id="col-completed" ondragover="colDragOver(event,'completed')" ondrop="colDrop(event,'completed')" ondragleave="colDragLeave(event)">
        <div class="kanban-col-header">
          <div class="kanban-col-title"><div class="col-dot completed"></div>Completed</div>
          <span class="col-count" id="count-completed">0</span>
        </div>
        <div class="kanban-cards" id="cards-completed"></div>
        <div class="kanban-add">
          <input type="text" placeholder="Add card…" id="add-completed" onkeydown="if(event.key==='Enter') addKanban('completed')"/>
          <div style="display:flex;gap:6px">
            <select id="tag-completed" style="flex:1;padding:5px 8px;border:1px solid var(--border);border-radius:8px;background:var(--cream);font-family:var(--ff-body);font-size:11px;color:var(--text-muted);outline:none">
              <option value="">No tag</option>
              <option value="work">Work</option>
              <option value="personal">Personal</option>
              <option value="urgent">Urgent</option>
              <option value="learning">Learning</option>
            </select>
            <button class="btn sm primary" onclick="addKanban('completed')">+ Add</button>
          </div>
        </div>
      </div>

    </div>
  </div>


  <!-- ── MOTIVATION (replaces the old Spotify box) ── -->
  <div class="motivation-section">
    <div class="section-label">Motivation</div>
    <div class="motivation-card card">
      <div class="card-label">Daily Boost</div>
      <div class="motivation-body">
        <div class="motivation-icon">✨</div>
        <div class="motivation-text" id="motivation-text">Loading today's boost…</div>
        <button class="btn-motivation" onclick="nextMotivation()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
          New quote
        </button>
      </div>
    </div>
  </div>

  <!-- ── BOTTOM GRID ── -->
  <div class="bottom-grid">

    <!-- Focus Session -->
    <div class="card focus-card">
      <div class="card-label">Focus Session</div>
      <div class="focus-modes" id="focus-modes">
        <button class="focus-mode-btn active" data-min="25" data-label="Pomodoro" onclick="setFocusMode(this)">🍅 25 min</button>
        <button class="focus-mode-btn" data-min="50" data-label="Deep Work" onclick="setFocusMode(this)">🧠 50 min</button>
        <button class="focus-mode-btn" data-min="5" data-label="Short Break" onclick="setFocusMode(this)">☕ 5 min</button>
        <button class="focus-mode-btn" data-min="15" data-label="Long Break" onclick="setFocusMode(this)">🌿 15 min</button>
      </div>
      <div class="focus-bar"><div class="focus-bar-fill" id="focus-bar"></div></div>
      <div class="focus-timer" id="focus-display">25:00</div>
      <div class="focus-lbl" id="focus-label">Ready to focus</div>
      <div class="focus-controls">
        <button class="btn primary" id="focus-start" onclick="focusStart()">Start</button>
        <button class="btn" id="focus-pause" style="display:none" onclick="focusPause()">Pause</button>
        <button class="btn" onclick="focusReset()">Reset</button>
      </div>
      <div class="session-dots" id="focus-dots"></div>
    </div>

    <!-- Quick Notes -->
    <div class="card notes-card">
      <div class="card-label">Quick Notes</div>
      <div class="notes-tabs" id="notes-tabs"></div>
      <textarea class="notes-editor" id="notes-editor" placeholder="Start writing…"></textarea>
      <div class="notes-footer">
        <span id="notes-char">0 chars</span>
        <div style="display:flex;gap:6px">
          <button class="btn sm" onclick="addNoteTab()">+ New</button>
          <button class="btn sm primary" onclick="saveNote()">Save</button>
        </div>
      </div>
    </div>

    <!-- Workspace Snapshot -->
    <div class="card snapshot-card" style="background:var(--sage-pale);border-color:rgba(138,158,140,0.25)">
      <div class="card-label">Workspace Snapshot</div>

      <div class="snapshot-progress">
        <div class="snapshot-progress-top">
          <span>Tasks completed</span>
          <span class="snapshot-progress-pct"><?= $completionPercent ?>%</span>
        </div>
        <div class="snapshot-bar"><div class="snapshot-bar-fill" style="width:<?= $completionPercent ?>%"></div></div>
        <div class="snapshot-progress-sub"><?= $completedTaskCount ?> of <?= $totalTaskCount ?> task<?= $totalTaskCount !== 1 ? 's' : '' ?> done</div>
      </div>

      <div class="snapshot-priorities">
        <div class="snapshot-priority-item"><span class="todo-priority high"></span>High <strong><?= $priorityCounts['High'] ?></strong></div>
        <div class="snapshot-priority-item"><span class="todo-priority medium"></span>Medium <strong><?= $priorityCounts['Medium'] ?></strong></div>
        <div class="snapshot-priority-item"><span class="todo-priority low"></span>Low <strong><?= $priorityCounts['Low'] ?></strong></div>
      </div>

      <div class="snapshot-next">
        <div class="snapshot-next-label">Next Reminder</div>
        <?php if ($nextReminder): ?>
          <div class="snapshot-next-title"><?= htmlspecialchars($nextReminder['title']) ?></div>
          <div class="snapshot-next-time"><?= date('M j · g:i A', strtotime($nextReminder['reminder_datetime'])) ?></div>
        <?php else: ?>
          <div class="snapshot-next-empty">Nothing scheduled — you're all caught up.</div>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <!-- Quote -->
  <div class="quote-card card" style="margin-top:8px">
    <blockquote><?= htmlspecialchars($quote) ?></blockquote>
  </div>

</main>

<script>
'use strict';

// ── HELPERS ──────────────────────────────────
function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtDT(ts) {
  const d = new Date(ts);
  return d.toLocaleDateString('en-MY',{month:'short',day:'numeric'}) + ' · ' +
         d.toLocaleTimeString('en-MY',{hour:'2-digit',minute:'2-digit'});
}
function fmtMS(s) {
  return String(Math.floor(s/60)).padStart(2,'0')+':'+String(s%60).padStart(2,'0');
}
function showToast(title, body, dur=6000) {
  document.getElementById('toast-title').textContent = title;
  document.getElementById('toast-body').textContent  = body;
  document.getElementById('toast').classList.add('show');
  if (dur) setTimeout(dismissToast, dur);
}
function dismissToast() { document.getElementById('toast').classList.remove('show'); }

// ── CLOCK ──────────────────────────────────
function updateClock() {
  const n = new Date();
  const pad = v => String(v).padStart(2,'0');
  document.getElementById('live-time').textContent =
    pad(n.getHours())+':'+pad(n.getMinutes())+':'+pad(n.getSeconds());
  const days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  document.getElementById('live-date').textContent =
    days[n.getDay()]+', '+n.getDate()+' '+months[n.getMonth()]+' '+n.getFullYear();
}
updateClock(); setInterval(updateClock, 1000);

// ── DROPDOWN ──────────────────────────────────
function toggleDropdown() { document.getElementById('profileWrap').classList.toggle('open'); }
document.addEventListener('click', e => {
  if (!e.target.closest('#profileWrap')) document.getElementById('profileWrap').classList.remove('open');
});

// ═══════════════════════════════════════
// KANBAN BOARD
// ═══════════════════════════════════════
const COLS = ['ongoing','incomplete','completed'];
let kanban = <?php echo json_encode($kanbanData); ?>;
let dragId=null, dragCol=null;

// Sends an action to workspace.php and saves it to the database for this user.
async function postAction(params) {
  const body = new URLSearchParams(params);
  const res = await fetch('workspace.php', { method: 'POST', body });
  return res.json();
}

function renderKanban() {
  COLS.forEach(col => {
    const el = document.getElementById('cards-'+col);
    el.innerHTML='';
    kanban[col].forEach(card => {
      const d = document.createElement('div');
      d.className='kanban-card';
      d.draggable=true;
      d.dataset.id=card.id;
      d.dataset.col=col;
      d.innerHTML=`<button class="kanban-card-del" onclick="deleteKanban('${col}',${card.id})">✕</button>
        <div class="kanban-card-title">${esc(card.title)}</div>
        <div class="kanban-card-meta">
          ${card.tag?`<span class="kanban-tag ${card.tag}">${esc(card.tag)}</span>`:''}
        </div>`;
      d.addEventListener('dragstart', e => { dragId=card.id; dragCol=col; d.classList.add('dragging'); });
      d.addEventListener('dragend', () => { d.classList.remove('dragging'); dragId=null; dragCol=null; });
      el.appendChild(d);
    });
    document.getElementById('count-'+col).textContent = kanban[col].length;
  });
}

function colDragOver(e, col) {
  e.preventDefault();
  document.getElementById('col-'+col).style.background='var(--sage-pale)';
}
function colDragLeave(e) {
  COLS.forEach(c => document.getElementById('col-'+c).style.background='');
}
async function colDrop(e, toCol) {
  e.preventDefault();
  COLS.forEach(c => document.getElementById('col-'+c).style.background='');
  if (!dragId || !dragCol || dragCol===toCol) { dragId=null; dragCol=null; return; }
  const card = kanban[dragCol].find(c=>c.id===dragId);
  if (!card) return;
  kanban[dragCol] = kanban[dragCol].filter(c=>c.id!==dragId);
  kanban[toCol].push(card);
  renderKanban();
  await postAction({action:'move_kanban_card', card_id: card.id, column: toCol});
  dragId=null; dragCol=null;
}

async function addKanban(col) {
  const inp = document.getElementById('add-'+col);
  const title = inp.value.trim(); if (!title) return;
  const tag = document.getElementById('tag-'+col).value;
  inp.value='';
  const result = await postAction({action:'add_kanban_card', column: col, title, tag});
  if (result.success) {
    kanban[col].push({id: result.card_id, title, tag});
    renderKanban();
  }
}

async function deleteKanban(col, id) {
  kanban[col]=kanban[col].filter(c=>c.id!==id);
  renderKanban();
  await postAction({action:'delete_kanban_card', card_id: id});
}

renderKanban();

// ═══════════════════════════════════════
// CORKBOARD STICKY NOTES
// ═══════════════════════════════════════
let stickies = <?php echo json_encode($stickiesData); ?>;
let stickyColor='yellow';
const TILTS = ['tilt-1','tilt-2','tilt-3','tilt-4','tilt-5'];

function selectStickyColor(el) {
  document.querySelectorAll('.sticky-colors .scp').forEach(s=>s.classList.remove('sel'));
  el.classList.add('sel'); stickyColor=el.dataset.color;
}

async function addSticky() {
  const text=document.getElementById('sticky-input').value.trim();
  if (!text) return;
  document.getElementById('sticky-input').value='';
  const result = await postAction({action:'add_sticky', text, color: stickyColor});
  if (result.success) {
    stickies.unshift({id: result.note_id, text, color: stickyColor, ts: Date.now()});
    renderStickies();
  }
}

async function deleteSticky(i) {
  const removed = stickies.splice(i,1)[0];
  renderStickies();
  if (removed) await postAction({action:'delete_sticky', note_id: removed.id});
}

function renderStickies() {
  const grid = document.getElementById('stickies-grid');
  const empty = document.getElementById('cork-empty');
  grid.innerHTML='';
  if (stickies.length===0) { grid.appendChild(empty); return; }
  stickies.forEach((s,i) => {
    const tilt = TILTS[i % TILTS.length];
    const d = document.createElement('div');
    d.className=`sticky-note ${s.color} ${tilt}`;
    d.innerHTML=`<button class="sticky-note-del" onclick="deleteSticky(${i})">✕</button>
      ${esc(s.text).replace(/\n/g,'<br>')}
      <span class="sticky-note-time">${fmtDT(s.ts)}</span>`;
    grid.appendChild(d);
  });
}

renderStickies();

// ═══════════════════════════════════════
// FOCUS SESSION
// ═══════════════════════════════════════
let fTotal=25*60, fRemain=25*60, fInterval=null, fRunning=false, fSessions=0, fLabel='Pomodoro';

function setFocusMode(btn) {
  if (fRunning) return;
  document.querySelectorAll('.focus-mode-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  fLabel=btn.dataset.label; fTotal=parseInt(btn.dataset.min)*60; fRemain=fTotal;
  document.getElementById('focus-display').textContent=fmtMS(fRemain);
  document.getElementById('focus-bar').style.width='100%';
  document.getElementById('focus-label').textContent='Ready · '+fLabel;
}

function focusStart() {
  if (fRunning||fRemain<=0) return; fRunning=true;
  document.getElementById('focus-start').style.display='none';
  document.getElementById('focus-pause').style.display='';
  document.getElementById('focus-label').textContent=fLabel+' in progress…';
  fInterval=setInterval(()=>{
    fRemain--;
    document.getElementById('focus-display').textContent=fmtMS(fRemain);
    document.getElementById('focus-bar').style.width=((fRemain/fTotal)*100)+'%';
    if (fRemain<=0) {
      clearInterval(fInterval); fRunning=false; fSessions++;
      document.getElementById('focus-start').style.display='';
      document.getElementById('focus-pause').style.display='none';
      document.getElementById('focus-label').textContent='✓ Session complete!';
      renderFocusDots();
      showToast('✅ '+fLabel+' done!','Great work. Time for a well-earned break.');
    }
  },1000);
}
function focusPause() {
  clearInterval(fInterval); fRunning=false;
  document.getElementById('focus-start').style.display='';
  document.getElementById('focus-pause').style.display='none';
  document.getElementById('focus-label').textContent='Paused · '+fLabel;
}
function focusReset() {
  focusPause(); fRemain=fTotal;
  document.getElementById('focus-display').textContent=fmtMS(fRemain);
  document.getElementById('focus-bar').style.width='100%';
  document.getElementById('focus-label').textContent='Ready · '+fLabel;
}
function renderFocusDots() {
  const c=document.getElementById('focus-dots'); c.innerHTML='';
  const total=Math.max(4,fSessions+1);
  for(let i=0;i<total;i++){
    const d=document.createElement('div');
    d.className='sdot'+(i<fSessions?' done':'');
    c.appendChild(d);
  }
}
renderFocusDots();


// ═══════════════════════════════════════
// QUICK NOTES
// ═══════════════════════════════════════
let noteTabs     = JSON.parse(localStorage.getItem('sc_note_tabs')||'["General","Ideas","Work"]');
let noteContents = JSON.parse(localStorage.getItem('sc_note_contents')||'{}');
let activeTab    = noteTabs[0];

function renderNoteTabs(){
  const c=document.getElementById('notes-tabs'); c.innerHTML='';
  noteTabs.forEach(tab=>{
    const el=document.createElement('div');
    el.className='note-tab'+(tab===activeTab?' active':'');
    el.innerHTML=esc(tab)+(noteTabs.length>1?`<span class="tab-del" onclick="deleteNoteTab(event,'${esc(tab)}')">✕</span>`:'');
    el.addEventListener('click',e=>{if(!e.target.classList.contains('tab-del')){saveNote();activeTab=tab;renderNoteTabs();loadNote();}});
    c.appendChild(el);
  });
}
function loadNote(){
  document.getElementById('notes-editor').value=noteContents[activeTab]||'';
  updateNoteChar();
}
function saveNote(){
  noteContents[activeTab]=document.getElementById('notes-editor').value;
  localStorage.setItem('sc_note_tabs',JSON.stringify(noteTabs));
  localStorage.setItem('sc_note_contents',JSON.stringify(noteContents));
}
function addNoteTab(){
  const n=prompt('Tab name:'); if(!n||!n.trim()) return;
  noteTabs.push(n.trim()); saveNote(); activeTab=n.trim();
  renderNoteTabs(); loadNote();
}
function deleteNoteTab(e,tab){
  e.stopPropagation();
  if(!confirm('Delete "'+tab+'"?')) return;
  const idx=noteTabs.indexOf(tab); noteTabs.splice(idx,1); delete noteContents[tab];
  if(activeTab===tab) activeTab=noteTabs[Math.max(0,idx-1)];
  localStorage.setItem('sc_note_tabs',JSON.stringify(noteTabs));
  localStorage.setItem('sc_note_contents',JSON.stringify(noteContents));
  renderNoteTabs(); loadNote();
}
function updateNoteChar(){
  document.getElementById('notes-char').textContent=document.getElementById('notes-editor').value.length+' chars';
}
document.getElementById('notes-editor').addEventListener('input',updateNoteChar);
renderNoteTabs(); loadNote();

// ═══════════════════════════════════════
// MOTIVATION BOX (replaces the old Spotify player)
// ═══════════════════════════════════════
const MOTIVATION_QUOTES = [
  "Small steps every day lead to big changes over time.",
  "You don't have to be perfect, you just have to be present.",
  "Rest is not quitting — it's part of the journey.",
  "One task at a time. Breathe. You've got this.",
  "Progress, not perfection.",
  "You are capable of more than you know.",
  "Keep going — you're closer than you think.",
  "Every effort counts, even the ones no one sees.",
  "Believe in the slow work of becoming better.",
  "Today is a good day to try again.",
];
let motiIdx = Math.floor(Math.random() * MOTIVATION_QUOTES.length);
function renderMotivation() {
  document.getElementById('motivation-text').textContent = MOTIVATION_QUOTES[motiIdx];
}
function nextMotivation() {
  motiIdx = (motiIdx + 1) % MOTIVATION_QUOTES.length;
  renderMotivation();
}
renderMotivation();
</script>
</body>
</html>