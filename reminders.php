<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

require __DIR__ . '/../db_connect.php';

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function out($data) {
    echo json_encode($data);
    exit;
}

switch ($action) {

    // ── LIST all reminders for the logged-in user ────────────────
    case 'list': {
        $stmt = $pdo->prepare(
            'SELECT reminder_id, title, message, reminder_datetime, status, created_at
             FROM reminders
             WHERE user_id = :uid
             ORDER BY reminder_datetime ASC'
        );
        $stmt->execute([':uid' => $userId]);
        out(['ok' => true, 'reminders' => $stmt->fetchAll()]);
        break;
    }

    // ── ADD a new reminder ────────────────────────────────────────
    case 'add': {
        $title = trim($_POST['title'] ?? '');
        $when  = trim($_POST['reminder_datetime'] ?? '');

        if ($title === '' || $when === '') {
            http_response_code(400);
            out(['ok' => false, 'error' => 'Title and time are required']);
        }

        $ts = strtotime($when);
        if ($ts === false) {
            http_response_code(400);
            out(['ok' => false, 'error' => 'Invalid date/time']);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO reminders (user_id, title, message, reminder_datetime, status, created_at)
             VALUES (:uid, :title, :message, :dt, "Pending", NOW())'
        );
        $stmt->execute([
            ':uid'     => $userId,
            ':title'   => $title,
            ':message' => trim($_POST['message'] ?? ''),
            ':dt'      => date('Y-m-d H:i:s', $ts),
        ]);

        out(['ok' => true, 'reminder_id' => $pdo->lastInsertId()]);
        break;
    }

    // ── MARK a reminder as Sent (fired) ───────────────────────────
    case 'mark_sent': {
        $id = (int)($_POST['reminder_id'] ?? 0);
        $stmt = $pdo->prepare('UPDATE reminders SET status = "Sent" WHERE reminder_id = :id AND user_id = :uid AND status = "Pending"');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        out(['ok' => true]);
        break;
    }

    // ── DELETE a reminder ─────────────────────────────────────────
    case 'delete': {
        $id = (int)($_POST['reminder_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM reminders WHERE reminder_id = :id AND user_id = :uid');
        $stmt->execute([':id' => $id, ':uid' => $userId]);
        out(['ok' => true]);
        break;
    }

    default:
        http_response_code(400);
        out(['ok' => false, 'error' => 'Unknown action']);
}