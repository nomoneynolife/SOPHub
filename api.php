<?php
/**
 * SOP 系统 API
 * 提供登录、文档 CRUD、图片上传接口
 */

require __DIR__ . '/config.php';

session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CORS：本机部署不需要，留给以后用
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

// 输入过滤助手
function input(string $key, string $method = 'POST'): ?string {
    $src = $method === 'GET' ? $_GET : $_POST;
    return isset($src[$key]) ? trim((string)$src[$key]) : null;
}

// 统一 JSON 输出
function json_out($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 错误输出
function json_err(string $msg, int $code = 400): void {
    json_out(['ok' => false, 'msg' => $msg], $code);
}

// 检查登录态
function require_login(): void {
    if (empty($_SESSION['sop_logged_in'])) {
        json_err('未登录', 401);
    }
}

// 初始化 SQLite 数据库
function init_db(): PDO {
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON");

    // 创建表
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS docs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parent_id INTEGER NOT NULL DEFAULT 0,
            title TEXT NOT NULL,
            content TEXT NOT NULL DEFAULT '',
            sort INTEGER NOT NULL DEFAULT 0,
            is_folder INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_docs_parent ON docs(parent_id);
    ");
    return $pdo;
}

// 安全地获取 ID
function get_id(): int {
    $id = input('id', 'GET') ?? input('id', 'POST');
    if ($id === null || !ctype_digit($id)) {
        json_err('参数 id 缺失');
    }
    return (int)$id;
}

// ========== 路由 ==========
$action = $_GET['action'] ?? '';

try {
    $pdo = init_db();
    switch ($action) {
        case 'login':   do_login();
        case 'logout':  do_logout();
        case 'check':   do_check();
        case 'list':   do_list($pdo);
        case 'get':    do_get($pdo);
        case 'save':   do_save($pdo);
        case 'create': do_create($pdo);
        case 'delete': do_delete($pdo);
        case 'move':   do_move($pdo);
        case 'upload': do_upload();
        default:       json_err('未知操作');
    }
} catch (Throwable $e) {
    json_err('服务器错误: ' . $e->getMessage(), 500);
}

// ========== 登录 ==========
function do_login(): void {
    $pwd = input('password') ?? '';
    if (!hash_equals(ACCESS_PASSWORD, $pwd)) {
        sleep(1); // 防爆破
        json_err('密码错误', 401);
    }
    $_SESSION['sop_logged_in'] = true;
    json_out(['ok' => true]);
}

function do_logout(): void {
    session_destroy();
    json_out(['ok' => true]);
}

function do_check(): void {
    json_out(['ok' => true, 'logged_in' => !empty($_SESSION['sop_logged_in'])]);
}

// ========== 列表 ==========
function do_list(PDO $pdo): void {
    $rows = $pdo->query("SELECT id, parent_id, title, is_folder, sort, updated_at FROM docs ORDER BY sort, id")->fetchAll();
    json_out(['ok' => true, 'data' => $rows]);
}

// ========== 详情 ==========
function do_get(PDO $pdo): void {
    $id = get_id();
    $stmt = $pdo->prepare("SELECT * FROM docs WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        json_err('文档不存在', 404);
    }
    json_out(['ok' => true, 'data' => $row]);
}

// ========== 保存（支持部分更新） ==========
function do_save(PDO $pdo): void {
    require_login();
    $id = get_id();
    $title = input('title');
    $content = input('content');

    // 允许只更新其中一项
    $fields = [];
    $params = [];
    if ($title !== null) {
        $fields[] = 'title = ?';
        $params[] = $title;
    }
    if ($content !== null) {
        $fields[] = 'content = ?';
        $params[] = $content;
    }
    if (empty($fields)) {
        json_err('没有要更新的字段');
    }
    $fields[] = 'updated_at = ?';
    $params[] = date('Y-m-d H:i:s');
    $params[] = $id;

    $stmt = $pdo->prepare("UPDATE docs SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        // 不存在
        $check = $pdo->prepare("SELECT id FROM docs WHERE id = ?");
        $check->execute([$id]);
        if (!$check->fetch()) {
            json_err('文档不存在', 404);
        }
    }
    json_out(['ok' => true]);
}

// ========== 新建（需用户在 UI 上点击后才调用，符合"显式确认"原则） ==========
function do_create(PDO $pdo): void {
    require_login();
    $parentId = (int)(input('parent_id') ?? '0');
    $title = input('title') ?? '未命名';
    $isFolder = (int)(input('is_folder') ?? '0');

    // 校验父节点
    if ($parentId > 0) {
        $check = $pdo->prepare("SELECT id, is_folder FROM docs WHERE id = ?");
        $check->execute([$parentId]);
        $parent = $check->fetch();
        if (!$parent) {
            json_err('父节点不存在');
        }
        if (!$parent['is_folder']) {
            json_err('父节点不是文件夹');
        }
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO docs (parent_id, title, content, sort, is_folder, created_at, updated_at) VALUES (?, ?, '', 0, ?, ?, ?)");
    $stmt->execute([$parentId, $title, $isFolder, $now, $now]);
    $newId = $pdo->lastInsertId();
    json_out(['ok' => true, 'id' => (int)$newId]);
}

// ========== 删除（递归删除子节点） ==========
function do_delete(PDO $pdo): void {
    require_login();
    $id = get_id();

    // 收集要删除的所有 ID
    $toDelete = [$id];
    $queue = [$id];
    while (!empty($queue)) {
        $placeholders = implode(',', array_fill(0, count($queue), '?'));
        $stmt = $pdo->prepare("SELECT id FROM docs WHERE parent_id IN ($placeholders)");
        $stmt->execute($queue);
        $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $toDelete = array_merge($toDelete, $children);
        $queue = $children;
    }
    $toDelete = array_unique($toDelete);

    // 删除文档记录
    $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
    $stmt = $pdo->prepare("DELETE FROM docs WHERE id IN ($placeholders)");
    $stmt->execute($toDelete);

    json_out(['ok' => true, 'deleted' => count($toDelete)]);
}

// ========== 移动/排序 ==========
function do_move(PDO $pdo): void {
    require_login();
    $id = get_id();
    $parentId = input('parent_id');
    $sort = input('sort');

    $fields = [];
    $params = [];
    if ($parentId !== null) {
        $fields[] = 'parent_id = ?';
        $params[] = (int)$parentId;
    }
    if ($sort !== null) {
        $fields[] = 'sort = ?';
        $params[] = (int)$sort;
    }
    if (empty($fields)) {
        json_err('没有要更新的字段');
    }
    $fields[] = 'updated_at = ?';
    $params[] = date('Y-m-d H:i:s');
    $params[] = $id;
    $stmt = $pdo->prepare("UPDATE docs SET " . implode(', ', $fields) . " WHERE id = ?");
    $stmt->execute($params);
    json_out(['ok' => true]);
}

// ========== 图片上传 ==========
function do_upload(): void {
    require_login();

    if (empty($_FILES['file'])) {
        json_err('未接收到文件');
    }
    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_err('上传失败，错误码: ' . $file['error']);
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        json_err('文件过大');
    }

    // 真实 MIME 校验（防止伪造扩展名）
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        json_err('不支持的图片类型: ' . $mime);
    }

    $ext = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/bmp'   => 'bmp',
    ][$mime];

    // 按年月分目录，避免单目录文件过多
    $subDir = date('Ym');
    $destDir = UPLOAD_DIR . $subDir;
    if (!is_dir($destDir)) {
        mkdir($destDir, 0777, true);
    }

    $fileName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $destDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        json_err('保存文件失败');
    }

    // 返回相对 URL（TinyMCE images_upload_url 模式要求返回 location 字段）
    $url = 'data/uploads/' . $subDir . '/' . $fileName;
    json_out(['ok' => true, 'location' => $url, 'url' => $url]);
}
