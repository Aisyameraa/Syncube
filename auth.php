<?php
session_start();
require_once 'db_connect.php';

// Redirect if already logged in
if (isset($_SESSION['id'])) {
    header('Location: auth.php');
    exit;
}

$error = '';
$success = '';
$mode = isset($_GET['mode']) && $_GET['mode'] === 'signup' ? 'signup' : 'login';

// ── HANDLE FORM SUBMISSION ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── LOGOUT ──
    if ($action === 'logout') {
        $_SESSION = [];
        session_unset();
        session_destroy();
        header('Location: logout.html');
        exit;
    }

    $db = getDB();

    // ── LOGIN ──
    if ($action === 'login') {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $stmt = $db->prepare("SELECT id, full_name, username, email, password_hash FROM users WHERE email = ? OR username = ? LIMIT 1");
            $stmt->bind_param('ss', $identifier, $identifier);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Update last login
                $upd = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $upd->bind_param('i', $user['id']);
                $upd->execute();
                $upd->close();

                // Regenerate the session ID on login to avoid session fixation and to
                // make sure we're not inheriting any leftover state from a previous user
                // who may have used this same browser/session before.
                session_regenerate_id(true);

                // Clear out any stale mock profile data from a prior login so
                // profile.php re-seeds it fresh from THIS user's real data.
                unset($_SESSION['user_data']);

                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['full_name'];
                $_SESSION['username']   = $user['username'];
                $_SESSION['user_email'] = $user['email']; // <-- this was missing; profile.php relies on it
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid email/username or password.';
            }
        }
        $mode = 'login';
    }

    // ── SIGNUP ──
    elseif ($action === 'signup') {
        $full_name        = trim($_POST['full_name'] ?? '');
        $username         = trim($_POST['username'] ?? '');
        $email            = trim($_POST['email'] ?? '');
        $password         = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
            $error = 'Username must be 3–30 characters (letters, numbers, underscores only).';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            // Check uniqueness
            $chk = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
            $chk->bind_param('ss', $email, $username);
            $chk->execute();
            $chk->store_result();

            if ($chk->num_rows > 0) {
                $error = 'An account with that email or username already exists.';
                $chk->close();
            } else {
                $chk->close();
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $db->prepare("INSERT INTO users (full_name, username, email, password_hash) VALUES (?, ?, ?, ?)");
                $ins->bind_param('ssss', $full_name, $username, $email, $hash);
                if ($ins->execute()) {
                    $success = 'Account created! You can now sign in.';
                    $mode = 'login';
                } else {
                    $error = 'Something went wrong. Please try again.';
                }
                $ins->close();
            }
        }
        if (!empty($error)) $mode = 'signup';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>SYNCUBE — <?= $mode === 'signup' ? 'Create Account' : 'Welcome Back' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;1,300;1,400&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500&display=swap" rel="stylesheet" />
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --sage:         #8a9e8c;
  --sage-light:   #c8d8c9;
  --sage-pale:    #eef3ee;
  --sage-deep:    #5e7a60;
  --stone:        #a89f96;
  --stone-light:  #e8e3de;
  --stone-pale:   #f4f1ee;
  --cream:        #faf8f5;
  --warm-white:   #f7f5f2;
  --text-dark:    #2c2a27;
  --text-mid:     #6b6760;
  --text-muted:   #a09d9a;
  --accent:       #7c8f7e;
  --accent-warm:  #c4a882;
  --accent-blush: #d4b5a8;
  --border:       rgba(44,42,39,0.08);
  --border-soft:  rgba(44,42,39,0.05);
  --shadow-soft:  0 2px 20px rgba(44,42,39,0.06);
  --shadow-card:  0 1px 8px rgba(44,42,39,0.07);
  --radius-sm:    10px;
  --radius-md:    16px;
  --radius-lg:    22px;
  --ff-display:   'Cormorant Garamond', Georgia, serif;
  --ff-body:      'DM Sans', system-ui, sans-serif;
  --transition:   0.22s ease;
  --panel-w:      480px;
}

html { scroll-behavior: smooth; height: 100%; }

body {
  font-family: var(--ff-body);
  background: var(--cream);
  color: var(--text-dark);
  min-height: 100vh;
  font-size: 14px;
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
  display: flex;
}

/* ─── SPLIT LAYOUT ─── */
.auth-wrapper {
  display: flex;
  width: 100%;
  min-height: 100vh;
}

/* ─── LEFT PANEL — Illustration ─── */
.left-panel {
  flex: 1;
  position: relative;
  background: #e8f0e8;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 40px;
}

/* Layered background */
.left-panel::before {
  content: '';
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 80% 60% at 20% 80%, rgba(138,158,140,0.22) 0%, transparent 60%),
    radial-gradient(ellipse 60% 80% at 85% 10%, rgba(196,168,130,0.13) 0%, transparent 55%),
    radial-gradient(ellipse 100% 70% at 50% 50%, rgba(238,243,238,0.5) 0%, transparent 100%);
  z-index: 0;
}

/* Grain texture overlay */
.left-panel::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.035'/%3E%3C/svg%3E");
  opacity: 0.6;
  pointer-events: none;
  z-index: 1;
}

.left-content {
  position: relative;
  z-index: 2;
  text-align: center;
  max-width: 420px;
}

/* ─── MAIN SVG ILLUSTRATION ─── */
.hero-illustration {
  width: 340px;
  height: 340px;
  margin: 0 auto 36px;
  animation: float 6s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50%       { transform: translateY(-12px); }
}

.left-tagline {
  font-family: var(--ff-display);
  font-size: 32px;
  font-weight: 300;
  color: var(--text-dark);
  line-height: 1.2;
  letter-spacing: 0.01em;
  margin-bottom: 14px;
}

.left-tagline em {
  font-style: italic;
  color: var(--accent);
}

.left-sub {
  font-family: var(--ff-body);
  font-size: 13.5px;
  color: var(--text-mid);
  line-height: 1.7;
  font-weight: 300;
  max-width: 320px;
  margin: 0 auto 32px;
}

/* Floating feature pills */
.feature-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: center;
}

.pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 14px;
  background: rgba(255,255,255,0.65);
  border: 1px solid var(--border);
  border-radius: 40px;
  font-size: 12px;
  color: var(--text-mid);
  font-weight: 400;
  backdrop-filter: blur(8px);
  transition: all var(--transition);
}

.pill:hover {
  background: rgba(255,255,255,0.85);
  transform: translateY(-1px);
}

.pill-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--sage);
  flex-shrink: 0;
}

.pill:nth-child(2) .pill-dot { background: var(--accent-warm); }
.pill:nth-child(3) .pill-dot { background: var(--accent-blush); }
.pill:nth-child(4) .pill-dot { background: var(--sage-deep); }

/* Bottom brand mark */
.left-brand {
  position: absolute;
  bottom: 28px;
  left: 50%;
  transform: translateX(-50%);
  font-family: var(--ff-display);
  font-size: 13px;
  color: var(--text-muted);
  letter-spacing: 0.15em;
  text-transform: uppercase;
  z-index: 2;
}

/* ─── RIGHT PANEL — Auth form ─── */
.right-panel {
  width: var(--panel-w);
  flex-shrink: 0;
  background: var(--warm-white);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 52px 52px;
  border-left: 1px solid var(--border);
  position: relative;
  overflow-y: auto;
}

/* Top-right decorative element */
.right-panel::before {
  content: '';
  position: absolute;
  top: -80px; right: -80px;
  width: 220px; height: 220px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(138,158,140,0.07) 0%, transparent 70%);
  pointer-events: none;
}

.form-container {
  width: 100%;
  max-width: 360px;
}

/* ─── LOGO ─── */
.logo-area {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 36px;
}

.logo-icon {
  width: 42px;
  height: 42px;
  flex-shrink: 0;
}

.logo-text {
  display: flex;
  flex-direction: column;
  line-height: 1;
}

.logo-name {
  font-family: var(--ff-display);
  font-size: 24px;
  font-weight: 500;
  color: var(--text-dark);
  letter-spacing: 0.12em;
}

.logo-sub {
  font-family: var(--ff-body);
  font-size: 10px;
  color: var(--text-muted);
  letter-spacing: 0.18em;
  text-transform: uppercase;
  margin-top: 2px;
}

/* ─── HEADING ─── */
.auth-heading {
  margin-bottom: 6px;
}

.auth-heading h1 {
  font-family: var(--ff-display);
  font-size: 30px;
  font-weight: 400;
  color: var(--text-dark);
  letter-spacing: 0.01em;
  line-height: 1.2;
}

.auth-heading p {
  font-family: var(--ff-body);
  font-size: 13px;
  color: var(--text-muted);
  margin-top: 5px;
  font-weight: 300;
}

/* ─── TAB SWITCHER ─── */
.tab-switcher {
  display: flex;
  background: var(--stone-light);
  border-radius: var(--radius-sm);
  padding: 3px;
  margin: 24px 0 28px;
  gap: 2px;
}

.tab-btn {
  flex: 1;
  padding: 9px 0;
  border: none;
  background: transparent;
  font-family: var(--ff-body);
  font-size: 13px;
  color: var(--text-muted);
  font-weight: 400;
  border-radius: 8px;
  cursor: pointer;
  transition: all var(--transition);
  letter-spacing: 0.02em;
}

.tab-btn.active {
  background: var(--warm-white);
  color: var(--text-dark);
  font-weight: 500;
  box-shadow: var(--shadow-card);
}

/* ─── ALERTS ─── */
.alert {
  padding: 11px 15px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  margin-bottom: 20px;
  display: flex;
  align-items: flex-start;
  gap: 9px;
  line-height: 1.5;
}

.alert-error {
  background: rgba(212,181,168,0.2);
  border: 1px solid rgba(212,181,168,0.45);
  color: #7a4c3c;
}

.alert-success {
  background: rgba(138,158,140,0.12);
  border: 1px solid rgba(138,158,140,0.3);
  color: var(--sage-deep);
}

.alert svg { flex-shrink: 0; margin-top: 1px; }

/* ─── FORM ─── */
.auth-form { display: flex; flex-direction: column; gap: 16px; }

.field-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  font-size: 12px;
  font-weight: 500;
  color: var(--text-mid);
  letter-spacing: 0.04em;
}

.field-wrap {
  position: relative;
}

.field-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  pointer-events: none;
  display: flex;
  align-items: center;
}

.auth-input {
  width: 100%;
  padding: 11px 14px 11px 40px;
  background: var(--cream);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  font-family: var(--ff-body);
  font-size: 14px;
  color: var(--text-dark);
  transition: all var(--transition);
  outline: none;
  font-weight: 300;
}

.auth-input::placeholder { color: var(--text-muted); font-weight: 300; }

.auth-input:focus {
  border-color: var(--sage);
  background: #fff;
  box-shadow: 0 0 0 3px rgba(138,158,140,0.1);
}

.auth-input:hover:not(:focus) {
  border-color: var(--stone);
}

/* Password toggle */
.pw-toggle {
  position: absolute;
  right: 13px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  color: var(--text-muted);
  padding: 2px;
  display: flex;
  align-items: center;
  transition: color var(--transition);
}
.pw-toggle:hover { color: var(--text-mid); }

/* Two col layout */
.field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

/* Password strength */
.pw-strength {
  display: none;
  flex-direction: column;
  gap: 4px;
  margin-top: 6px;
}

.pw-strength.visible { display: flex; }

.strength-bar {
  height: 3px;
  border-radius: 2px;
  background: var(--stone-light);
  overflow: hidden;
}

.strength-fill {
  height: 100%;
  border-radius: 2px;
  transition: width 0.3s ease, background 0.3s ease;
  width: 0%;
}

.strength-label {
  font-size: 11px;
  color: var(--text-muted);
}

/* Submit button */
.submit-btn {
  width: 100%;
  padding: 13px 24px;
  background: var(--text-dark);
  color: var(--cream);
  border: none;
  border-radius: var(--radius-sm);
  font-family: var(--ff-body);
  font-size: 14px;
  font-weight: 400;
  letter-spacing: 0.04em;
  cursor: pointer;
  transition: all var(--transition);
  margin-top: 4px;
  position: relative;
  overflow: hidden;
}

.submit-btn::after {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(138,158,140,0.15) 0%, transparent 60%);
  opacity: 0;
  transition: opacity var(--transition);
}

.submit-btn:hover {
  background: var(--sage-deep);
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(44,42,39,0.15);
}
.submit-btn:hover::after { opacity: 1; }
.submit-btn:active { transform: translateY(0); }

/* Divider */
.divider {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 4px 0;
}
.divider-line {
  flex: 1;
  height: 1px;
  background: var(--border);
}
.divider-text {
  font-size: 12px;
  color: var(--text-muted);
  font-weight: 300;
}

/* Switch link */
.switch-text {
  text-align: center;
  font-size: 13px;
  color: var(--text-muted);
  font-weight: 300;
}

.switch-text a {
  color: var(--accent);
  text-decoration: none;
  font-weight: 500;
  transition: color var(--transition);
}
.switch-text a:hover { color: var(--sage-deep); text-decoration: underline; }

/* Forgot password */
.forgot-link {
  text-align: right;
  font-size: 12px;
}
.forgot-link a {
  color: var(--text-muted);
  text-decoration: none;
  transition: color var(--transition);
}
.forgot-link a:hover { color: var(--accent); }

/* Terms note */
.terms-note {
  font-size: 11.5px;
  color: var(--text-muted);
  line-height: 1.6;
  text-align: center;
  margin-top: 4px;
  font-weight: 300;
}

/* ─── RESPONSIVE ─── */
@media (max-width: 860px) {
  .left-panel { display: none; }
  .right-panel {
    width: 100%;
    border-left: none;
    padding: 40px 28px;
  }
}

/* ─── ENTRANCE ANIMATION ─── */
.form-container {
  animation: slideUp 0.55s cubic-bezier(0.22,1,0.36,1) both;
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(18px); }
  to   { opacity: 1; transform: translateY(0); }
}

.left-content {
  animation: fadeIn 0.7s ease both;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>

<div class="auth-wrapper">

  <!-- ══════════ LEFT PANEL ══════════ -->
  <div class="left-panel">
    <div class="left-content">

      <!-- Hero Illustration -->
      <svg class="hero-illustration" viewBox="0 0 340 340" fill="none" xmlns="http://www.w3.org/2000/svg">
        <!-- Background circle -->
        <circle cx="170" cy="170" r="150" fill="rgba(138,158,140,0.08)" />
        <circle cx="170" cy="170" r="120" fill="rgba(138,158,140,0.06)" />

        <!-- Desk surface -->
        <ellipse cx="170" cy="235" rx="130" ry="18" fill="rgba(168,159,150,0.18)" />

        <!-- Journal / planner book -->
        <rect x="75" y="135" width="90" height="115" rx="6" fill="#e8ede8" stroke="#c5d1c6" stroke-width="1.5"/>
        <rect x="75" y="135" width="8" height="115" rx="4" fill="#c5d1c6"/>
        <!-- Journal lines -->
        <line x1="93" y1="162" x2="155" y2="162" stroke="#b8c9b9" stroke-width="1.2" stroke-linecap="round"/>
        <line x1="93" y1="174" x2="155" y2="174" stroke="#b8c9b9" stroke-width="1.2" stroke-linecap="round"/>
        <line x1="93" y1="186" x2="145" y2="186" stroke="#b8c9b9" stroke-width="1.2" stroke-linecap="round"/>
        <line x1="93" y1="198" x2="155" y2="198" stroke="#b8c9b9" stroke-width="1.2" stroke-linecap="round"/>
        <line x1="93" y1="210" x2="135" y2="210" stroke="#b8c9b9" stroke-width="1.2" stroke-linecap="round"/>
        <!-- Small bookmark -->
        <rect x="149" y="132" width="8" height="20" rx="1" fill="#d4b5a8"/>

        <!-- Laptop / screen -->
        <rect x="155" y="105" width="120" height="85" rx="8" fill="#f0f4f0" stroke="#c5d1c6" stroke-width="1.5"/>
        <rect x="160" y="110" width="110" height="72" rx="5" fill="#e0ebe0"/>
        <!-- Screen content mockup -->
        <rect x="166" y="117" width="50" height="5" rx="2.5" fill="rgba(94,122,96,0.45)"/>
        <rect x="166" y="127" width="98" height="3" rx="1.5" fill="rgba(168,159,150,0.35)"/>
        <rect x="166" y="133" width="80" height="3" rx="1.5" fill="rgba(168,159,150,0.3)"/>
        <rect x="166" y="143" width="95" height="3" rx="1.5" fill="rgba(168,159,150,0.25)"/>
        <!-- Small chart bars on screen -->
        <rect x="218" y="152" width="8" height="18" rx="2" fill="rgba(138,158,140,0.5)"/>
        <rect x="229" y="158" width="8" height="12" rx="2" fill="rgba(196,168,130,0.5)"/>
        <rect x="240" y="148" width="8" height="22" rx="2" fill="rgba(138,158,140,0.35)"/>
        <rect x="251" y="155" width="8" height="15" rx="2" fill="rgba(212,181,168,0.5)"/>
        <!-- Laptop base -->
        <path d="M148 190 L160 190 L275 190 L280 196 L143 196 Z" fill="#d8ddd8" stroke="#c5d1c6" stroke-width="1"/>
        <ellipse cx="215" cy="196" rx="18" ry="2" fill="#c5ccc5"/>

        <!-- Floating task cards -->
        <!-- Card 1 -->
        <rect x="42" y="88" width="80" height="36" rx="8" fill="white" stroke="rgba(138,158,140,0.3)" stroke-width="1"/>
        <circle cx="56" cy="106" r="5" fill="rgba(138,158,140,0.3)" stroke="#8a9e8c" stroke-width="1"/>
        <line x1="66" y1="101" x2="108" y2="101" stroke="#c5d1c6" stroke-width="1.3" stroke-linecap="round"/>
        <line x1="66" y1="110" x2="100" y2="110" stroke="#c5d1c6" stroke-width="1.3" stroke-linecap="round"/>

        <!-- Card 2 -->
        <rect x="215" y="68" width="90" height="36" rx="8" fill="white" stroke="rgba(196,168,130,0.35)" stroke-width="1"/>
        <rect x="226" y="80" width="10" height="10" rx="2" fill="rgba(196,168,130,0.25)" stroke="#c4a882" stroke-width="1"/>
        <line x1="242" y1="83" x2="292" y2="83" stroke="#c5d1c6" stroke-width="1.3" stroke-linecap="round"/>
        <line x1="242" y1="91" x2="280" y2="91" stroke="#c5d1c6" stroke-width="1.3" stroke-linecap="round"/>

        <!-- Floating dot sparkles -->
        <circle cx="50" cy="65" r="4" fill="rgba(138,158,140,0.35)"/>
        <circle cx="290" cy="145" r="3" fill="rgba(196,168,130,0.45)"/>
        <circle cx="305" cy="100" r="5" fill="rgba(212,181,168,0.3)"/>
        <circle cx="38" cy="165" r="3" fill="rgba(138,158,140,0.25)"/>
        <circle cx="130" cy="80" r="2.5" fill="rgba(196,168,130,0.4)"/>

        <!-- Small pen -->
        <g transform="rotate(-35, 100, 200)">
          <rect x="95" y="185" width="6" height="30" rx="3" fill="#c4a882" opacity="0.8"/>
          <polygon points="95,215 101,215 98,225" fill="#a88a6a" opacity="0.8"/>
          <rect x="95" y="183" width="6" height="5" rx="1" fill="#d4b5a8" opacity="0.7"/>
        </g>

        <!-- Soft glow -->
        <circle cx="170" cy="170" r="100" fill="url(#glowGrad)" opacity="0.3"/>
        <defs>
          <radialGradient id="glowGrad" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#8a9e8c" stop-opacity="0.1"/>
            <stop offset="100%" stop-color="#8a9e8c" stop-opacity="0"/>
          </radialGradient>
        </defs>
      </svg>

      <h2 class="left-tagline">Plan with <em>intention</em>,<br>journal with clarity.</h2>
      <p class="left-sub">SYNCUBE brings your tasks, plans, and thoughts together in one calm, focused space — designed for how you actually think.</p>

      <div class="feature-pills">
        <span class="pill"><span class="pill-dot"></span>Task Management</span>
        <span class="pill"><span class="pill-dot"></span>Personal Planning</span>
        <span class="pill"><span class="pill-dot"></span>Digital Journal</span>
        <span class="pill"><span class="pill-dot"></span>Focus Tools</span>
      </div>
    </div>

    <div class="left-brand">Syncube &bull; Your Personal Space</div>
  </div>

  <!-- ══════════ RIGHT PANEL ══════════ -->
  <div class="right-panel">
    <div class="form-container">

      <!-- Logo -->
      <div class="logo-area">
        <!-- SVG Logo -->
        <svg class="logo-icon" viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg">
          <!-- Outer cube frame -->
          <rect x="3" y="3" width="36" height="36" rx="9" fill="var(--sage-pale)" stroke="var(--sage-light)" stroke-width="1.5"/>
          <!-- Cube isometric shape -->
          <polygon points="21,8 33,15 33,27 21,34 9,27 9,15" fill="none" stroke="var(--sage)" stroke-width="1.8" stroke-linejoin="round"/>
          <!-- Cube inner lines -->
          <line x1="21" y1="8" x2="21" y2="34" stroke="var(--sage)" stroke-width="1.2" stroke-dasharray="2,2" opacity="0.5"/>
          <line x1="9" y1="15" x2="33" y2="15" stroke="var(--sage)" stroke-width="1.2" stroke-dasharray="2,2" opacity="0.5"/>
          <line x1="9" y1="27" x2="33" y2="27" stroke="var(--sage)" stroke-width="1.2" stroke-dasharray="2,2" opacity="0.5"/>
          <!-- Center dot -->
          <circle cx="21" cy="21" r="3" fill="var(--sage)" opacity="0.7"/>
          <!-- Small spark dots -->
          <circle cx="33" cy="15" r="2" fill="var(--accent-warm)" opacity="0.8"/>
          <circle cx="9" cy="27" r="1.5" fill="var(--accent-blush)" opacity="0.7"/>
        </svg>
        <div class="logo-text">
          <span class="logo-name">SYNCUBE</span>
          <span class="logo-sub">Personal Planning System</span>
        </div>
      </div>

      <!-- Heading changes based on mode -->
      <div class="auth-heading">
        <h1 id="formTitle"><?= $mode === 'signup' ? 'Create account' : 'Welcome back' ?></h1>
        <p id="formSubtitle"><?= $mode === 'signup' ? 'Start your planning journey today.' : 'Sign in to continue to your space.' ?></p>
      </div>

      <!-- Tab Switcher -->
      <div class="tab-switcher">
        <button class="tab-btn <?= $mode === 'login' ? 'active' : '' ?>" onclick="switchMode('login')" type="button">Sign In</button>
        <button class="tab-btn <?= $mode === 'signup' ? 'active' : '' ?>" onclick="switchMode('signup')" type="button">Sign Up</button>
      </div>

      <!-- Alerts -->
      <?php if (!empty($error)): ?>
      <div class="alert alert-error">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="#b05040" stroke-width="1.4"/><line x1="8" y1="5" x2="8" y2="9" stroke="#b05040" stroke-width="1.5" stroke-linecap="round"/><circle cx="8" cy="11.5" r="0.8" fill="#b05040"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
      <div class="alert alert-success">
        <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="#5e7a60" stroke-width="1.4"/><path d="M5 8.5l2 2 4-4" stroke="#5e7a60" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <?= htmlspecialchars($success) ?>
      </div>
      <?php endif; ?>

      <!-- ── LOGIN FORM ── -->
      <form id="loginForm" class="auth-form" method="POST" action="auth.php" style="<?= $mode === 'login' ? '' : 'display:none' ?>">
        <input type="hidden" name="action" value="login" />

        <div class="field-group">
          <label class="field-label" for="l_identifier">Email or Username</label>
          <div class="field-wrap">
            <span class="field-icon">
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M2.5 4h11a1 1 0 011 1v6a1 1 0 01-1 1h-11a1 1 0 01-1-1V5a1 1 0 011-1z" stroke="#a09d9a" stroke-width="1.3"/><path d="M2.5 4.5l5.5 4 5.5-4" stroke="#a09d9a" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <input class="auth-input" type="text" id="l_identifier" name="identifier"
              placeholder="you@email.com or username"
              value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
              autocomplete="username" required />
          </div>
        </div>

        <div class="field-group">
          <label class="field-label" for="l_password">Password</label>
          <div class="field-wrap">
            <span class="field-icon">
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><rect x="3" y="7" width="10" height="8" rx="2" stroke="#a09d9a" stroke-width="1.3"/><path d="M5.5 7V5a2.5 2.5 0 015 0v2" stroke="#a09d9a" stroke-width="1.3"/><circle cx="8" cy="11" r="1" fill="#a09d9a"/></svg>
            </span>
            <input class="auth-input" type="password" id="l_password" name="password"
              placeholder="Your password"
              autocomplete="current-password" required />
            <button type="button" class="pw-toggle" onclick="togglePw('l_password', this)">
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="#a09d9a" stroke-width="1.3"/><circle cx="8" cy="8" r="2" stroke="#a09d9a" stroke-width="1.3"/></svg>
            </button>
          </div>
          <div class="forgot-link"><a href="#">Forgot password?</a></div>
        </div>

        <button class="submit-btn" type="submit">Sign In</button>

        <p class="switch-text">Don't have an account? <a href="#" onclick="switchMode('signup'); return false;">Create one</a></p>
      </form>

      <!-- ── SIGNUP FORM ── -->
      <form id="signupForm" class="auth-form" method="POST" action="auth.php" style="<?= $mode === 'signup' ? '' : 'display:none' ?>">
        <input type="hidden" name="action" value="signup" />

        <div class="field-group">
          <label class="field-label" for="s_full_name">Full Name</label>
          <div class="field-wrap">
            <span class="field-icon">
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="5" r="3" stroke="#a09d9a" stroke-width="1.3"/><path d="M2 14c0-3.314 2.686-6 6-6s6 2.686 6 6" stroke="#a09d9a" stroke-width="1.3" stroke-linecap="round"/></svg>
            </span>
            <input class="auth-input" type="text" id="s_full_name" name="full_name"
              placeholder="Your full name"
              value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
              autocomplete="name" required />
          </div>
        </div>

        <div class="field-row">
          <div class="field-group">
            <label class="field-label" for="s_username">Username</label>
            <div class="field-wrap">
              <span class="field-icon">
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M8 1a7 7 0 100 14A7 7 0 008 1z" stroke="#a09d9a" stroke-width="1.3"/><path d="M8 5v4l2.5 1.5" stroke="#a09d9a" stroke-width="1.3" stroke-linecap="round"/></svg>
              </span>
              <input class="auth-input" type="text" id="s_username" name="username"
                placeholder="username"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                autocomplete="username" required />
            </div>
          </div>

          <div class="field-group">
            <label class="field-label" for="s_email">Email</label>
            <div class="field-wrap">
              <span class="field-icon">
                <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M2.5 4h11a1 1 0 011 1v6a1 1 0 01-1 1h-11a1 1 0 01-1-1V5a1 1 0 011-1z" stroke="#a09d9a" stroke-width="1.3"/><path d="M2.5 4.5l5.5 4 5.5-4" stroke="#a09d9a" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </span>
              <input class="auth-input" type="email" id="s_email" name="email"
                placeholder="you@email.com"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                autocomplete="email" required />
            </div>
          </div>
        </div>

        <div class="field-group">
          <label class="field-label" for="s_password">Password</label>
          <div class="field-wrap">
            <span class="field-icon">
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><rect x="3" y="7" width="10" height="8" rx="2" stroke="#a09d9a" stroke-width="1.3"/><path d="M5.5 7V5a2.5 2.5 0 015 0v2" stroke="#a09d9a" stroke-width="1.3"/><circle cx="8" cy="11" r="1" fill="#a09d9a"/></svg>
            </span>
            <input class="auth-input" type="password" id="s_password" name="password"
              placeholder="At least 8 characters"
              autocomplete="new-password" required
              oninput="checkStrength(this.value)" />
            <button type="button" class="pw-toggle" onclick="togglePw('s_password', this)">
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="#a09d9a" stroke-width="1.3"/><circle cx="8" cy="8" r="2" stroke="#a09d9a" stroke-width="1.3"/></svg>
            </button>
          </div>
          <div class="pw-strength" id="pwStrength">
            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
            <span class="strength-label" id="strengthLabel">Strength: —</span>
          </div>
        </div>

        <div class="field-group">
          <label class="field-label" for="s_confirm">Confirm Password</label>
          <div class="field-wrap">
            <span class="field-icon">
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><rect x="3" y="7" width="10" height="8" rx="2" stroke="#a09d9a" stroke-width="1.3"/><path d="M5.5 7V5a2.5 2.5 0 015 0v2" stroke="#a09d9a" stroke-width="1.3"/><path d="M6 11l1.5 1.5L10 9.5" stroke="#a09d9a" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <input class="auth-input" type="password" id="s_confirm" name="confirm_password"
              placeholder="Repeat your password"
              autocomplete="new-password" required />
            <button type="button" class="pw-toggle" onclick="togglePw('s_confirm', this)">
              <svg width="15" height="15" viewBox="0 0 16 16" fill="none"><path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z" stroke="#a09d9a" stroke-width="1.3"/><circle cx="8" cy="8" r="2" stroke="#a09d9a" stroke-width="1.3"/></svg>
            </button>
          </div>
        </div>

        <button class="submit-btn" type="submit">Create Account</button>

        <p class="terms-note">By creating an account you agree to our Terms of Service and Privacy Policy.</p>

        <p class="switch-text">Already have an account? <a href="#" onclick="switchMode('login'); return false;">Sign in</a></p>
      </form>

    </div>
  </div>
</div>

<script>
// ── TAB SWITCHER ──
function switchMode(mode) {
  const loginForm  = document.getElementById('loginForm');
  const signupForm = document.getElementById('signupForm');
  const title      = document.getElementById('formTitle');
  const subtitle   = document.getElementById('formSubtitle');
  const tabs       = document.querySelectorAll('.tab-btn');

  if (mode === 'login') {
    loginForm.style.display  = '';
    signupForm.style.display = 'none';
    title.textContent    = 'Welcome back';
    subtitle.textContent = 'Sign in to continue to your space.';
    tabs[0].classList.add('active');
    tabs[1].classList.remove('active');
  } else {
    loginForm.style.display  = 'none';
    signupForm.style.display = '';
    title.textContent    = 'Create account';
    subtitle.textContent = 'Start your planning journey today.';
    tabs[1].classList.add('active');
    tabs[0].classList.remove('active');
  }
}

// ── PASSWORD TOGGLE ──
function togglePw(id, btn) {
  const input = document.getElementById(id);
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  btn.style.opacity = isHidden ? '0.7' : '1';
}

// ── PASSWORD STRENGTH ──
function checkStrength(val) {
  const wrap  = document.getElementById('pwStrength');
  const fill  = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');

  if (!val) { wrap.classList.remove('visible'); return; }
  wrap.classList.add('visible');

  let score = 0;
  if (val.length >= 8)  score++;
  if (val.length >= 12) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  const levels = [
    { pct: '15%', color: '#d4b5a8', text: 'Very weak'  },
    { pct: '30%', color: '#c4a882', text: 'Weak'        },
    { pct: '55%', color: '#e6c87a', text: 'Fair'        },
    { pct: '78%', color: '#8a9e8c', text: 'Strong'      },
    { pct: '100%',color: '#5e7a60', text: 'Very strong' },
  ];
  const l = levels[Math.min(score, 4)];
  fill.style.width      = l.pct;
  fill.style.background = l.color;
  label.textContent     = 'Strength: ' + l.text;
}

// ── INIT: if PHP set mode to signup, show correct tab ──
<?php if ($mode === 'signup'): ?>
// Already rendered correctly via PHP
<?php endif; ?>
</script>
</body>
</html>