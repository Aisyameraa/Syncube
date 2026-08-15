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

    // ── LIST all tasks for the logged-in user ──────────────────
    case 'list': {
        $stmt = $pdo->prepare(
            'SELECT task_id, title, description, category, priority, status, due_date, created_at, updated_at
             FROM tasks
             WHERE user_id = :uid
             ORDER BY (status = "Completed") ASC, due_date IS NULL, due_date ASC, created_at DESC'
        );
        $stmt->execute([':uid' => $userId]);
        out(['ok' => true, 'tasks' => $stmt->fetchAll()]);
        break;
    }

    // ── ADD a new task ──────────────────────────────────────────
    case 'add': {
        $title    = trim($_POST['title'] ?? '');
        $priority = $_POST['priority'] ?? 'Medium';
        $category = trim($_POST['category'] ?? '');
        $dueDate  = trim($_POST['due_date'] ?? '');

        if ($title === '') {
            http_response_code(400);
            out(['ok' => false, 'error' => 'Title is required']);
        }

        $allowedPriority = ['Low', 'Medium', 'High'];
        if (!in_array($priority, $allowedPriority, true)) $priority = 'Medium';

        $dueDateValue = null;
        if ($dueDate !== '') {
            $ts = strtotime($dueDate);
            if ($ts !== false) $dueDateValue = date('Y-m-d H:i:s', $ts);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO tasks (user_id, title, description, category, priority, status, due_date, created_at, updated_at)
             VALUES (:uid, :title, :description, :category, :priority, "Pending", :due_date, NOW(), NOW())'
        );
        $stmt->execute([
            ':uid'         => $userId,
            ':title'       => $title,
            ':description' => trim($_POST['description'] ?? ''),
            ':category'    => $category !== '' ? $category : null,
            ':priority'    => $priority,
            ':due_date'    => $dueDateValue,
        ]);

        out(['ok' => true, 'task_id' => $pdo->lastInsertId()]);
        break;
    }

    // ── TOGGLE status Pending <-> Completed ─────────────────────
    case 'toggle': {
        $taskId = (int)($_POST['task_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT status FROM tasks WHERE task_id = :id AND user_id = :uid');
        $stmt->execute([':id' => $taskId, ':uid' => $userId]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); out(['ok' => false, 'error' => 'Task not found']); }

        $newStatus = $row['status'] === 'Completed' ? 'Pending' : 'Completed';
        $upd = $pdo->prepare('UPDATE tasks SET status = :status, updated_at = NOW() WHERE task_id = :id AND user_id = :uid');
        $upd->execute([':status' => $newStatus, ':id' => $taskId, ':uid' => $userId]);

        out(['ok' => true, 'status' => $newStatus]);
        break;
    }

    // ── DELETE a task ────────────────────────────────────────────
    case 'delete': {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE task_id = :id AND user_id = :uid');
        $stmt->execute([':id' => $taskId, ':uid' => $userId]);
        out(['ok' => true]);
        break;
    }

    // ── CLEAR all completed tasks ────────────────────────────────
    case 'clear_done': {
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE user_id = :uid AND status = "Completed"');
        $stmt->execute([':uid' => $userId]);
        out(['ok' => true]);
        break;
    }

    default:
        http_response_code(400);
        out(['ok' => false, 'error' => 'Unknown action']);
}