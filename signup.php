<?php
session_start();

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';
    $agree    = $_POST['agree']         ?? '';

    // --- Validation ---
    if (empty($name))                              $errors['name']     = 'Full name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
                                                   $errors['email']    = 'A valid email is required.';
    if (strlen($password) < 8)                     $errors['password'] = 'Password must be at least 8 characters.';
    elseif (!preg_match('/[A-Z]/', $password))     $errors['password'] = 'Password must contain an uppercase letter.';
    elseif (!preg_match('/[0-9]/', $password))     $errors['password'] = 'Password must contain a number.';
    if ($password !== $confirm)                    $errors['confirm']  = 'Passwords do not match.';
    if (empty($agree))                             $errors['agree']    = 'You must accept the terms.';

    if (empty($errors)) {
        // --- Replace with real DB insert ---
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        // INSERT INTO users (name, email, password) VALUES (...)
        $success = true;
    }
}

// Helpers
function old($key) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') return htmlspecialchars($_POST[$key] ?? '');
    return '';
}
function err($key, $errors) {
    return isset($errors[$key])
        ? '<p class="field-err"><svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>' . htmlspecialchars($errors[$key]) . '</p>'
        : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Sign Up — SYNCUBE</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet" />
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:       #0b0f1a;
    --surface:  #111827;
    --border:   rgba(255,255,255,0.08);
    --accent:   #4f8ef7;
    --accent2:  #a78bfa;
    --success:  #34d399;
    --text:     #f1f5f9;
    --muted:    #6b7280;
    --error:    #f87171;
  }

  html, body { height: 100%; background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; font-size: 15px; line-height: 1.6; }

  body::before {
    content: '';
    position: fixed; inset: 0; z-index: 0;
    background:
      radial-gradient(ellipse 70% 50% at 80% 10%,  rgba(167,139,250,.18) 0%, transparent 60%),
      radial-gradient(ellipse 60% 50% at 10% 90%,  rgba(79,142,247,.15)  0%, transparent 60%),
      radial-gradient(ellipse 40% 30% at 50% 45%,  rgba(52,211,153,.07)  0%, transparent 70%);
    animation: meshShift 14s ease-in-out infinite alternate;
    pointer-events: none;
  }

  @keyframes meshShift { 0% { transform:scale(1); } 100% { transform:scale(1.08) rotate(-2deg); } }

  body::after {
    content: '';
    position: fixed; inset: 0; z-index: 0;
    background-image: radial-gradient(circle, rgba(255,255,255,.035) 1px, transparent 1px);
    background-size: 32px 32px;
    pointer-events: none;
  }

  .page {
    position: relative; z-index: 1;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 36px 16px;
  }

  .card {
    width: 100%; max-width: 460px;
    background: rgba(17,24,39,.88);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 48px 44px;
    backdrop-filter: blur(22px);
    box-shadow:
      0 0 0 1px rgba(167,139,250,.1),
      0 32px 64px rgba(0,0,0,.5),
      0 0 80px rgba(167,139,250,.07);
    animation: cardIn .55s cubic-bezier(.22,1,.36,1) both;
  }

  @keyframes cardIn { from { opacity:0; transform:translateY(28px) scale(.97); } to { opacity:1; transform:translateY(0) scale(1); } }

  /* ── Logo ── */
  .logo {
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 32px; text-decoration: none;
  }
  .logo-icon { width: 46px; height: 46px; flex-shrink: 0; }
  .logo-text {
    font-family: 'Syne', sans-serif;
    font-size: 22px; font-weight: 800; letter-spacing: .06em;
    background: linear-gradient(135deg, #4f8ef7 0%, #a78bfa 50%, #34d399 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
  }

  /* ── Steps indicator ── */
  .steps {
    display: flex; align-items: center; gap: 6px;
    margin-bottom: 28px;
  }
  .step {
    flex: 1; height: 3px; border-radius: 4px;
    background: var(--border);
    transition: background .4s;
  }
  .step.active { background: linear-gradient(90deg, #4f8ef7, #a78bfa); }
  .step.done   { background: var(--success); }

  /* ── Heading ── */
  .heading { margin-bottom: 6px; }
  .heading h1 { font-family: 'Syne', sans-serif; font-size: 24px; font-weight: 700; }
  .heading p  { color: var(--muted); font-size: 13.5px; margin-top: 4px; }

  .divider { height: 1px; background: var(--border); margin: 20px 0; }

  /* ── Success state ── */
  .success-box {
    text-align: center; padding: 20px 0;
    animation: fadeIn .4s ease;
  }
  .success-icon {
    width: 64px; height: 64px;
    background: rgba(52,211,153,.12);
    border: 2px solid rgba(52,211,153,.4);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px;
    color: var(--success);
  }
  .success-box h2 { font-family: 'Syne', sans-serif; font-size: 22px; margin-bottom: 8px; }
  .success-box p  { color: var(--muted); font-size: 14px; margin-bottom: 24px; }

  /* ── Form ── */
  .form-group { margin-bottom: 16px; }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }

  label {
    display: block; font-size: 13px; font-weight: 500;
    color: #cbd5e1; margin-bottom: 6px; letter-spacing: .02em;
  }

  .input-wrap { position: relative; }
  .input-icon {
    position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
    color: var(--muted); pointer-events: none; display: flex;
  }

  input[type="email"],
  input[type="password"],
  input[type="text"] {
    width: 100%;
    background: rgba(255,255,255,.04);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 11px 14px 11px 40px;
    color: var(--text);
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    outline: none;
    transition: border-color .2s, box-shadow .2s, background .2s;
  }

  input.has-error { border-color: rgba(248,113,113,.5) !important; }

  input:focus {
    border-color: rgba(167,139,250,.6);
    background: rgba(167,139,250,.05);
    box-shadow: 0 0 0 3px rgba(167,139,250,.1);
  }

  .toggle-password {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: var(--muted); cursor: pointer;
    display: flex; transition: color .2s;
  }
  .toggle-password:hover { color: var(--text); }

  /* ── Strength meter ── */
  .strength-wrap { margin-top: 8px; }
  .strength-bars { display: flex; gap: 4px; margin-bottom: 4px; }
  .strength-bar  { flex: 1; height: 3px; border-radius: 4px; background: var(--border); transition: background .3s; }
  .strength-label { font-size: 11.5px; color: var(--muted); }

  /* ── Checkbox ── */
  .checkbox-group {
    display: flex; align-items: flex-start; gap: 10px;
    margin-bottom: 20px;
  }
  .checkbox-group input[type="checkbox"] {
    width: 16px; height: 16px;
    accent-color: var(--accent2);
    margin-top: 3px; cursor: pointer;
    flex-shrink: 0;
    padding: 0;
  }
  .checkbox-group label { font-size: 13px; color: var(--muted); cursor: pointer; margin: 0; }
  .checkbox-group a { color: var(--accent); text-decoration: none; }
  .checkbox-group a:hover { text-decoration: underline; }

  /* ── Field error ── */
  .field-err {
    display: flex; align-items: center; gap: 5px;
    font-size: 12px; color: var(--error); margin-top: 5px;
  }

  /* ── Submit ── */
  .btn-primary {
    width: 100%; padding: 13px;
    background: linear-gradient(135deg, #a78bfa 0%, #4f8ef7 100%);
    border: none; border-radius: 12px;
    color: #fff;
    font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 700; letter-spacing: .04em;
    cursor: pointer; position: relative; overflow: hidden;
    transition: transform .15s, box-shadow .2s;
    box-shadow: 0 4px 20px rgba(167,139,250,.3);
  }
  .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(167,139,250,.42); }
  .btn-primary:active { transform: translateY(0); }

  .btn-secondary {
    display: block; width: 100%; padding: 13px;
    background: rgba(255,255,255,.06);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text);
    font-family: 'Syne', sans-serif; font-size: 15px; font-weight: 600;
    text-align: center; text-decoration: none;
    cursor: pointer;
    transition: background .2s, transform .15s;
  }
  .btn-secondary:hover { background: rgba(255,255,255,.1); transform: translateY(-1px); }

  .footer-link { text-align: center; font-size: 13.5px; color: var(--muted); margin-top: 22px; }
  .footer-link a { color: var(--accent2); text-decoration: none; font-weight: 500; }
  .footer-link a:hover { text-decoration: underline; }

  @keyframes fadeIn { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }

  @media (max-width: 480px) {
    .card { padding: 36px 22px; }
    .form-row { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>
<div class="page">
  <div class="card">

    <!-- Logo -->
    <a href="#" class="logo">
      <svg class="logo-icon" viewBox="0 0 46 46" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="lg1" x1="0" y1="0" x2="46" y2="46" gradientUnits="userSpaceOnUse">
            <stop offset="0%"  stop-color="#4f8ef7"/>
            <stop offset="50%" stop-color="#a78bfa"/>
            <stop offset="100%" stop-color="#34d399"/>
          </linearGradient>
        </defs>
        <path d="M23 4 L40 13.5 L40 32.5 L23 42 L6 32.5 L6 13.5 Z"
              stroke="url(#lg1)" stroke-width="2" fill="none" stroke-linejoin="round"/>
        <path d="M23 4 L23 23 M40 13.5 L23 23 M6 13.5 L23 23"
              stroke="url(#lg1)" stroke-width="1.5" opacity="0.6" stroke-linecap="round"/>
        <path d="M23 23 L23 42" stroke="url(#lg1)" stroke-width="1.5" opacity="0.4" stroke-linecap="round"/>
        <circle cx="23" cy="23" r="3" fill="url(#lg1)"/>
        <path d="M14 18 Q11 14 15 11 Q19 8 21 11" stroke="#4f8ef7" stroke-width="1.5" fill="none" stroke-linecap="round"/>
        <path d="M32 28 Q35 32 31 35 Q27 38 25 35" stroke="#a78bfa" stroke-width="1.5" fill="none" stroke-linecap="round"/>
      </svg>
      <span class="logo-text">SYNCUBE</span>
    </a>

    <?php if ($success): ?>
    <!-- ── Success ── -->
    <div class="success-box">
      <div class="success-icon">
        <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
      </div>
      <h2>Account Created!</h2>
      <p>Welcome to SYNCUBE, <strong><?= htmlspecialchars($_POST['name']) ?></strong>!<br>
         Your workspace is ready to go.</p>
      <a href="login.php" class="btn-secondary">Go to Login →</a>
    </div>

    <?php else: ?>
    <!-- ── Form ── -->

    <!-- Steps -->
    <div class="steps" id="steps">
      <div class="step active" id="s1"></div>
      <div class="step"        id="s2"></div>
      <div class="step"        id="s3"></div>
    </div>

    <div class="heading">
      <h1>Create your account</h1>
      <p>Your personal planning hub starts here</p>
    </div>

    <div class="divider"></div>

    <form method="POST" action="signup.php" id="signupForm" novalidate>

      <!-- Full Name -->
      <div class="form-group">
        <label for="name">Full Name</label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
          </span>
          <input type="text" id="name" name="name"
                 placeholder="Your full name"
                 value="<?= old('name') ?>"
                 class="<?= isset($errors['name']) ? 'has-error' : '' ?>"
                 required />
        </div>
        <?= err('name', $errors) ?>
      </div>

      <!-- Email -->
      <div class="form-group">
        <label for="email">Email Address</label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
          </span>
          <input type="email" id="email" name="email"
                 placeholder="you@example.com"
                 value="<?= old('email') ?>"
                 class="<?= isset($errors['email']) ? 'has-error' : '' ?>"
                 required autocomplete="email" />
        </div>
        <?= err('email', $errors) ?>
      </div>

      <!-- Password -->
      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
          </span>
          <input type="password" id="password" name="password"
                 placeholder="Min. 8 characters"
                 class="<?= isset($errors['password']) ? 'has-error' : '' ?>"
                 oninput="checkStrength(this.value)"
                 required />
          <button type="button" class="toggle-password" onclick="togglePwd('password')" aria-label="Show password">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </button>
        </div>
        <div class="strength-wrap">
          <div class="strength-bars">
            <div class="strength-bar" id="sb1"></div>
            <div class="strength-bar" id="sb2"></div>
            <div class="strength-bar" id="sb3"></div>
            <div class="strength-bar" id="sb4"></div>
          </div>
          <span class="strength-label" id="strengthLabel"></span>
        </div>
        <?= err('password', $errors) ?>
      </div>

      <!-- Confirm -->
      <div class="form-group">
        <label for="confirm">Confirm Password</label>
        <div class="input-wrap">
          <span class="input-icon">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
          </span>
          <input type="password" id="confirm" name="confirm"
                 placeholder="Repeat your password"
                 class="<?= isset($errors['confirm']) ? 'has-error' : '' ?>"
                 required />
          <button type="button" class="toggle-password" onclick="togglePwd('confirm')" aria-label="Show confirm">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </button>
        </div>
        <?= err('confirm', $errors) ?>
      </div>

      <!-- Agree -->
      <div class="checkbox-group">
        <input type="checkbox" id="agree" name="agree" value="1"
               <?= !empty($_POST['agree']) ? 'checked' : '' ?> />
        <label for="agree">
          I agree to SYNCUBE's <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
        </label>
      </div>
      <?= err('agree', $errors) ?>

      <button type="submit" class="btn-primary">Create My Account →</button>
    </form>

    <p class="footer-link">
      Already have an account? <a href="login.php">Sign in</a>
    </p>
    <?php endif; ?>

  </div>
</div>

<script>
function togglePwd(id) {
  const inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
}

function checkStrength(val) {
  let score = 0;
  if (val.length >= 8)                       score++;
  if (/[A-Z]/.test(val))                     score++;
  if (/[0-9]/.test(val))                     score++;
  if (/[^A-Za-z0-9]/.test(val))             score++;

  const colors  = ['', '#f87171', '#fb923c', '#facc15', '#34d399'];
  const labels  = ['', 'Weak', 'Fair', 'Good', 'Strong'];
  const bars    = [document.getElementById('sb1'), document.getElementById('sb2'),
                   document.getElementById('sb3'), document.getElementById('sb4')];

  bars.forEach((b, i) => {
    b.style.background = i < score ? colors[score] : 'rgba(255,255,255,.08)';
  });

  const lbl = document.getElementById('strengthLabel');
  lbl.textContent = val.length ? labels[score] : '';
  lbl.style.color = colors[score] || 'var(--muted)';

  // Update step indicator
  const s1 = document.getElementById('s1');
  const s2 = document.getElementById('s2');
  const s3 = document.getElementById('s3');
  if (val.length === 0) { s1.className='step active'; s2.className='step'; s3.className='step'; }
  else if (score < 3)   { s1.className='step done';   s2.className='step active'; s3.className='step'; }
  else                  { s1.className='step done';   s2.className='step done';   s3.className='step active'; }
}
</script>
</body>
</html>