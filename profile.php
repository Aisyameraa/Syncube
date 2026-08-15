<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

// The real, currently logged-in username & email (these must always win over mock data)
$currentUserName = $_SESSION['user_name'] ?? 'Guest User';
$currentUserEmail = $_SESSION['user_email'] ?? ($_SESSION['email'] ?? 'unknown@syncube.io');
$nameParts = explode(' ', trim($currentUserName), 2);

// Initialize session mock database if empty to retain state across edits/saves.
// IMPORTANT: 'first_name'/'last_name' and 'email' are seeded from the ACTUAL logged-in
// user/signup data, not hardcoded placeholders — this was the bug causing the
// header and email to show the wrong info (it always defaulted to a mock record).
if (!isset($_SESSION['user_data'])) {
    $_SESSION['user_data'] = [
        'first_name' => $nameParts[0] ?? 'Guest',
        'last_name' => $nameParts[1] ?? '',
        'role' => 'Personal',
        'email' => $currentUserEmail,
        'phone' => '',
        'dob' => '',
        'country' => '',
        'city' => '',
        'postal_code' => '',
        'user_id' => 'SYNC-2026-9041',
        'joined_date' => 'January 14, 2026',
        'status_vibe' => '🌿 Focused',
        'banner_url' => 'http://googleusercontent.com/image_collection/image_retrieval/12358642552366925941_0',
        'avatar_url' => 'http://googleusercontent.com/image_collection/image_retrieval/6931729005843437886_0'
    ];
}

$user = &$_SESSION['user_data'];

// Safety net: if the logged-in username/email ever changes (or was updated
// elsewhere, e.g. at signup) but the mock profile record hasn't caught up yet,
// keep the header and email in sync with the real current user rather than stale data.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sessionNameParts = explode(' ', trim($currentUserName), 2);
    if (!empty($_SESSION['user_name']) && trim($user['first_name'] . ' ' . $user['last_name']) !== $_SESSION['user_name']) {
        $user['first_name'] = $sessionNameParts[0] ?? $user['first_name'];
        $user['last_name'] = $sessionNameParts[1] ?? '';
    }
    $sessionEmail = $_SESSION['user_email'] ?? ($_SESSION['email'] ?? null);
    if (!empty($sessionEmail) && $user['email'] !== $sessionEmail) {
        $user['email'] = $sessionEmail;
    }
}

// Backfill defaults for anyone whose session was created before these fields existed
$user += [
    'first_name' => $nameParts[0] ?? 'Guest', 'last_name' => $nameParts[1] ?? '',
    'phone' => '', 'dob' => '', 'country' => '', 'city' => '', 'postal_code' => ''
];

// Convenience field used throughout the nav/hero/credentials markup
$user['name'] = trim($user['first_name'] . ' ' . $user['last_name']);
$nameWords = explode(' ', $user['name']);
$user['initials'] = strtoupper(substr($nameWords[0], 0, 1) . substr($nameWords[1] ?? '', 0, 1));

// Simple mock form submission logic to save changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user['first_name'] = $_POST['first_name'] ?? $user['first_name'];
    $user['last_name'] = $_POST['last_name'] ?? $user['last_name'];
    $user['dob'] = $_POST['dob'] ?? $user['dob'];
    $user['email'] = $_POST['email'] ?? $user['email'];
    $user['phone'] = $_POST['phone'] ?? $user['phone'];
    $user['role'] = $_POST['role'] ?? $user['role'];
    $user['country'] = $_POST['country'] ?? $user['country'];
    $user['city'] = $_POST['city'] ?? $user['city'];
    $user['postal_code'] = $_POST['postal_code'] ?? $user['postal_code'];
    $user['status_vibe'] = $_POST['status_vibe'] ?? $user['status_vibe'];
    
    // Capture base64 uploaded mock images if present
    if (!empty($_POST['banner_base64'])) {
        $user['banner_url'] = $_POST['banner_base64'];
    }
    if (!empty($_POST['avatar_base64'])) {
        $user['avatar_url'] = $_POST['avatar_base64'];
    }

    // Re-calculate name/initials
    $user['name'] = trim($user['first_name'] . ' ' . $user['last_name']);
    $words = explode(' ', $user['name']);
    $user['initials'] = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));

    // Keep the actual session username/email in sync too, so the header stays correct
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    // Redirect after POST so refreshing the page never resubmits the form
    header('Location: profile.php?updated=1');
    exit;
}

// ─── Membership duration (fun little "alive" stat) ───
$joinedTimestamp = strtotime($user['joined_date']);
$daysAsMember = $joinedTimestamp ? max(0, floor((time() - $joinedTimestamp) / 86400)) : 0;

// ─── Profile completeness meter ───
$completenessChecks = [
    !empty($user['dob']),
    !empty($user['phone']),
    !empty($user['country']),
    !empty($user['city']),
    !empty($user['postal_code']),
    !empty($user['status_vibe']),
    !empty($user['avatar_url']),
    !empty($user['banner_url']),
];
$completenessPercent = (int) round((array_sum($completenessChecks) / count($completenessChecks)) * 100);

// ─── Achievement badges (auto-derived, gives the profile a gamified feel) ───
$hasAddress = !empty($user['country']) && !empty($user['city']) && !empty($user['postal_code']);
$hasContact = !empty($user['phone']) && !empty($user['email']);

$badges = [];
if ($completenessPercent === 100) {
    $badges[] = ['icon' => '🏆', 'label' => 'Profile Completionist'];
}
if ($daysAsMember >= 180) {
    $badges[] = ['icon' => '🌳', 'label' => 'Established Member'];
} elseif ($daysAsMember >= 30) {
    $badges[] = ['icon' => '🌱', 'label' => 'Growing Roots'];
} else {
    $badges[] = ['icon' => '✨', 'label' => 'New Arrival'];
}
if ($hasAddress) {
    $badges[] = ['icon' => '📍', 'label' => 'Address Verified'];
}
if ($hasContact) {
    $badges[] = ['icon' => '📞', 'label' => 'Fully Contactable'];
}
if (!empty($user['avatar_url']) && !empty($user['banner_url'])) {
    $badges[] = ['icon' => '🎨', 'label' => 'Visually Styled'];
}

$justUpdated = isset($_GET['updated']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>SYNCUBE — My Profile</title>
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
    --accent-warm: #c4a882;
    --border: rgba(44,42,39,0.08);
    --border-soft: rgba(44,42,39,0.05);
    --shadow-soft: 0 4px 24px rgba(44,42,39,0.06);
    --shadow-card: 0 1px 8px rgba(44,42,39,0.07);
    --radius-sm: 10px;
    --radius-md: 16px;
    --radius-lg: 22px;
    --nav-height: 64px;
    --ff-display: 'Cormorant Garamond', Georgia, serif;
    --ff-body: 'DM Sans', system-ui, sans-serif;
    --transition: 0.22s ease;
  }

  body {
    font-family: var(--ff-body);
    background: var(--cream);
    color: var(--text-dark);
    min-height: 100vh;
    font-size: 14px;
    line-height: 1.6;
    -webkit-font-smoothing: antialiased;
    position: relative;
    overflow-x: hidden;
  }

  /* ─── DYNAMIC BACKGROUND MOTIONS (Gives "Alive" Feel) ─── */
  .bg-blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(100px);
    z-index: 0;
    opacity: 0.45;
    pointer-events: none;
    animation: drift 20s infinite alternate ease-in-out;
  }
  .bg-blob-1 {
    width: 400px; height: 400px;
    background: var(--sage-light);
    top: -100px; right: -50px;
  }
  .bg-blob-2 {
    width: 350px; height: 350px;
    background: var(--stone-light);
    bottom: -100px; left: -100px;
    animation-delay: -5s;
  }

  @keyframes drift {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(40px, 60px) scale(1.15); }
  }

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
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .nav-logo span {
    display: inline-block;
    width: 7px; height: 7px;
    border-radius: 50%;
    background: var(--sage);
  }

  .nav-links {
    display: flex;
    align-items: center;
    gap: 4px;
    list-style: none;
  }

  .nav-links a {
    font-family: var(--ff-body);
    font-size: 13px;
    font-weight: 400;
    color: var(--text-mid);
    text-decoration: none;
    padding: 6px 14px;
    border-radius: var(--radius-sm);
    transition: all var(--transition);
  }

  .nav-links a:hover, .nav-links a.active {
    color: var(--text-dark);
    background: var(--stone-light);
  }

  /* Profile Menu Dropdown */
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
  
  .dropdown {
    position: absolute; top: calc(100% + 8px); right: 0;
    background: var(--warm-white); border: 1px solid var(--border);
    border-radius: var(--radius-md); box-shadow: var(--shadow-soft);
    min-width: 180px; padding: 8px; opacity: 0; transform: translateY(-6px);
    pointer-events: none; transition: all var(--transition); z-index: 200;
  }
  .profile-wrap.open .dropdown { opacity: 1; transform: translateY(0); pointer-events: all; }
  .dropdown-header { padding: 8px 10px 10px; border-bottom: 1px solid var(--border); margin-bottom: 6px; }
  .dropdown-header strong { display: block; font-size: 13px; color: var(--text-dark); }
  .dropdown-header span { font-size: 11px; color: var(--text-muted); }
  .dropdown a { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 8px; font-size: 13px; color: var(--text-mid); text-decoration: none; transition: all var(--transition); }
  .dropdown a:hover { background: var(--stone-light); color: var(--text-dark); }
  .dropdown .divider { height: 1px; background: var(--border); margin: 6px 0; }
  .dropdown a.logout { color: #b85c5c; }
  .dropdown a.logout:hover { background: #fdf0f0; }

  /* ─── MAIN LAYOUT ─── */
  main {
    margin-top: var(--nav-height);
    padding: 40px 36px 60px;
    max-width: 1100px;
    margin-left: auto;
    margin-right: auto;
    position: relative;
    z-index: 1;
  }

  /* ─── ENHANCED HEADER PHOTO CARD ─── */
  .profile-hero-frame {
    background: var(--warm-white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 24px;
    box-shadow: var(--shadow-soft);
  }

  .cover-banner-wrap {
    height: 240px;
    width: 100%;
    background-color: var(--stone-pale);
    background-size: cover;
    background-position: center;
    position: relative;
  }

  /* Banner hover upload overlay */
  .cover-banner-overlay, .avatar-overlay {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(44, 42, 39, 0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    opacity: 0;
    cursor: pointer;
    transition: opacity var(--transition);
    font-size: 12px;
    letter-spacing: 0.05em;
    font-weight: 500;
    text-transform: uppercase;
  }
  .cover-banner-wrap:hover .cover-banner-overlay { opacity: 1; }

  /* Overlapping profile meta bar */
  .hero-meta-bar {
    padding: 0 40px 32px;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    position: relative;
    margin-top: -55px; /* Pulls profile picture up to overlap */
  }

  .meta-user-details {
    display: flex;
    align-items: flex-end;
    gap: 24px;
  }

  /* Profile pic overlay circle */
  .avatar-frame {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    background-color: var(--sage-light);
    border: 4px solid var(--warm-white);
    box-shadow: 0 4px 15px rgba(44, 42, 39, 0.1);
    position: relative;
    overflow: hidden;
    background-size: cover;
    background-position: center;
  }
  .avatar-frame:hover .avatar-overlay { opacity: 1; }

  /* Breathing dynamic focus halo */
  .living-pulse {
    position: absolute;
    width: 100%; height: 100%;
    border-radius: 50%;
    border: 2px solid var(--sage);
    top: 0; left: 0;
    opacity: 0;
    z-index: -1;
    animation: breathing 4s infinite ease-in-out;
  }

  @keyframes breathing {
    0% { transform: scale(1); opacity: 0; }
    50% { transform: scale(1.1); opacity: 0.25; }
    100% { transform: scale(1.2); opacity: 0; }
  }

  .user-badge-fields h2 {
    font-family: var(--ff-display);
    font-size: 32px;
    font-weight: 300;
    color: var(--text-dark);
    line-height: 1.2;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .user-badge-fields p {
    font-size: 13px;
    color: var(--text-mid);
    margin-top: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .status-vibe-badge {
    font-size: 12px;
    background: #fff;
    border: 1px solid var(--border);
    padding: 3px 12px;
    border-radius: 30px;
    font-weight: 400;
    color: var(--text-dark);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 1px 4px rgba(44,42,39,0.03);
  }

  /* ─── GRID SYSTEMS ─── */
  .profile-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 24px;
  }

  .card {
    background: var(--warm-white);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 32px;
    box-shadow: var(--shadow-card);
    transition: transform var(--transition), box-shadow var(--transition);
  }
  .card:hover { box-shadow: var(--shadow-soft); }

  .card-label {
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .card-label::before { content: ''; display: inline-block; width: 4px; height: 4px; border-radius: 50%; background: var(--sage); }

  .bio-text {
    font-family: var(--ff-display);
    font-size: 18px;
    font-weight: 300;
    font-style: italic;
    color: var(--text-dark);
    line-height: 1.6;
    margin-bottom: 28px;
  }

  /* Milestones timeline */
  .milestone-timeline {
    display: flex;
    flex-direction: column;
    gap: 18px;
    border-left: 1px solid var(--border);
    padding-left: 20px;
    margin-left: 6px;
  }

  .milestone-node {
    position: relative;
  }
  .milestone-node::before {
    content: ''; position: absolute; left: -26px; top: 6px;
    width: 10px; height: 10px; border-radius: 50%;
    background: var(--cream); border: 2.5px solid var(--stone);
    transition: all var(--transition);
  }
  .milestone-node.active::before { border-color: var(--sage); background: var(--sage); }

  .m-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
  .m-val { font-size: 14px; font-weight: 400; color: var(--text-dark); margin-top: 2px; }

  /* ─── AUTOMATED GENERAL SYSTEM FIELDS ─── */
  .info-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  .info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border-soft);
  }
  .info-item:last-child { border: none; }
  .info-label { font-size: 13px; color: var(--text-mid); }
  .info-value { font-size: 13px; font-weight: 500; color: var(--text-dark); }
  .info-value.monospace { font-family: monospace; font-size: 12px; color: var(--text-muted); }

  /* ─── FOCUS METRICS GRAPHICS ("Make Looks Alive") ─── */
  .vibe-pulse-panel {
    background: var(--sage-pale);
    border: 1px solid rgba(138,158,140,0.15);
    border-radius: var(--radius-md);
    padding: 18px;
    margin-bottom: 24px;
  }
  
  .vibe-pulse-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
    color: var(--accent);
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
  }

  .pulse-wave-svg {
    width: 100%;
    height: 32px;
    stroke: var(--sage);
    stroke-width: 1.5;
    fill: none;
  }

  /* ─── ACTION CONTROL BUTTONS ─── */
  .btn-sync {
    padding: 9px 22px;
    border-radius: 40px;
    border: 1px solid var(--border);
    background: var(--cream);
    color: var(--text-dark);
    font-family: var(--ff-body);
    font-size: 13px;
    cursor: pointer;
    transition: all var(--transition);
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .btn-sync:hover { background: var(--stone-light); border-color: rgba(44,42,39,0.15); }
  .btn-sync.primary { background: var(--accent); color: #fff; border-color: var(--accent); }
  .btn-sync.primary:hover { background: #6d7f70; }

  /* ─── VIEW OR EDIT STATE SWITCHERS ─── */
  .view-mode { display: block; }
  .edit-mode { display: none; }

  body.is-editing .view-mode { display: none; }
  body.is-editing .edit-mode { display: block; }

  /* ─── INLINE FORM CUSTOM FIELDS ─── */
  .form-group {
    margin-bottom: 16px;
  }
  .form-group label {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    margin-bottom: 6px;
  }
  .form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--cream);
    font-family: var(--ff-body);
    font-size: 13px;
    color: var(--text-dark);
    outline: none;
    transition: border-color var(--transition);
  }
  .form-control:focus { border-color: var(--sage); }
  textarea.form-control { resize: vertical; min-height: 80px; font-family: var(--ff-body); }

  .form-actions {
    display: flex;
    gap: 10px;
    margin-top: 24px;
    justify-content: flex-end;
  }

  /* Hidden Image Uplinks */
  .hidden-uploader {
    display: none;
  }

  /* ─── SAVE CONFIRMATION TOAST ─── */
  .toast {
    position: fixed;
    top: 84px;
    right: 36px;
    background: var(--text-dark);
    color: var(--cream);
    padding: 13px 20px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: var(--shadow-soft);
    z-index: 999;
    animation: toastIn 0.35s ease, toastOut 0.4s ease 2.6s forwards;
  }
  .toast svg { flex-shrink: 0; color: var(--sage-light); }
  @keyframes toastIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
  @keyframes toastOut { to { opacity: 0; transform: translateY(-8px); } }

  /* ─── PROFILE COMPLETENESS METER ─── */
  .completeness-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 8px;
  }
  .completeness-row .info-label { font-size: 12px; }
  .completeness-pct { font-size: 13px; font-weight: 500; color: var(--accent); }
  .completeness-track {
    width: 100%;
    height: 6px;
    background: var(--stone-light);
    border-radius: 10px;
    overflow: hidden;
  }
  .completeness-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--sage), var(--accent-warm));
    border-radius: 10px;
    transition: width 0.6s ease;
  }

  .hero-quote {
    margin-top: 8px;
    font-family: 'Cormorant Garamond', serif;
    font-style: italic;
    font-size: 16px;
    color: var(--text-muted);
  }

  /* ─── STRUCTURED FIELD GRID (Personal Info / Address view mode) ─── */
  .field-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 22px 20px;
    margin-top: 16px;
  }
  .field-block { display: flex; flex-direction: column; gap: 6px; }
  .field-label { font-size: 12px; color: var(--text-muted); }
  .field-value { font-size: 14px; font-weight: 500; color: var(--text-dark); }

  .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }

  /* ─── SKILLS & INTERESTS CHIPS ─── */
  .chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
  }
  .chip {
    font-size: 12.5px;
    padding: 6px 14px;
    border-radius: 20px;
    background: var(--sage-pale);
    color: var(--sage);
    border: 1px solid var(--sage-light);
    font-weight: 500;
  }

  /* ─── ACHIEVEMENT BADGES ─── */
  .badge-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .badge-pill {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    background: var(--cream);
    border: 1px solid var(--stone-light);
    border-radius: var(--radius-sm);
    font-size: 13px;
    color: var(--text-dark);
    font-weight: 500;
  }
  .badge-icon { font-size: 16px; }

  /* ─── CONNECT / SOCIAL LINKS ─── */
  .social-links {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .social-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 12px;
    border-radius: var(--radius-sm);
    background: var(--cream);
    border: 1px solid var(--stone-light);
    color: var(--text-dark);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all var(--transition);
  }
  .social-link:hover {
    border-color: var(--sage);
    color: var(--sage);
    transform: translateX(2px);
  }
  .empty-hint {
    font-size: 12.5px;
    color: var(--text-muted);
    line-height: 1.6;
    font-style: italic;
  }

  /* ─── COPY TO CLIPBOARD ─── */
  .copyable { display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
  .copy-btn {
    background: none; border: none; padding: 2px; cursor: pointer;
    color: var(--text-muted); display: inline-flex; align-items: center;
    border-radius: 4px; transition: all var(--transition);
  }
  .copy-btn:hover { color: var(--sage); background: var(--sage-pale); }
  .copy-btn.copied { color: var(--sage); }

  @media (max-width: 840px) {
    .profile-grid { grid-template-columns: 1fr; }
    .hero-meta-bar { flex-direction: column; align-items: flex-start; gap: 20px; }
  }

  /* Add this to your style block */
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--stone-light); border-radius: 10px; }

</style>
</head>
<body>

<!-- Ambient drifting blobs -->
<div class="bg-blob bg-blob-1"></div>
<div class="bg-blob bg-blob-2"></div>

<?php if ($justUpdated): ?>
<div class="toast" id="saveToast">
  <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8l3.5 3.5L13 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
  Profile updated successfully
</div>
<?php endif; ?>

<!-- ─── NAVIGATION (SYNCUBE Global Nav Setup) ─── -->
<nav>
  <a href="dashboard.php" class="nav-logo">
    <span></span> syncube
  </a>

  <ul class="nav-links">
    <li><a href="dashboard.php">Dashboard</a></li>
    <li><a href="workspace.php">Workspace</a></li>
    <li><a href="calendar.php">Calendar</a></li>
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

<!-- ─── MAIN PORTAL FRAME ─── -->
<main>
  <form action="profile.php" method="POST" id="profileForm">
    <!-- Image upload base64 tracking stores to simulate post saves without databases -->
    <input type="hidden" name="banner_base64" id="banner_base64" value="" />
    <input type="hidden" name="avatar_base64" id="avatar_base64" value="" />
    
    <!-- Upload inputs triggered by frame clicks during edit mode -->
    <input type="file" id="file-banner" class="hidden-uploader" accept="image/*" onchange="previewImage(this, 'banner')" />
    <input type="file" id="file-avatar" class="hidden-uploader" accept="image/*" onchange="previewImage(this, 'avatar')" />

    <!-- ─── PROFILE OVERVIEW WITH LIVE BANNER + AVATAR ─── -->
    <div class="profile-hero-frame">
      <div class="cover-banner-wrap" id="coverBanner" style="background-image: url('<?= htmlspecialchars($user['banner_url']) ?>');">
        <div class="cover-banner-overlay edit-mode" onclick="triggerFileSelector('banner')">
          <span>🌿 Change Cover Photo</span>
        </div>
      </div>

      <div class="hero-meta-bar">
        <div class="meta-user-details">
          <div class="avatar-frame" id="avatarPic" style="background-image: url('<?= htmlspecialchars($user['avatar_url']) ?>');">
            <div class="living-pulse"></div>
            <div class="avatar-overlay edit-mode" onclick="triggerFileSelector('avatar')">
              <span>Change</span>
            </div>
          </div>
          
          <div class="user-badge-fields">
            <h2>
              <span id="txt-display-name"><?= htmlspecialchars($user['name']) ?></span>
              <span class="status-vibe-badge" id="vibeBadge"><?= htmlspecialchars($user['status_vibe']) ?></span>
            </h2>
            <p>
              <?= htmlspecialchars($user['email']) ?> 
              <span class="role-badge"><?= htmlspecialchars($user['role']) ?> Level</span>
            </p>
          </div>
        </div>

        <div class="view-mode">
          <button type="button" class="btn-sync" onclick="enterEditMode()">
            <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M11 2l3 3L5 14H2v-3L11 2z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Edit Profile
          </button>
        </div>
      </div>
    </div>

    <!-- ─── DUAL GRID STRUCTURE ─── -->
    <div class="profile-grid">
      
      <!-- COLUMN 1: EDITABLE PROFILE & TIME DATA -->
      <div class="card">
        
        <!-- View State -->
        <div class="view-mode">
          <div class="card-label">Personal Information</div>
          <div class="field-grid">
            <div class="field-block">
              <span class="field-label">First Name</span>
              <span class="field-value"><?= htmlspecialchars($user['first_name']) ?></span>
            </div>
            <div class="field-block">
              <span class="field-label">Last Name</span>
              <span class="field-value"><?= htmlspecialchars($user['last_name']) ?></span>
            </div>
            <div class="field-block">
              <span class="field-label">Date of Birth</span>
              <span class="field-value"><?= htmlspecialchars($user['dob'] ?: '—') ?></span>
            </div>
            <div class="field-block">
              <span class="field-label">Email Address</span>
              <span class="field-value"><?= htmlspecialchars($user['email']) ?></span>
            </div>
            <div class="field-block">
              <span class="field-label">Phone Number</span>
              <span class="field-value"><?= htmlspecialchars($user['phone'] ?: '—') ?></span>
            </div>
            <div class="field-block">
              <span class="field-label">User Role</span>
              <span class="field-value"><?= htmlspecialchars($user['role']) ?></span>
            </div>
          </div>

          <div class="card-label" style="margin-top: 36px;">Address</div>
          <div class="field-grid">
            <div class="field-block">
              <span class="field-label">Country</span>
              <span class="field-value"><?= htmlspecialchars($user['country'] ?: '—') ?></span>
            </div>
            <div class="field-block">
              <span class="field-label">City</span>
              <span class="field-value"><?= htmlspecialchars($user['city'] ?: '—') ?></span>
            </div>
            <div class="field-block">
              <span class="field-label">Postal Code</span>
              <span class="field-value"><?= htmlspecialchars($user['postal_code'] ?: '—') ?></span>
            </div>
          </div>
        </div>

        <!-- Inline Live Form State -->
        <div class="edit-mode">
          <div class="card-label">Configure Personal Matrix</div>

          <div class="form-row">
            <div class="form-group">
              <label>First Name</label>
              <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name']) ?>" required />
            </div>
            <div class="form-group">
              <label>Last Name</label>
              <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name']) ?>" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Date of Birth</label>
              <input type="text" name="dob" class="form-control" value="<?= htmlspecialchars($user['dob']) ?>" placeholder="DD-MM-YYYY" />
            </div>
            <div class="form-group">
              <label>User Role</label>
              <input type="text" name="role" class="form-control" value="<?= htmlspecialchars($user['role']) ?>" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required />
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" placeholder="+60 12-345 6789" />
            </div>
          </div>

          <div class="form-group">
            <label>Primary State (Current Vibe)</label>
            <select name="status_vibe" class="form-control">
              <option value="🌿 Focused" <?= $user['status_vibe'] == '🌿 Focused' ? 'selected' : '' ?>>🌿 Focused</option>
              <option value="☕ Coffee Break" <?= $user['status_vibe'] == '☕ Coffee Break' ? 'selected' : '' ?>>☕ Coffee Break</option>
              <option value="🧠 Deep Work" <?= $user['status_vibe'] == '🧠 Deep Work' ? 'selected' : '' ?>>🧠 Deep Work</option>
              <option value="📖 Learning" <?= $user['status_vibe'] == '📖 Learning' ? 'selected' : '' ?>>📖 Learning</option>
              <option value="💤 Resting" <?= $user['status_vibe'] == '💤 Resting' ? 'selected' : '' ?>>💤 Resting</option>
            </select>
          </div>

          <div class="card-label" style="margin-top: 28px;">Address</div>

          <div class="form-row">
            <div class="form-group">
              <label>Country</label>
              <input type="text" name="country" class="form-control" value="<?= htmlspecialchars($user['country']) ?>" />
            </div>
            <div class="form-group">
              <label>City</label>
              <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($user['city']) ?>" />
            </div>
          </div>

          <div class="form-group">
            <label>Postal Code</label>
            <input type="text" name="postal_code" class="form-control" value="<?= htmlspecialchars($user['postal_code']) ?>" />
          </div>

          <div class="form-actions">
            <button type="button" class="btn-sync" onclick="exitEditMode()">Cancel</button>
            <button type="submit" class="btn-sync primary">Commit Changes</button>
          </div>
        </div>

      </div>

      <!-- COLUMN 2: SYSTEM AND LIVING METRICS -->
      <div style="display: flex; flex-direction: column; gap: 24px;">
        
        <!-- Interactive Focus Wave Panel ("Makes Looks Alive") -->
        <div class="card" style="padding: 24px;">
          <div class="card-label" style="margin-bottom: 12px;">Living Bio Pulse</div>
          <div class="vibe-pulse-panel">
            <div class="vibe-pulse-header">
              <span>Focus Resonance</span>
              <span style="color: var(--sage); font-weight: bold;">Active</span>
            </div>
            <!-- Decorative SVG Pulse wave -->
            <svg class="pulse-wave-svg" viewBox="0 0 200 30">
              <path d="M 0 15 Q 25 5, 50 15 T 100 15 T 150 15 T 200 15" stroke="#8a9e8c" />
              <!-- Pulsing secondary overlapping path -->
              <path d="M 0 15 Q 25 25, 50 15 T 100 15 T 150 15 T 200 15" stroke="#c8d8c9" opacity="0.5" />
            </svg>
          </div>
          <div class="completeness-row">
            <span class="info-label">Profile Completeness</span>
            <span class="completeness-pct"><?= $completenessPercent ?>%</span>
          </div>
          <div class="completeness-track">
            <div class="completeness-fill" style="width: <?= $completenessPercent ?>%;"></div>
          </div>

          <div class="info-list" style="margin-top: 20px;">
            <div class="info-item">
              <span class="info-label">Daily Focus Rating</span>
              <span class="info-value">Excellent (4.8h)</span>
            </div>
            <div class="info-item">
              <span class="info-label">Member For</span>
              <span class="info-value" style="color: var(--sage);"><?= number_format($daysAsMember) ?> day<?= $daysAsMember == 1 ? '' : 's' ?></span>
            </div>
          </div>
        </div>

        <!-- Automatic System Information Card -->
        <div class="card">
          <div class="card-label">Automated Credentials</div>
          <div class="info-list">
            <div class="info-item">
              <span class="info-label">Internal Node ID</span>
              <span class="copyable" onclick="copyToClipboard('<?= htmlspecialchars($user['user_id'], ENT_QUOTES) ?>', this)">
                <span class="info-value monospace"><?= htmlspecialchars($user['user_id']) ?></span>
                <button type="button" class="copy-btn" title="Copy">
                  <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><rect x="5.5" y="5.5" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M3.5 10.5h-1a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v1" stroke="currentColor" stroke-width="1.3"/></svg>
                </button>
              </span>
            </div>
            <div class="info-item">
              <span class="info-label">Signed Email</span>
              <span class="copyable" onclick="copyToClipboard('<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>', this)">
                <span class="info-value"><?= htmlspecialchars($user['email']) ?></span>
                <button type="button" class="copy-btn" title="Copy">
                  <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><rect x="5.5" y="5.5" width="8" height="8" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M3.5 10.5h-1a1 1 0 0 1-1-1v-7a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v1" stroke="currentColor" stroke-width="1.3"/></svg>
                </button>
              </span>
            </div>
            <div class="info-item">
              <span class="info-label">Account Clearance</span>
              <span class="info-value"><?= htmlspecialchars($user['role']) ?> account</span>
            </div>
            <div class="info-item">
              <span class="info-label">Creation Stamp</span>
              <span class="info-value"><?= htmlspecialchars($user['joined_date']) ?></span>
            </div>
          </div>
        </div>

        <!-- Achievements / Badges Card -->
        <div class="card">
          <div class="card-label">Achievements</div>
          <div class="badge-grid">
            <?php foreach ($badges as $badge): ?>
              <div class="badge-pill">
                <span class="badge-icon"><?= $badge['icon'] ?></span>
                <span><?= htmlspecialchars($badge['label']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>

    </div>
  </form>
</main>

<script>
// ─── COPY TO CLIPBOARD FEEDBACK ───
function copyToClipboard(text, wrapperEl) {
  navigator.clipboard.writeText(text).then(() => {
    const btn = wrapperEl.querySelector('.copy-btn');
    btn.classList.add('copied');
    const original = btn.innerHTML;
    btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M3 8l3.5 3.5L13 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    setTimeout(() => {
      btn.innerHTML = original;
      btn.classList.remove('copied');
    }, 1400);
  }).catch(() => {});
}

// ─── CLEAN UP ?updated=1 FROM URL AFTER SHOWING TOAST ───
if (window.location.search.includes('updated=1')) {
  const cleanUrl = window.location.pathname;
  window.history.replaceState({}, document.title, cleanUrl);
  setTimeout(() => {
    const toastEl = document.getElementById('saveToast');
    if (toastEl) toastEl.remove();
  }, 3000);
}

// ─── DROPDOWN COMPONENT (Matching Syncube Setup) ───
function toggleDropdown() {
  document.getElementById('profileWrap').classList.toggle('open');
}
document.addEventListener('click', e => {
  if (!e.target.closest('#profileWrap')) {
    document.getElementById('profileWrap').classList.remove('open');
  }
});

// ─── TRANSITIONAL EDIT MODES ───
function enterEditMode() {
  document.body.classList.add('is-editing');
}
function exitEditMode() {
  document.body.classList.remove('is-editing');
}

// ─── LIVE FILE CHOOSE PREVIEWS ───
function triggerFileSelector(type) {
  if (type === 'banner') {
    document.getElementById('file-banner').click();
  } else if (type === 'avatar') {
    document.getElementById('file-avatar').click();
  }
}

function previewImage(input, type) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      if (type === 'banner') {
        // Live update cover wrapper background
        document.getElementById('coverBanner').style.backgroundImage = "url('" + e.target.result + "')";
        // Update form base64 value for server submission
        document.getElementById('banner_base64').value = e.target.result;
      } else if (type === 'avatar') {
        // Live update avatar background frame
        document.getElementById('avatarPic').style.backgroundImage = "url('" + e.target.result + "')";
        // Update form base64 value for server submission
        document.getElementById('avatar_base64').value = e.target.result;
      }
    }
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
</body>
</html>