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

// Mock upcoming priority tasks/events
$upcoming = [
    [
        'title' => 'Design Review Meeting',
        'time' => '10:00 AM',
        'date' => 'Today',
        'priority' => 'high',
        'type' => 'event',
        'tag' => 'Work'
    ],
    [
        'title' => 'Submit Project Proposal',
        'time' => '2:00 PM',
        'date' => 'Today',
        'priority' => 'high',
        'type' => 'task',
        'tag' => 'Urgent'
    ],
    [
        'title' => 'Weekly Journal Entry',
        'time' => '7:00 PM',
        'date' => 'Today',
        'priority' => 'medium',
        'type' => 'journal',
        'tag' => 'Personal'
    ],
    [
        'title' => 'Team Standup',
        'time' => '9:30 AM',
        'date' => 'Tomorrow',
        'priority' => 'medium',
        'type' => 'event',
        'tag' => 'Work'
    ],
    [
        'title' => 'Read Research Paper',
        'time' => '3:00 PM',
        'date' => 'Tomorrow',
        'priority' => 'low',
        'type' => 'task',
        'tag' => 'Learning'
    ]
];

// Mock task stats
$stats = [
    'total' => 12,
    'done' => 7,
    'pending' => 5
];
$progress = round(($stats['done'] / $stats['total']) * 100);

// Motivational quotes
$quotes = [
    "Small steps every day lead to big changes over time.",
    "You don't have to be perfect, you just have to be present.",
    "Rest is not quitting — it's part of the journey.",
    "One task at a time. Breathe. You've got this.",
    "Progress, not perfection.",
];
$quote = $quotes[date('N') % count($quotes)];

// Cute daily mood illustration — deterministic per day, purely decorative
$moods = [
    ['face' => '(｡•̀ᴗ•́｡)', 'label' => 'Cozy',      'msg' => 'Feeling soft and cozy today ☁️',        'color' => '#c8d8c9'],
    ['face' => '(＾▽＾)',   'label' => 'Bright',     'msg' => 'A little sunshine in your step ☀️',      'color' => '#e8dcc4'],
    ['face' => '(´｡• ᵕ •｡`)', 'label' => 'Gentle',   'msg' => 'Take it slow and steady today 🌿',       'color' => '#eef3ee'],
    ['face' => '(⌐■_■)',    'label' => 'Focused',    'msg' => 'Locked in and ready to go 🎯',           'color' => '#dcd6cc'],
    ['face' => '(｡♥‿♥｡)',  'label' => 'Warm',       'msg' => 'Spreading a little warmth today 💛',     'color' => '#e5cfc0'],
    ['face' => '(｡-‿-｡)',  'label' => 'Calm',       'msg' => 'Peaceful vibes, one step at a time 🍃',  'color' => '#d6e2d7'],
    ['face' => '(≧◡≦)',    'label' => 'Playful',    'msg' => 'A little playful energy today ✨',       'color' => '#e9d9e3'],
];
$mood = $moods[date('N') % count($moods)];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>SYNCUBE — Dashboard</title>
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
    letter-spacing: 0.01em;
  }

  .nav-links a:hover,
  .nav-links a.active {
    color: var(--text-dark);
    background: var(--stone-light);
  }

  .nav-links a.active {
    font-weight: 500;
  }

  /* Profile dropdown */
  .profile-wrap {
    position: relative;
  }

  .profile-btn {
    display: flex;
    align-items: center;
    gap: 9px;
    cursor: pointer;
    padding: 6px 12px 6px 6px;
    border-radius: 40px;
    border: 1px solid var(--border);
    background: var(--warm-white);
    transition: all var(--transition);
    font-family: var(--ff-body);
    font-size: 13px;
    color: var(--text-mid);
    font-weight: 400;
    user-select: none;
  }

  .profile-btn:hover { background: var(--stone-light); border-color: rgba(44,42,39,0.14); }

  .profile-avatar {
    width: 30px; height: 30px;
    border-radius: 50%;
    background: var(--sage-light);
    display: flex; align-items: center; justify-content: center;
    font-size: 11px;
    font-weight: 500;
    color: var(--accent);
    letter-spacing: 0.05em;
    flex-shrink: 0;
  }

  .chevron {
    width: 14px; height: 14px;
    opacity: 0.45;
    transition: transform var(--transition);
  }

  .profile-wrap.open .chevron { transform: rotate(180deg); }

  .dropdown {
    position: absolute;
    top: calc(100% + 8px);
    right: 0;
    background: var(--warm-white);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-soft);
    min-width: 180px;
    padding: 8px;
    opacity: 0;
    transform: translateY(-6px);
    pointer-events: none;
    transition: all var(--transition);
    z-index: 200;
  }

  .profile-wrap.open .dropdown {
    opacity: 1;
    transform: translateY(0);
    pointer-events: all;
  }

  .dropdown-header {
    padding: 8px 10px 10px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 6px;
  }

  .dropdown-header strong { display: block; font-size: 13px; font-weight: 500; color: var(--text-dark); }
  .dropdown-header span { font-size: 11px; color: var(--text-muted); }

  .dropdown a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 13px;
    color: var(--text-mid);
    text-decoration: none;
    transition: all var(--transition);
  }

  .dropdown a:hover { background: var(--stone-light); color: var(--text-dark); }
  .dropdown a.logout { color: #b85c5c; }
  .dropdown a.logout:hover { background: #fdf0f0; color: #a34444; }
  .dropdown .divider { height: 1px; background: var(--border); margin: 6px 0; }

  /* ─── MAIN LAYOUT ─── */
  main {
    margin-top: var(--nav-height);
    padding: 40px 36px 60px;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
  }

  /* ─── GREETING ROW ─── */
  .greeting-row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 36px;
  }

  .greeting-text h1 {
    font-family: var(--ff-display);
    font-size: 38px;
    font-weight: 300;
    color: var(--text-dark);
    line-height: 1.15;
    letter-spacing: -0.01em;
  }

  .greeting-text h1 em {
    font-style: italic;
    color: var(--accent);
  }

  .greeting-text p {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 6px;
    font-style: italic;
    font-family: var(--ff-display);
    font-size: 16px;
    font-weight: 300;
  }

  /* Date display */
  .date-display {
    text-align: right;
    flex-shrink: 0;
  }

  #live-time {
    font-family: var(--ff-display);
    font-size: 46px;
    font-weight: 300;
    color: var(--text-dark);
    line-height: 1;
    letter-spacing: -0.02em;
  }

  #live-date {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 4px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-weight: 400;
  }

  /* ─── GRID ─── */
  .grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    grid-template-rows: auto;
    gap: 18px;
  }

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
    font-size: 10px;
    font-weight: 500;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .card-label::before {
    content: '';
    display: inline-block;
    width: 4px; height: 4px;
    border-radius: 50%;
    background: var(--sage);
    flex-shrink: 0;
  }

  /* ─── MOOD OF THE DAY ─── */
  .mood-card {
    grid-column: 1 / 2;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .mood-blob {
    width: 88px; height: 88px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    font-family: var(--ff-body);
    color: var(--text-dark);
    margin: 6px 0 14px;
    transition: transform var(--transition);
    animation: moodFloat 3.2s ease-in-out infinite;
  }

  .mood-blob:hover { transform: scale(1.06); }

  @keyframes moodFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-6px); }
  }

  .mood-label {
    font-family: var(--ff-display);
    font-size: 20px;
    font-weight: 400;
    color: var(--text-dark);
    margin-bottom: 6px;
  }

  .mood-msg {
    font-size: 12.5px;
    color: var(--text-mid);
    line-height: 1.5;
  }

  /* ─── TRIVIA ─── */
  .trivia-card {
    grid-column: 2 / 3;
    display: flex;
    flex-direction: column;
  }

  .trivia-body {
    flex: 1;
    display: flex;
    align-items: center;
    font-family: var(--ff-display);
    font-size: 16px;
    font-weight: 300;
    font-style: italic;
    color: var(--text-dark);
    line-height: 1.55;
    min-height: 92px;
  }

  .trivia-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 14px;
  }

  .trivia-tag {
    font-size: 10px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--accent-warm);
    font-weight: 500;
  }

  .trivia-next {
    padding: 6px 16px;
    border-radius: 40px;
    border: 1px solid var(--border);
    background: var(--cream);
    color: var(--text-mid);
    font-family: var(--ff-body);
    font-size: 11.5px;
    cursor: pointer;
    transition: all var(--transition);
  }

  .trivia-next:hover { background: var(--stone-light); color: var(--text-dark); }

  /* ─── BUBBLE POP GAME ─── */
  .game-card {
    grid-column: 3 / 4;
    display: flex;
    flex-direction: column;
  }

  .game-header {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 10px;
  }

  .game-score {
    font-family: var(--ff-display);
    font-size: 22px;
    font-weight: 300;
    color: var(--text-dark);
  }

  .game-score small {
    font-family: var(--ff-body);
    font-size: 11px;
    color: var(--text-muted);
    font-weight: 400;
    margin-left: 3px;
  }

  .game-reset {
    font-size: 11px;
    color: var(--text-muted);
    background: none;
    border: none;
    cursor: pointer;
    text-decoration: underline;
    text-underline-offset: 2px;
  }

  .game-reset:hover { color: var(--text-dark); }

  .bubble-field {
    position: relative;
    height: 132px;
    background: var(--cream);
    border-radius: var(--radius-sm);
    overflow: hidden;
    cursor: pointer;
  }

  .bubble {
    position: absolute;
    border-radius: 50%;
    background: radial-gradient(circle at 32% 28%, #fff, var(--sage-light) 55%, var(--sage) 100%);
    box-shadow: 0 2px 6px rgba(44,42,39,0.12);
    animation: bubbleRise linear forwards;
    cursor: pointer;
  }

  .bubble:nth-child(3n) {
    background: radial-gradient(circle at 32% 28%, #fff, var(--accent-blush) 55%, #c99d8d 100%);
  }

  .bubble:nth-child(4n) {
    background: radial-gradient(circle at 32% 28%, #fff, var(--accent-warm) 55%, #b8925f 100%);
  }

  @keyframes bubbleRise {
    from { transform: translateY(0); opacity: 0.95; }
    to { transform: translateY(-140px); opacity: 0; }
  }

  .bubble-pop {
    animation: bubblePop 0.25s ease forwards !important;
  }

  @keyframes bubblePop {
    to { transform: scale(1.6); opacity: 0; }
  }

  /* ─── STOPWATCH ─── */
  .stopwatch-card { grid-column: 1 / 2; }

  .timer-display {
    font-family: var(--ff-display);
    font-size: 44px;
    font-weight: 300;
    color: var(--text-dark);
    letter-spacing: 0.02em;
    text-align: center;
    margin: 16px 0;
    line-height: 1;
  }

  .timer-sub {
    font-family: var(--ff-display);
    font-size: 16px;
    color: var(--text-muted);
    font-weight: 300;
    letter-spacing: 0.03em;
    text-align: center;
    margin-bottom: 16px;
  }

  .timer-controls {
    display: flex;
    justify-content: center;
    gap: 8px;
  }

  .btn-timer {
    padding: 8px 20px;
    border-radius: 40px;
    border: 1px solid var(--border);
    background: var(--cream);
    color: var(--text-mid);
    font-family: var(--ff-body);
    font-size: 12px;
    font-weight: 400;
    cursor: pointer;
    transition: all var(--transition);
    letter-spacing: 0.03em;
  }

  .btn-timer:hover { background: var(--stone-light); color: var(--text-dark); border-color: rgba(44,42,39,0.15); }

  .btn-timer.primary {
    background: var(--accent);
    color: #fff;
    border-color: var(--accent);
  }

  .btn-timer.primary:hover { background: #6d7f70; }

  .btn-timer.danger {
    background: #f0e8e5;
    color: #b85c5c;
    border-color: transparent;
  }

  .btn-timer.danger:hover { background: #f5ddd9; }

  /* ─── TIMER (countdown) ─── */
  .countdown-card { grid-column: 2 / 3; }

  .timer-presets {
    display: flex;
    gap: 6px;
    margin-bottom: 12px;
    flex-wrap: wrap;
  }

  .preset-btn {
    padding: 5px 12px;
    border-radius: 40px;
    border: 1px solid var(--border);
    background: var(--cream);
    color: var(--text-muted);
    font-size: 11px;
    cursor: pointer;
    transition: all var(--transition);
    font-family: var(--ff-body);
  }

  .preset-btn:hover, .preset-btn.active {
    background: var(--sage-pale);
    color: var(--accent);
    border-color: var(--sage-light);
  }

  .custom-time {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 10px;
  }

  .custom-time input {
    width: 52px;
    padding: 6px 8px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--cream);
    font-family: var(--ff-body);
    font-size: 13px;
    color: var(--text-dark);
    text-align: center;
    outline: none;
    transition: border-color var(--transition);
  }

  .custom-time input:focus { border-color: var(--sage); }
  .custom-time span { font-size: 12px; color: var(--text-muted); }

  .countdown-bar {
    height: 3px;
    background: var(--stone-light);
    border-radius: 10px;
    margin: 10px 0;
    overflow: hidden;
  }

  .countdown-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--sage) 0%, var(--sage-light) 100%);
    border-radius: 10px;
    transition: width 1s linear;
    width: 100%;
  }

  /* ─── BREAK REMINDER ─── */
  .break-card {
    grid-column: 3 / 4;
    background: var(--sage-pale);
    border-color: rgba(138,158,140,0.2);
  }

  .break-icon {
    width: 38px; height: 38px;
    border-radius: 50%;
    background: var(--sage-light);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    margin-bottom: 12px;
  }

  .break-msg {
    font-size: 13px;
    color: var(--text-mid);
    line-height: 1.55;
    margin-bottom: 14px;
  }

  .break-next {
    font-size: 11px;
    color: var(--text-muted);
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 5px;
  }

  .break-timer-display {
    font-family: var(--ff-display);
    font-size: 28px;
    font-weight: 300;
    color: var(--accent);
    margin-bottom: 12px;
    letter-spacing: 0.03em;
  }

  /* ─── QUOTE ─── */
  .quote-card {
    grid-column: 1 / 4;
    background: transparent;
    border: none;
    box-shadow: none;
    padding: 0 0 6px;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
  }

  .quote-card::before, .quote-card::after {
    content: '—';
    color: var(--stone);
    font-family: var(--ff-display);
    font-size: 16px;
    opacity: 0.4;
  }

  .quote-card blockquote {
    font-family: var(--ff-display);
    font-size: 17px;
    font-weight: 300;
    font-style: italic;
    color: var(--text-mid);
    letter-spacing: 0.01em;
    max-width: 600px;
    line-height: 1.6;
  }

  /* ─── NOTIFICATION BADGE ─── */
  .badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px; height: 18px;
    border-radius: 50%;
    background: var(--accent-warm);
    color: #fff;
    font-size: 10px;
    font-weight: 500;
    margin-left: 4px;
    vertical-align: middle;
  }

  /* Break reminder notification */
  .break-notification {
    position: fixed;
    top: 80px; right: 24px;
    background: var(--warm-white);
    border: 1px solid var(--sage-light);
    border-radius: var(--radius-md);
    padding: 16px 20px;
    box-shadow: 0 8px 32px rgba(44,42,39,0.12);
    max-width: 280px;
    z-index: 300;
    display: none;
    animation: slideIn 0.3s ease;
  }

  .break-notification.show { display: block; }

  @keyframes slideIn {
    from { opacity: 0; transform: translateX(20px); }
    to { opacity: 1; transform: translateX(0); }
  }

  .bn-title {
    font-size: 13px;
    font-weight: 500;
    color: var(--text-dark);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .bn-body { font-size: 12px; color: var(--text-mid); line-height: 1.5; margin-bottom: 12px; }

  .bn-actions { display: flex; gap: 8px; }

  .bn-btn {
    padding: 6px 14px;
    border-radius: 40px;
    border: 1px solid var(--border);
    background: transparent;
    font-size: 11px;
    font-family: var(--ff-body);
    color: var(--text-mid);
    cursor: pointer;
    transition: all var(--transition);
  }

  .bn-btn.primary { background: var(--sage); color: #fff; border-color: var(--sage); }
  .bn-btn.primary:hover { background: #7a8f7c; }
  .bn-btn:hover { background: var(--stone-light); }

  /* Scrollbar */
  ::-webkit-scrollbar { width: 5px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: var(--stone-light); border-radius: 10px; }

  /* Responsive */
  @media (max-width: 900px) {
    nav { padding: 0 18px; }
    main { padding: 30px 18px 50px; }
    .grid { grid-template-columns: 1fr 1fr; gap: 14px; }
    .mood-card { grid-column: 1 / 2; }
    .trivia-card { grid-column: 2 / 3; }
    .game-card { grid-column: 1 / 3; }
    .stopwatch-card { grid-column: 2 / 3; }
    .countdown-card { grid-column: 1 / 2; }
    .break-card { grid-column: 2 / 3; }
    .quote-card { grid-column: 1 / 3; }
    .greeting-text h1 { font-size: 28px; }
    #live-time { font-size: 34px; }
  }

  @media (max-width: 640px) {
    .nav-links { display: none; }
    .grid { grid-template-columns: 1fr; }
    .mood-card, .trivia-card, .game-card, .stopwatch-card,
    .countdown-card, .break-card, .quote-card { grid-column: 1; }
  }
</style>
</head>
<body>

<!-- ─── NAVIGATION ─── -->
<nav>
  <a href="dashboard.php" class="nav-logo">
    <span></span> syncube
  </a>

  <ul class="nav-links">
    <li><a href="dashboard.php" class="active">Dashboard</a></li>
    <li><a href="workspace.php">Workspace</a></li>
    <li><a href="calendar.php">Calendar</a></li>
    <li><a href="journal.php">Journal</a></li>
    <li>
      <div class="profile-wrap" id="profileWrap">
        <div class="profile-btn" onclick="toggleDropdown()">
          <div class="profile-avatar"><?= htmlspecialchars($user['initials']) ?></div>
          <?= htmlspecialchars($user['name']) ?>
          <svg class="chevron" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
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

<!-- ─── BREAK REMINDER NOTIFICATION ─── -->
<div class="break-notification" id="breakNotif">
  <div class="bn-title">🌿 Time for a break</div>
  <div class="bn-body">You've been focused for a while. Step away, breathe, and stretch — even 5 minutes helps.</div>
  <div class="bn-actions">
    <button class="bn-btn primary" onclick="snoozeBreak()">Take a break</button>
    <button class="bn-btn" onclick="dismissBreak()">Remind later</button>
  </div>
</div>

<!-- ─── MAIN ─── -->
<main>

  <!-- Greeting -->
  <div class="greeting-row">
    <div class="greeting-text">
      <h1>Good <em id="timeOfDay">morning</em>, <?= htmlspecialchars(explode(' ', $user['name'])[0]) ?>.</h1>
      <p>"<?= htmlspecialchars($quote) ?>"</p>
    </div>
    <div class="date-display">
      <div id="live-time">—</div>
      <div id="live-date">—</div>
    </div>
  </div>

  <!-- Dashboard Grid -->
  <div class="grid">

    <!-- Mood of the Day -->
    <div class="card mood-card">
      <div class="card-label">Mood of the Day</div>
      <div class="mood-blob" style="background: <?= htmlspecialchars($mood['color']) ?>">
        <?= htmlspecialchars($mood['face']) ?>
      </div>
      <div class="mood-label"><?= htmlspecialchars($mood['label']) ?></div>
      <div class="mood-msg"><?= htmlspecialchars($mood['msg']) ?></div>
    </div>

    <!-- Fun Trivia -->
    <div class="card trivia-card">
      <div class="card-label">Did You Know?</div>
      <div class="trivia-body" id="trivia-text">Loading a fun fact...</div>
      <div class="trivia-footer">
        <span class="trivia-tag">Trivia</span>
        <button class="trivia-next" onclick="nextTrivia()">Next fact →</button>
      </div>
    </div>

    <!-- Bubble Pop Mini Game -->
    <div class="card game-card">
      <div class="game-header">
        <div class="card-label" style="margin-bottom:0">Bubble Pop</div>
        <button class="game-reset" onclick="resetBubbles()">Reset</button>
      </div>
      <div class="game-score" style="margin-bottom:10px"><span id="bubble-score">0</span><small>popped</small></div>
      <div class="bubble-field" id="bubble-field"></div>
    </div>

    <!-- Stopwatch -->
    <div class="card stopwatch-card">
      <div class="card-label">Stopwatch</div>
      <div class="timer-display" id="sw-display">00:00:00</div>
      <div class="timer-controls">
        <button class="btn-timer primary" id="sw-start" onclick="swStart()">Start</button>
        <button class="btn-timer" id="sw-pause" onclick="swPause()" style="display:none">Pause</button>
        <button class="btn-timer" onclick="swReset()">Reset</button>
      </div>
    </div>

    <!-- Countdown Timer -->
    <div class="card countdown-card">
      <div class="card-label">Timer</div>
      <div class="timer-presets">
        <button class="preset-btn" onclick="setPreset(5)">5 min</button>
        <button class="preset-btn" onclick="setPreset(15)">15 min</button>
        <button class="preset-btn" onclick="setPreset(25)">25 min</button>
        <button class="preset-btn" onclick="setPreset(45)">45 min</button>
        <button class="preset-btn" onclick="setPreset(60)">1 hr</button>
      </div>
      <div class="custom-time">
        <input type="number" id="ct-min" min="1" max="999" placeholder="min" />
        <span>min</span>
        <input type="number" id="ct-sec" min="0" max="59" placeholder="sec" />
        <span>sec</span>
      </div>
      <div class="countdown-bar"><div class="countdown-bar-fill" id="ct-bar"></div></div>
      <div class="timer-display" id="ct-display" style="font-size:38px">00:00</div>
      <div class="timer-controls">
        <button class="btn-timer primary" id="ct-start" onclick="ctStart()">Start</button>
        <button class="btn-timer" id="ct-pause" onclick="ctPause()" style="display:none">Pause</button>
        <button class="btn-timer" onclick="ctReset()">Reset</button>
      </div>
    </div>

    <!-- Break Reminder -->
    <div class="card break-card">
      <div class="card-label">Break Reminder</div>
      <div class="break-icon">🌿</div>
      <div class="break-timer-display" id="break-countdown">50:00</div>
      <div class="break-msg">You'll be reminded to take a mindful break every <strong>50 minutes</strong> of focus time.</div>
      <div class="break-next" id="break-next-label">
        <svg width="12" height="12" viewBox="0 0 14 14" fill="none"><circle cx="7" cy="7" r="5.5" stroke="#a09d9a" stroke-width="1.3"/><path d="M7 4.5V7l1.5 1.5" stroke="#a09d9a" stroke-width="1.3" stroke-linecap="round"/></svg>
        Next reminder in <span id="break-next-time">50 min</span>
      </div>
      <div class="timer-controls">
        <button class="btn-timer primary" onclick="resetBreakTimer()">Reset</button>
        <button class="btn-timer" onclick="snoozeBreak()">Snooze</button>
      </div>
    </div>

    <!-- Quote -->
    <div class="card quote-card" style="margin-top:4px;">
      <blockquote id="daily-quote"><?= htmlspecialchars($quote) ?></blockquote>
    </div>

  </div>
</main>

<script>
// ── CLOCK ──
function updateClock() {
  const now = new Date();
  const h = String(now.getHours()).padStart(2,'0');
  const m = String(now.getMinutes()).padStart(2,'0');
  const s = String(now.getSeconds()).padStart(2,'0');
  document.getElementById('live-time').textContent = h + ':' + m + ':' + s;
  const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  document.getElementById('live-date').textContent =
    days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
  const hour = now.getHours();
  const tod = hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening';
  document.getElementById('timeOfDay').textContent = tod;
}
updateClock();
setInterval(updateClock, 1000);

// ── DROPDOWN ──
function toggleDropdown() {
  document.getElementById('profileWrap').classList.toggle('open');
}
document.addEventListener('click', e => {
  if (!e.target.closest('#profileWrap')) {
    document.getElementById('profileWrap').classList.remove('open');
  }
});

// ── STOPWATCH ──
let swMs = 0, swInterval = null, swRunning = false, swStart0 = 0;
function swFmt(ms) {
  const t = Math.floor(ms / 1000);
  const h = Math.floor(t / 3600), mi = Math.floor((t % 3600) / 60), s = t % 60;
  return [h,mi,s].map(n => String(n).padStart(2,'0')).join(':');
}
function swStart() {
  if (swRunning) return;
  swRunning = true;
  swStart0 = Date.now() - swMs;
  swInterval = setInterval(() => {
    swMs = Date.now() - swStart0;
    document.getElementById('sw-display').textContent = swFmt(swMs);
  }, 100);
  document.getElementById('sw-start').style.display = 'none';
  document.getElementById('sw-pause').style.display = '';
}
function swPause() {
  clearInterval(swInterval);
  swRunning = false;
  document.getElementById('sw-start').style.display = '';
  document.getElementById('sw-pause').style.display = 'none';
}
function swReset() {
  swPause();
  swMs = 0;
  document.getElementById('sw-display').textContent = '00:00:00';
}

// ── COUNTDOWN TIMER ──
let ctTotal = 0, ctRemain = 0, ctInterval2 = null, ctRunning = false;
function setPreset(min) {
  ctReset();
  document.getElementById('ct-min').value = min;
  document.getElementById('ct-sec').value = 0;
  document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
  event.target.classList.add('active');
}
function ctFmt(s) {
  const m = Math.floor(s / 60);
  const sec = s % 60;
  return String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0');
}
function ctStart() {
  if (ctRunning) return;
  const min = parseInt(document.getElementById('ct-min').value) || 0;
  const sec = parseInt(document.getElementById('ct-sec').value) || 0;
  if (ctRemain === 0) {
    ctTotal = min * 60 + sec;
    ctRemain = ctTotal;
  }
  if (ctRemain <= 0) return;
  ctRunning = true;
  ctInterval2 = setInterval(() => {
    ctRemain--;
    document.getElementById('ct-display').textContent = ctFmt(ctRemain);
    const pct = ctTotal > 0 ? (ctRemain / ctTotal) * 100 : 0;
    document.getElementById('ct-bar').style.width = pct + '%';
    if (ctRemain <= 0) {
      clearInterval(ctInterval2);
      ctRunning = false;
      document.getElementById('ct-display').textContent = '00:00';
      document.getElementById('ct-start').style.display = '';
      document.getElementById('ct-pause').style.display = 'none';
      showTimerDone();
    }
  }, 1000);
  document.getElementById('ct-start').style.display = 'none';
  document.getElementById('ct-pause').style.display = '';
}
function ctPause() {
  clearInterval(ctInterval2);
  ctRunning = false;
  document.getElementById('ct-start').style.display = '';
  document.getElementById('ct-pause').style.display = 'none';
}
function ctReset() {
  ctPause();
  ctRemain = 0; ctTotal = 0;
  document.getElementById('ct-display').textContent = '00:00';
  document.getElementById('ct-bar').style.width = '100%';
  document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
}
function showTimerDone() {
  const notif = document.getElementById('breakNotif');
  notif.querySelector('.bn-title').innerHTML = '⏰ Timer complete!';
  notif.querySelector('.bn-body').textContent = 'Your countdown has ended. Take a moment before starting another session.';
  notif.classList.add('show');
  setTimeout(() => notif.classList.remove('show'), 8000);
}

// ── BREAK REMINDER ──
const BREAK_INTERVAL = 50 * 60; // 50 minutes
let breakRemain = BREAK_INTERVAL;
let breakInterval = null;

function fmtBreak(s) {
  const m = Math.floor(s / 60), sec = s % 60;
  return String(m).padStart(2,'0') + ':' + String(sec).padStart(2,'0');
}

function startBreakTimer() {
  breakInterval = setInterval(() => {
    breakRemain--;
    document.getElementById('break-countdown').textContent = fmtBreak(breakRemain);
    const minLeft = Math.ceil(breakRemain / 60);
    document.getElementById('break-next-time').textContent = minLeft + ' min';
    if (breakRemain <= 0) {
      showBreakReminder();
      breakRemain = BREAK_INTERVAL;
    }
  }, 1000);
}

function showBreakReminder() {
  const notif = document.getElementById('breakNotif');
  notif.querySelector('.bn-title').innerHTML = '🌿 Time for a break';
  notif.querySelector('.bn-body').textContent = "You've been focused for 50 minutes. Step away, breathe, and stretch — even 5 minutes helps.";
  notif.classList.add('show');
}

function dismissBreak() {
  document.getElementById('breakNotif').classList.remove('show');
}

function snoozeBreak() {
  dismissBreak();
  breakRemain = 10 * 60; // snooze 10 min
}

function resetBreakTimer() {
  breakRemain = BREAK_INTERVAL;
  document.getElementById('break-countdown').textContent = fmtBreak(breakRemain);
  document.getElementById('break-next-time').textContent = '50 min';
}

startBreakTimer();

// ── FUN TRIVIA ──
const triviaFacts = [
  "Honey never spoils — archaeologists have found 3,000-year-old honey that's still edible.",
  "Octopuses have three hearts and blue blood.",
  "A group of flamingos is called a 'flamboyance'.",
  "Bananas are berries, but strawberries aren't.",
  "The Eiffel Tower grows about 6 inches taller in summer heat.",
  "Sea otters hold hands while sleeping so they don't drift apart.",
  "A day on Venus is longer than a year on Venus.",
  "Wombat poop is cube-shaped.",
  "The shortest war in history lasted about 38 minutes.",
  "Sharks existed before trees did.",
  "Cows have best friends and get stressed when separated.",
  "There are more possible chess games than atoms in the observable universe.",
  "A bolt of lightning is hotter than the surface of the sun.",
  "Butterflies taste with their feet.",
  "The dot over a lowercase 'i' or 'j' is called a tittle.",
];
let triviaIdx = -1;
function nextTrivia() {
  let next;
  do { next = Math.floor(Math.random() * triviaFacts.length); } while (next === triviaIdx && triviaFacts.length > 1);
  triviaIdx = next;
  document.getElementById('trivia-text').textContent = triviaFacts[triviaIdx];
}
nextTrivia();

// ── BUBBLE POP GAME (client-side only, resets on reload) ──
let bubbleScore = 0;
let bubbleSpawner = null;
const bubbleField = document.getElementById('bubble-field');

function spawnBubble() {
  if (!bubbleField) return;
  const size = 18 + Math.random() * 22;
  const bubble = document.createElement('div');
  bubble.className = 'bubble';
  bubble.style.width = size + 'px';
  bubble.style.height = size + 'px';
  bubble.style.left = Math.random() * (bubbleField.clientWidth - size) + 'px';
  bubble.style.bottom = '-30px';
  const duration = 3 + Math.random() * 2.5;
  bubble.style.animationDuration = duration + 's';
  bubble.addEventListener('click', () => popBubble(bubble));
  bubble.addEventListener('animationend', () => bubble.remove());
  bubbleField.appendChild(bubble);
}

function popBubble(bubble) {
  if (bubble.classList.contains('bubble-pop')) return;
  bubble.classList.add('bubble-pop');
  bubbleScore++;
  document.getElementById('bubble-score').textContent = bubbleScore;
  setTimeout(() => bubble.remove(), 250);
}

function resetBubbles() {
  bubbleScore = 0;
  document.getElementById('bubble-score').textContent = '0';
  if (bubbleField) bubbleField.innerHTML = '';
}

if (bubbleField) {
  spawnBubble();
  bubbleSpawner = setInterval(spawnBubble, 900);
}
</script>
</body>
</html>