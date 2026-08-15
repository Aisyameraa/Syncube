<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

// Current logged-in user
$currentUserName = $_SESSION['user_name'] ?? 'Guest User';

// Seed settings if not exists
if (!isset($_SESSION['user_settings'])) {
    $_SESSION['user_settings'] = [
        'email' => 'user@syncube.io',
        'theme' => 'light',
        'accent' => 'sage',
        'animations' => true,
        'glass' => true,

        'journal_reminder' => true,
        'calendar_reminder' => true,
        'workspace_updates' => true,
        'weekly_summary' => true,
        'marketing' => false,

        'default_mood' => 'Focused',
        'reminder_time' => '19:00',
        'autosave' => true,
        'journal_privacy' => 'private',

        'week_start' => 'monday',
        'calendar_view' => 'month',
        'calendar_alert' => '15',

        'default_workspace' => 'Personal',
        'compact_mode' => false,
        'show_completed' => true,

        'two_factor' => false
    ];
}

$settings = &$_SESSION['user_settings'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Appearance
    $settings['theme'] = $_POST['theme'] ?? $settings['theme'];
    $settings['accent'] = $_POST['accent'] ?? $settings['accent'];
    $settings['animations'] = isset($_POST['animations']);
    $settings['glass'] = isset($_POST['glass']);

    // Notifications
    $settings['journal_reminder'] = isset($_POST['journal_reminder']);
    $settings['calendar_reminder'] = isset($_POST['calendar_reminder']);
    $settings['workspace_updates'] = isset($_POST['workspace_updates']);
    $settings['weekly_summary'] = isset($_POST['weekly_summary']);
    $settings['marketing'] = isset($_POST['marketing']);

    // Journal
    $settings['default_mood'] = $_POST['default_mood'] ?? $settings['default_mood'];
    $settings['reminder_time'] = $_POST['reminder_time'] ?? $settings['reminder_time'];
    $settings['autosave'] = isset($_POST['autosave']);
    $settings['journal_privacy'] = $_POST['journal_privacy'] ?? $settings['journal_privacy'];

    // Calendar
    $settings['week_start'] = $_POST['week_start'] ?? $settings['week_start'];
    $settings['calendar_view'] = $_POST['calendar_view'] ?? $settings['calendar_view'];
    $settings['calendar_alert'] = $_POST['calendar_alert'] ?? $settings['calendar_alert'];

    // Workspace
    $settings['default_workspace'] = $_POST['default_workspace'] ?? $settings['default_workspace'];
    $settings['compact_mode'] = isset($_POST['compact_mode']);
    $settings['show_completed'] = isset($_POST['show_completed']);

    // Security
    $settings['two_factor'] = isset($_POST['two_factor']);

    header('Location: settings.php?saved=1');
    exit;
}

$justSaved = isset($_GET['saved']);

// Initials
$nameWords = explode(' ', $currentUserName);
$initials = strtoupper(substr($nameWords[0], 0, 1) . substr($nameWords[1] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8' />
<meta name='viewport' content='width=device-width, initial-scale=1.0' />
<title>SYNCUBE — Settings</title>

<link rel='preconnect' href='https://fonts.googleapis.com' />
<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin />
<link href='https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500&family=DM+Sans:wght@300;400;500&display=swap' rel='stylesheet'/>

<style>
*{margin:0;padding:0;box-sizing:border-box}

:root{
  --sage:#8a9e8c;
  --sage-light:#c8d8c9;
  --sage-pale:#eef3ee;
  --stone-light:#e8e3de;
  --cream:#faf8f5;
  --warm:#f7f5f2;
  --text:#2c2a27;
  --mid:#6b6760;
  --muted:#a09d9a;
  --border:rgba(44,42,39,.08);
  --shadow:0 8px 30px rgba(44,42,39,.08);
  --radius:22px;
  --nav:64px;
  --display:'Cormorant Garamond',serif;
  --body:'DM Sans',sans-serif;
}

body{
  font-family:var(--body);
  background:var(--cream);
  color:var(--text);
  min-height:100vh;
  overflow-x:hidden;
}

/* animated blobs */
.bg{
  position:fixed;
  inset:auto;
  border-radius:50%;
  filter:blur(100px);
  opacity:.4;
  pointer-events:none;
  z-index:0;
  animation:float 18s ease-in-out infinite alternate;
}
.bg.one{width:380px;height:380px;background:var(--sage-light);top:-120px;right:-40px;}
.bg.two{width:320px;height:320px;background:var(--stone-light);bottom:-120px;left:-80px;animation-delay:-6s;}

@keyframes float{
  from{transform:translate(0,0) scale(1)}
  to{transform:translate(35px,55px) scale(1.12)}
}

/* nav */
nav{
  position:fixed;
  top:0;left:0;right:0;
  height:var(--nav);
  background:rgba(250,248,245,.92);
  backdrop-filter:blur(16px);
  border-bottom:1px solid var(--border);
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:0 36px;
  z-index:100;
}

.logo{
  font-family:var(--display);
  font-size:22px;
  letter-spacing:.08em;
  text-decoration:none;
  color:var(--text);
  display:flex;
  align-items:center;
  gap:8px;
}
.logo span{
  width:7px;height:7px;border-radius:50%;background:var(--sage);
}

.nav-links{
  display:flex;
  align-items:center;
  gap:4px;
  list-style:none;
}
.nav-links a{
  text-decoration:none;
  color:var(--mid);
  font-size:13px;
  padding:6px 14px;
  border-radius:10px;
  transition:.2s;
}
.nav-links a:hover,
.nav-links a.active{
  background:var(--stone-light);
  color:var(--text);
}

/* profile dropdown */
.profile-wrap{position:relative}
.profile-btn{
  display:flex;align-items:center;gap:9px;
  padding:6px 12px 6px 6px;
  border:1px solid var(--border);
  border-radius:999px;
  background:var(--warm);
  cursor:pointer;
  font-size:13px;
}
.avatar{
  width:30px;height:30px;border-radius:50%;
  background:var(--sage-light);
  display:grid;place-items:center;
  color:var(--sage);
  font-size:11px;font-weight:600;
}
.chev{width:14px;height:14px;opacity:.5;transition:.2s}
.profile-wrap.open .chev{transform:rotate(180deg)}

.dropdown{
  position:absolute;
  right:0;top:calc(100% + 8px);
  width:190px;
  background:var(--warm);
  border:1px solid var(--border);
  border-radius:16px;
  box-shadow:var(--shadow);
  padding:8px;
  opacity:0;
  transform:translateY(-6px);
  pointer-events:none;
  transition:.2s;
}
.profile-wrap.open .dropdown{
  opacity:1;transform:none;pointer-events:auto;
}
.dropdown-header{
  padding:8px 10px 10px;
  border-bottom:1px solid var(--border);
  margin-bottom:6px;
}
.dropdown-header strong{display:block;font-size:13px}
.dropdown-header span{font-size:11px;color:var(--muted)}
.dropdown a{
  display:flex;align-items:center;gap:8px;
  padding:8px 10px;
  border-radius:10px;
  color:var(--mid);
  text-decoration:none;
  font-size:13px;
}
.dropdown a:hover{background:var(--stone-light);color:var(--text)}
.dropdown .divider{height:1px;background:var(--border);margin:6px 0}
.dropdown a.logout{color:#b85c5c}
.dropdown a.logout:hover{background:#fdf0f0}

/* layout */
main{
  position:relative;
  z-index:1;
  max-width:1280px;
  margin:0 auto;
  padding:calc(var(--nav) + 28px) 36px 56px;
}

.hero{
  background:var(--warm);
  border:1px solid var(--border);
  border-radius:28px;
  padding:28px 32px;
  box-shadow:var(--shadow);
  margin-bottom:24px;
}
.hero h1{
  font-family:var(--display);
  font-size:42px;
  font-weight:300;
  margin-bottom:6px;
}
.hero p{color:var(--mid)}

.layout{
  display:grid;
  grid-template-columns:260px 1fr;
  gap:24px;
}

.sidebar{
  position:sticky;
  top:92px;
  align-self:start;
  background:var(--warm);
  border:1px solid var(--border);
  border-radius:22px;
  padding:14px;
  box-shadow:var(--shadow);
}
.sidebar h3{
  font-size:11px;
  letter-spacing:.12em;
  text-transform:uppercase;
  color:var(--muted);
  margin:8px 10px 12px;
}
.sidebar a{
  display:flex;align-items:center;gap:10px;
  padding:11px 12px;
  border-radius:12px;
  color:var(--mid);
  text-decoration:none;
  font-size:14px;
  transition:.2s;
}
.sidebar a:hover,
.sidebar a.active{
  background:var(--sage-pale);
  color:var(--text);
}

.content{
  display:flex;
  flex-direction:column;
  gap:24px;
}

.card{
  background:var(--warm);
  border:1px solid var(--border);
  border-radius:24px;
  padding:28px;
  box-shadow:var(--shadow);
}
.card h2{
  font-family:var(--display);
  font-size:30px;
  font-weight:300;
  margin-bottom:6px;
}
.card p.section{
  color:var(--mid);
  margin-bottom:22px;
}

.grid{
  display:grid;
  grid-template-columns:repeat(2,minmax(0,1fr));
  gap:18px;
}

.field label{
  display:block;
  font-size:11px;
  letter-spacing:.08em;
  text-transform:uppercase;
  color:var(--muted);
  margin-bottom:8px;
}
.field input,
.field select{
  width:100%;
  padding:12px 14px;
  border:1px solid var(--border);
  border-radius:12px;
  background:var(--cream);
  font:inherit;
  color:var(--text);
}

.readonly{
  padding:12px 14px;
  border:1px dashed var(--border);
  border-radius:12px;
  background:#fff;
  color:var(--mid);
}

.row{
  display:flex;
  align-items:center;
  justify-content:space-between;
  padding:14px 0;
  border-bottom:1px solid rgba(44,42,39,.06);
}
.row:last-child{border-bottom:none}

.toggle{
  position:relative;
  width:52px;height:30px;
}
.toggle input{display:none}
.toggle span{
  position:absolute;inset:0;
  background:#d7d2cb;
  border-radius:999px;
  transition:.2s;
}
.toggle span:before{
  content:'';
  position:absolute;
  width:22px;height:22px;
  left:4px;top:4px;
  background:#fff;border-radius:50%;
  transition:.2s;
  box-shadow:0 2px 6px rgba(0,0,0,.15);
}
.toggle input:checked + span{background:var(--sage)}
.toggle input:checked + span:before{transform:translateX(22px)}

.storage{
  background:var(--sage-pale);
  border:1px solid rgba(138,158,140,.18);
  border-radius:18px;
  padding:18px;
}
.bar{
  height:8px;border-radius:999px;
  background:#d8e2d8;
  overflow:hidden;margin:10px 0 16px;
}
.bar div{
  width:72%;height:100%;
  background:linear-gradient(90deg,var(--sage),#b79b77);
}

.actions{
  display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap;
}

.btn{
  padding:11px 20px;
  border-radius:999px;
  border:1px solid var(--border);
  background:var(--cream);
  color:var(--text);
  font:inherit;
  cursor:pointer;
  transition:.2s;
}
.btn:hover{background:var(--stone-light)}
.btn.primary{
  background:var(--sage);
  color:#fff;
  border-color:var(--sage);
}
.btn.primary:hover{background:#738676}
.btn.danger{
  background:#fff5f5;
  border-color:#efcaca;
  color:#b85c5c;
}

.savebar{
  position:sticky;
  bottom:18px;
  background:rgba(247,245,242,.92);
  backdrop-filter:blur(12px);
  border:1px solid var(--border);
  border-radius:20px;
  padding:14px 18px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  box-shadow:var(--shadow);
}

.toast{
  position:fixed;
  top:84px;right:36px;
  background:var(--text);
  color:var(--cream);
  padding:13px 18px;
  border-radius:14px;
  box-shadow:var(--shadow);
  display:flex;align-items:center;gap:10px;
  z-index:999;
  animation:toastIn .35s ease, toastOut .4s ease 2.6s forwards;
}
@keyframes toastIn{
  from{opacity:0;transform:translateY(-8px)}
  to{opacity:1;transform:none}
}
@keyframes toastOut{
  to{opacity:0;transform:translateY(-8px)}
}

@media (max-width: 980px){
  .layout{grid-template-columns:1fr}
  .sidebar{position:relative;top:0}
}
@media (max-width: 720px){
  main{padding:calc(var(--nav) + 20px) 18px 40px}
  nav{padding:0 18px}
  .grid{grid-template-columns:1fr}
  .hero h1{font-size:34px}
}
</style>
</head>
<body>

<div class='bg one'></div>
<div class='bg two'></div>

<?php if ($justSaved): ?>
<div class='toast'>
  <svg width='16' height='16' viewBox='0 0 16 16' fill='none'>
    <path d='M3 8l3.5 3.5L13 5' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/>
  </svg>
  Settings saved successfully
</div>
<?php endif; ?>

<nav>
  <a href='dashboard.php' class='logo'><span></span> syncube</a>

  <ul class='nav-links'>
    <li><a href='dashboard.php'>Dashboard</a></li>
    <li><a href='workspace.php'>Workspace</a></li>
    <li><a href='calendar.php'>Calendar</a></li>
    <li><a href='journal.php'>Journal</a></li>
    <li>
      <div class='profile-wrap' id='profileWrap'>
        <div class='profile-btn' onclick='toggleDropdown()'>
          <div class='avatar'><?= htmlspecialchars($initials) ?></div>
          <?= htmlspecialchars($currentUserName) ?>
          <svg class='chev' viewBox='0 0 16 16' fill='none'>
            <path d='M4 6L8 10L12 6' stroke='currentColor' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/>
          </svg>
        </div>

        <div class='dropdown'>
          <div class='dropdown-header'>
            <strong><?= htmlspecialchars($currentUserName) ?></strong>
            <span>Personal Account</span>
          </div>

          <a href='profile.php'>My Profile</a>
          <a href='settings.php'>Settings</a>

          <div class='divider'></div>

          <a href='logout.php' class='logout'>Log out</a>
        </div>
      </div>
    </li>
  </ul>
</nav>

<main>
<form method='POST'>

  <section class='hero'>
    <h1>System Settings</h1>
    <p>Manage your Syncube account, appearance, notifications, privacy, and workspace preferences.</p>
  </section>

  <div class='layout'>

    <!-- Sidebar -->
    <aside class='sidebar'>
      <h3>Preferences</h3>
      <a href='#account' class='active'>👤 Account</a>
      <a href='#security'>🔐 Security</a>
      <a href='#appearance'>🎨 Appearance</a>
      <a href='#notifications'>🔔 Notifications</a>
      <a href='#journal'>📖 Journal</a>
      <a href='#calendar'>📅 Calendar</a>
      <a href='#workspace'>🗂 Workspace</a>
      <a href='#privacy'>🔒 Privacy</a>
      <a href='#system'>💻 System</a>
    </aside>

    <!-- Content -->
    <div class='content'>

      <!-- Account -->
      <section class='card' id='account'>
        <h2>Account Information</h2>
        <p class='section'>Basic account details linked to your Syncube profile.</p>

        <div class='grid'>
          <div class='field'>
            <label>Username</label>
            <input type='text' value='<?= htmlspecialchars($currentUserName) ?>' readonly>
          </div>

          <div class='field'>
            <label>Email Address</label>
            <input type='email' value='<?= htmlspecialchars($settings['email']) ?>' readonly>
          </div>

          <div class='field'>
            <label>User ID</label>
            <div class='readonly'>SYNC-2026-9041</div>
          </div>

          <div class='field'>
            <label>Role</label>
            <div class='readonly'>Personal Account</div>
          </div>
        </div>
      </section>

      <!-- Security -->
      <section class='card' id='security'>
        <h2>Security</h2>
        <p class='section'>Update your password and secure your account.</p>

        <div class='grid'>
          <div class='field'>
            <label>Current Password</label>
            <input type='password' placeholder='••••••••'>
          </div>

          <div class='field'>
            <label>New Password</label>
            <input type='password' placeholder='Enter new password'>
          </div>

          <div class='field'>
            <label>Confirm Password</label>
            <input type='password' placeholder='Confirm new password'>
          </div>
        </div>

        <div class='row'>
          <div>
            <strong>Two-factor authentication</strong>
            <div style='color:var(--mid);font-size:13px'>Require a verification code when signing in.</div>
          </div>

          <label class='toggle'>
            <input type='checkbox' name='two_factor' <?= $settings['two_factor'] ? 'checked' : '' ?>>
            <span></span>
          </label>
        </div>

        <div class='actions' style='margin-top:18px'>
          <button type='button' class='btn'>Log out all devices</button>
          <button type='button' class='btn primary'>Update Password</button>
        </div>
      </section>

      <!-- Appearance -->
      <section class='card' id='appearance'>
        <h2>Appearance</h2>
        <p class='section'>Customize the look and feel of Syncube.</p>

        <div class='grid'>
          <div class='field'>
            <label>Theme</label>
            <select name='theme'>
              <option value='light' <?= $settings['theme']==='light'?'selected':'' ?>>Light</option>
              <option value='dark' <?= $settings['theme']==='dark'?'selected':'' ?>>Dark</option>
              <option value='auto' <?= $settings['theme']==='auto'?'selected':'' ?>>Auto</option>
            </select>
          </div>

          <div class='field'>
            <label>Accent Color</label>
            <select name='accent'>
              <option value='sage' <?= $settings['accent']==='sage'?'selected':'' ?>>Sage</option>
              <option value='forest' <?= $settings['accent']==='forest'?'selected':'' ?>>Forest</option>
              <option value='beige' <?= $settings['accent']==='beige'?'selected':'' ?>>Beige</option>
              <option value='blue' <?= $settings['accent']==='blue'?'selected':'' ?>>Ocean Blue</option>
            </select>
          </div>
        </div>

        <div class='row'>
          <div>
            <strong>Enable animations</strong>
            <div style='color:var(--mid);font-size:13px'>Subtle motion and transitions across the interface.</div>
          </div>
          <label class='toggle'>
            <input type='checkbox' name='animations' <?= $settings['animations'] ? 'checked' : '' ?>>
            <span></span>
          </label>
        </div>

        <div class='row'>
          <div>
            <strong>Glass blur effects</strong>
            <div style='color:var(--mid);font-size:13px'>Enable translucent panels and backdrop blur.</div>
          </div>
          <label class='toggle'>
            <input type='checkbox' name='glass' <?= $settings['glass'] ? 'checked' : '' ?>>
            <span></span>
          </label>
        </div>
      </section>

      <!-- Notifications -->
      <section class='card' id='notifications'>
        <h2>Notifications</h2>
        <p class='section'>Choose which updates and reminders you want to receive.</p>

        <?php
        $notifMap = [
          'journal_reminder' => ['Journal reminders', 'Daily writing reminders'],
          'calendar_reminder' => ['Calendar reminders', 'Upcoming events and deadlines'],
          'workspace_updates' => ['Workspace updates', 'Changes in shared workspaces'],
          'weekly_summary' => ['Weekly summary', 'A recap of your activity each week'],
          'marketing' => ['Marketing emails', 'Product announcements and tips']
        ];
        foreach ($notifMap as $key => [$title, $desc]): ?>
          <div class='row'>
            <div>
              <strong><?= $title ?></strong>
              <div style='color:var(--mid);font-size:13px'><?= $desc ?></div>
            </div>
            <label class='toggle'>
              <input type='checkbox' name='<?= $key ?>' <?= $settings[$key] ? 'checked' : '' ?>>
              <span></span>
            </label>
          </div>
        <?php endforeach; ?>
      </section>

      <!-- Journal -->
      <section class='card' id='journal'>
        <h2>Journal Preferences</h2>

        <div class='grid'>
          <div class='field'>
            <label>Default Mood</label>
            <select name='default_mood'>
              <?php foreach (['Focused','Happy','Calm','Reflective','Energetic'] as $mood): ?>
                <option value='<?= $mood ?>' <?= $settings['default_mood']===$mood?'selected':'' ?>><?= $mood ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class='field'>
            <label>Daily Reminder</label>
            <input type='time' name='reminder_time' value='<?= htmlspecialchars($settings['reminder_time']) ?>'>
          </div>

          <div class='field'>
            <label>Default Privacy</label>
            <select name='journal_privacy'>
              <option value='private' <?= $settings['journal_privacy']==='private'?'selected':'' ?>>Private</option>
              <option value='shared' <?= $settings['journal_privacy']==='shared'?'selected':'' ?>>Shared</option>
            </select>
          </div>
        </div>

        <div class='row'>
          <div>
            <strong>Auto-save entries</strong>
            <div style='color:var(--mid);font-size:13px'>Save drafts automatically while typing.</div>
          </div>
          <label class='toggle'>
            <input type='checkbox' name='autosave' <?= $settings['autosave'] ? 'checked' : '' ?>>
            <span></span>
          </label>
        </div>
      </section>

      <!-- Calendar -->
      <section class='card' id='calendar'>
        <h2>Calendar Preferences</h2>

        <div class='grid'>
          <div class='field'>
            <label>Week Starts On</label>
            <select name='week_start'>
              <option value='monday' <?= $settings['week_start']==='monday'?'selected':'' ?>>Monday</option>
              <option value='sunday' <?= $settings['week_start']==='sunday'?'selected':'' ?>>Sunday</option>
            </select>
          </div>

          <div class='field'>
            <label>Default View</label>
            <select name='calendar_view'>
              <option value='month' <?= $settings['calendar_view']==='month'?'selected':'' ?>>Month</option>
              <option value='week' <?= $settings['calendar_view']==='week'?'selected':'' ?>>Week</option>
              <option value='day' <?= $settings['calendar_view']==='day'?'selected':'' ?>>Day</option>
            </select>
          </div>

          <div class='field'>
            <label>Reminder</label>
            <select name='calendar_alert'>
              <option value='5' <?= $settings['calendar_alert']==='5'?'selected':'' ?>>5 minutes before</option>
              <option value='15' <?= $settings['calendar_alert']==='15'?'selected':'' ?>>15 minutes before</option>
              <option value='30' <?= $settings['calendar_alert']==='30'?'selected':'' ?>>30 minutes before</option>
              <option value='60' <?= $settings['calendar_alert']==='60'?'selected':'' ?>>1 hour before</option>
            </select>
          </div>
        </div>
      </section>

      <!-- Workspace -->
      <section class='card' id='workspace'>
        <h2>Workspace Preferences</h2>

        <div class='grid'>
          <div class='field'>
            <label>Default Workspace</label>
            <select name='default_workspace'>
              <option value='Personal' <?= $settings['default_workspace']==='Personal'?'selected':'' ?>>Personal</option>
              <option value='Study' <?= $settings['default_workspace']==='Study'?'selected':'' ?>>Study</option>
              <option value='Team' <?= $settings['default_workspace']==='Team'?'selected':'' ?>>Team</option>
            </select>
          </div>
        </div>

        <div class='row'>
          <div>
            <strong>Compact mode</strong>
            <div style='color:var(--mid);font-size:13px'>Reduce spacing to fit more content on screen.</div>
          </div>
          <label class='toggle'>
            <input type='checkbox' name='compact_mode' <?= $settings['compact_mode'] ? 'checked' : '' ?>>
            <span></span>
          </label>
        </div>

        <div class='row'>
          <div>
            <strong>Show completed tasks</strong>
            <div style='color:var(--mid);font-size:13px'>Keep completed items visible in task lists.</div>
          </div>
          <label class='toggle'>
            <input type='checkbox' name='show_completed' <?= $settings['show_completed'] ? 'checked' : '' ?>>
            <span></span>
          </label>
        </div>
      </section>

      <!-- Privacy -->
      <section class='card' id='privacy'>
        <h2>Data & Privacy</h2>
        <p class='section'>Export your data, clear local cache, or manage account removal.</p>

        <div class='storage'>
          <strong>Storage usage</strong>
          <div class='bar'><div></div></div>

          <div class='grid'>
            <div><strong>Journals</strong><div>15 MB</div></div>
            <div><strong>Calendar</strong><div>2 MB</div></div>
            <div><strong>Workspace</strong><div>25 MB</div></div>
            <div><strong>Total</strong><div>42 MB / 60 MB</div></div>
          </div>
        </div>

        <div class='actions' style='margin-top:18px'>
          <button type='button' class='btn'>Export My Data</button>
          <button type='button' class='btn'>Download Journal</button>
          <button type='button' class='btn'>Download Calendar</button>
          <button type='button' class='btn'>Clear Cache</button>
          <button type='button' class='btn danger'>Delete Account</button>
        </div>
      </section>

      <!-- System -->
      <section class='card' id='system'>
        <h2>System Information</h2>

        <div class='grid'>
          <div class='field'>
            <label>Application Version</label>
            <div class='readonly'>SYNCUBE 1.0.0</div>
          </div>

          <div class='field'>
            <label>Database</label>
            <div class='readonly'>Connected</div>
          </div>

          <div class='field'>
            <label>PHP Version</label>
            <div class='readonly'><?= phpversion() ?></div>
          </div>

          <div class='field'>
            <label>Server Status</label>
            <div class='readonly'>Online</div>
          </div>

          <div class='field'>
            <label>Storage Used</label>
            <div class='readonly'>42 MB</div>
          </div>

          <div class='field'>
            <label>Last Backup</label>
            <div class='readonly'>Today, <?= date('H:i') ?></div>
          </div>
        </div>
      </section>

      <!-- Sticky save bar -->
      <div class='savebar'>
        <div>
          <strong>Unsaved changes</strong>
          <div style='color:var(--mid);font-size:13px'>Review your preferences before saving.</div>
        </div>

        <div class='actions'>
          <button type='reset' class='btn'>Reset</button>
          <button type='submit' class='btn primary'>Save Changes</button>
        </div>
      </div>

    </div>
  </div>
</form>
</main>

<script>
function toggleDropdown(){
  document.getElementById('profileWrap').classList.toggle('open');
}

document.addEventListener('click', e => {
  if (!e.target.closest('#profileWrap')) {
    document.getElementById('profileWrap').classList.remove('open');
  }
});

// remove ?saved=1 after toast
if (location.search.includes('saved=1')) {
  history.replaceState({}, document.title, location.pathname);
}
</script>

</body>
</html>