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
            expanded INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_docs_parent ON docs(parent_id);
    ");
    // 兼容旧库：若 expanded 列不存在则补加
    try { $pdo->exec("ALTER TABLE docs ADD COLUMN expanded INTEGER NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
    // 兼容旧库：若 deleted 列不存在则补加
    try { $pdo->exec("ALTER TABLE docs ADD COLUMN deleted INTEGER NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
    // 兼容旧库：若 deleted_at 列不存在则补加
    try { $pdo->exec("ALTER TABLE docs ADD COLUMN deleted_at TEXT"); } catch (Throwable $e) {}
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
        case 'trash_list': do_trash_list($pdo);
        case 'restore': do_restore($pdo);
        case 'purge': do_purge($pdo);
        case 'move':   do_move($pdo);
        case 'reorder': do_reorder($pdo);
        case 'upload': do_upload();
        case 'fetch_url': do_fetch_url();
        case 'import_docx': do_import_docx();
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

// ========== 列表（仅未删除） ==========
function do_list(PDO $pdo): void {
    $rows = $pdo->query("SELECT id, parent_id, title, is_folder, expanded, sort, updated_at FROM docs WHERE deleted = 0 ORDER BY sort, id")->fetchAll();
    json_out(['ok' => true, 'data' => $rows]);
}

// ========== 回收站列表（仅已删除） ==========
function do_trash_list(PDO $pdo): void {
    require_login();
    $rows = $pdo->query("SELECT id, parent_id, title, is_folder, expanded, sort, deleted_at FROM docs WHERE deleted = 1 ORDER BY deleted_at DESC, id")->fetchAll();
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
    $expanded = input('expanded');

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
    if ($expanded !== null) {
        $fields[] = 'expanded = ?';
        $params[] = $expanded ? 1 : 0;
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

// ========== 软删除（移入回收站，递归标记子节点） ==========
function do_delete(PDO $pdo): void {
    require_login();
    $id = get_id();

    // 收集要软删除的所有 ID（节点 + 所有子孙）
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

    // 标记为已删除（保留 parent_id 关系，便于还原时保持树结构）
    $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE docs SET deleted = 1, deleted_at = ? WHERE id IN ($placeholders)");
    $stmt->execute(array_merge([$now], $toDelete));

    json_out(['ok' => true, 'deleted' => count($toDelete)]);
}

// ========== 还原（从回收站恢复，递归标记子节点） ==========
function do_restore(PDO $pdo): void {
    require_login();
    $id = get_id();

    // 收集要还原的所有 ID（节点 + 所有子孙，但只包含 deleted=1 的）
    $toRestore = [$id];
    $queue = [$id];
    while (!empty($queue)) {
        $placeholders = implode(',', array_fill(0, count($queue), '?'));
        $stmt = $pdo->prepare("SELECT id FROM docs WHERE parent_id IN ($placeholders) AND deleted = 1");
        $stmt->execute($queue);
        $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $toRestore = array_merge($toRestore, $children);
        $queue = $children;
    }
    $toRestore = array_unique($toRestore);

    // 检查父节点：如果父节点仍在回收站（deleted=1），把当前节点挂到根（parent_id=0）
    $stmt = $pdo->prepare("SELECT parent_id FROM docs WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && $row['parent_id'] != 0) {
        $parentCheck = $pdo->prepare("SELECT deleted FROM docs WHERE id = ?");
        $parentCheck->execute([$row['parent_id']]);
        $parent = $parentCheck->fetch();
        if ($parent && $parent['deleted'] == 1) {
            // 父节点还在回收站，把当前节点提升为根节点
            $moveRoot = $pdo->prepare("UPDATE docs SET parent_id = 0 WHERE id = ?");
            $moveRoot->execute([$id]);
        }
    }

    // 还原
    $placeholders = implode(',', array_fill(0, count($toRestore), '?'));
    $stmt = $pdo->prepare("UPDATE docs SET deleted = 0, deleted_at = NULL WHERE id IN ($placeholders)");
    $stmt->execute($toRestore);

    json_out(['ok' => true, 'restored' => count($toRestore)]);
}

// ========== 彻底删除（从回收站永久删除） ==========
function do_purge(PDO $pdo): void {
    require_login();
    $id = get_id();

    // 收集要彻底删除的所有 ID（节点 + 所有子孙，只包含 deleted=1 的）
    $toPurge = [$id];
    $queue = [$id];
    while (!empty($queue)) {
        $placeholders = implode(',', array_fill(0, count($queue), '?'));
        $stmt = $pdo->prepare("SELECT id FROM docs WHERE parent_id IN ($placeholders) AND deleted = 1");
        $stmt->execute($queue);
        $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $toPurge = array_merge($toPurge, $children);
        $queue = $children;
    }
    $toPurge = array_unique($toPurge);

    // 在删除前先收集所有文档引用的本地图片路径
    $placeholders = implode(',', array_fill(0, count($toPurge), '?'));
    $stmt = $pdo->prepare("SELECT id, content FROM docs WHERE id IN ($placeholders)");
    $stmt->execute($toPurge);
    $docs = $stmt->fetchAll();

    $deletedFiles = 0;
    foreach ($docs as $doc) {
        // 找出所有本地图片引用 data/uploads/...
        preg_match_all('#data/uploads/([\d]+/doc[\d]+/[\w\-\.]+)#', $doc['content'], $matches);
        foreach ($matches[1] as $relPath) {
            $absPath = __DIR__ . '/data/uploads/' . $relPath;
            if (file_exists($absPath)) {
                @unlink($absPath);
                $deletedFiles++;
            }
        }
    }

    // 真实删除记录
    $placeholders = implode(',', array_fill(0, count($toPurge), '?'));
    $stmt = $pdo->prepare("DELETE FROM docs WHERE id IN ($placeholders)");
    $stmt->execute($toPurge);

    // 清理空的 docID 目录
    $docDirs = glob(__DIR__ . '/data/uploads/*/doc*', GLOB_ONLYDIR);
    foreach ($docDirs as $dir) {
        $files = glob($dir . '/*');
        if (empty($files)) {
            @rmdir($dir);
            // 如果年月目录也空了，也清理
            $ymDir = dirname($dir);
            if (is_dir($ymDir) && glob($ymDir . '/*') === []) {
                @rmdir($ymDir);
            }
        }
    }

    json_out(['ok' => true, 'purged' => count($toPurge), 'deleted_files' => $deletedFiles]);
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

// ========== 批量重排（拖拽后调用） ==========
function do_reorder(PDO $pdo): void {
    require_login();
    $items = input('items');
    if ($items === null) json_err('参数 items 缺失');
    $items = json_decode($items, true);
    if (!is_array($items) || empty($items)) json_err('参数格式错误');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE docs SET parent_id = ?, sort = ?, updated_at = ? WHERE id = ?");
        $now = date('Y-m-d H:i:s');
        foreach ($items as $i => $item) {
            if (!isset($item['id'], $item['parent_id'])) continue;
            $stmt->execute([(int)$item['parent_id'], (int)$i, $now, (int)$item['id']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        json_err('重排失败: ' . $e->getMessage());
    }
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

    // 按年月分目录 + 文档 ID 子目录，方便区分附件归属
    $ym = date('Ym');
    $docId = input('doc_id') ?: 'temp';  // 编辑中未保存的文档用 temp 目录
    $subDir = $ym . '/doc' . $docId;
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

// ========== 下载远程图片到本地（粘贴外链图片时自动调用） ==========
function do_fetch_url(): void {
    require_login();

    $url = input('url', 'GET');
    $docId = input('doc_id', 'GET') ?: 'temp';

    if (!$url) {
        json_err('缺少 url 参数');
    }
    // 安全检查：只允许 http(s)
    if (!preg_match('#^https?://#i', $url)) {
        json_err('只允许 http/https 链接');
    }
    // 禁止下载内网地址（防止 SSRF）
    $host = parse_url($url, PHP_URL_HOST);
    if ($host && (filter_var($host, FILTER_VALIDATE_IP) || preg_match('/localhost|127\.0\.0\.1|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\./', $host))) {
        json_err('禁止下载内网地址');
    }
    // 禁止下载上传目录下的文件（循环引用）
    if (strpos($url, 'data/uploads') !== false || strpos($url, 'uploads/') !== false) {
        json_err('已是本地地址');
    }

    // 下载
    $opts = [
        'http' => [
            'timeout' => 15,
            'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
        ],
    ];
    $ctx = stream_context_create($opts);
    $data = @file_get_contents($url, false, $ctx);
    if ($data === false || $data === '') {
        json_err('下载失败');
    }

    // MIME 校验
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($data);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/bmp'   => 'bmp',
    ];
    if (!isset($allowed[$mime])) {
        json_err('不支持的图片类型: ' . $mime);
    }
    $ext = $allowed[$mime];

    // 存储路径: 年月/docID/
    $ym = date('Ym');
    $subDir = $ym . '/doc' . $docId;
    $destDir = UPLOAD_DIR . $subDir;
    if (!is_dir($destDir)) {
        mkdir($destDir, 0777, true);
    }

    $fileName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $destDir . '/' . $fileName;

    if (!file_put_contents($destPath, $data)) {
        json_err('保存失败');
    }

    $localUrl = 'data/uploads/' . $subDir . '/' . $fileName;
    json_out(['ok' => true, 'location' => $localUrl, 'url' => $localUrl]);
}

// ========== 导入 Word(.docx) 文件：解析图片和文本生成 HTML ==========
// .docx 本质是 zip：word/document.xml 是正文，word/media/ 是图片，
// word/_rels/document.xml.rels 建立 rId -> 图片路径 的关系。
// 浏览器粘贴 Word 图文时因 file:// 安全限制无法读取内嵌图片，
// 这里通过文件导入绕过该限制。
function do_import_docx(): void {
    require_login();

    if (!class_exists('ZipArchive')) {
        json_err('服务器缺少 ZipArchive 扩展，无法解析 Word 文件');
    }
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

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'docx') {
        json_err('只支持 .docx 格式（旧版 .doc 不支持，请在 Word 中另存为 .docx）');
    }
    // docx 真实 MIME 可能是 application/zip 或 octet-stream，扩展名校验为主

    $docId = input('doc_id') ?: 'temp';
    $ym = date('Ym');
    $subDir = $ym . '/doc' . $docId;
    $destDir = UPLOAD_DIR . $subDir;
    if (!is_dir($destDir)) {
        mkdir($destDir, 0777, true);
    }

    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) {
        json_err('无法打开 Word 文件，可能已损坏');
    }

    // 1) 读取关系文件：rId => "media/image1.png"
    $rels = []; // rId => rels 中的 Target（如 media/image1.png）
    $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
    if ($relsXml) {
        $relsDoc = new DOMDocument();
        if (@$relsDoc->loadXML($relsXml)) {
            foreach ($relsDoc->getElementsByTagName('Relationship') as $rel) {
                $type = $rel->getAttribute('Type');
                if (strpos($type, 'image') !== false) {
                    $rels[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
                }
            }
        }
    }

    // 2) 提取 word/media/ 下所有图片到本地，并建立 rId => 本地URL 映射
    $ridToUrl = []; // rId => data/uploads/.../xxx.png
    $mediaLocalMap = []; // "media/image1.png" => 本地URL
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if ($name === false || strpos($name, 'word/media/') !== 0) continue;

        $imgData = $zip->getFromIndex($i);
        if ($imgData === false || $imgData === '') continue;

        $basename = basename($name);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $imgMime = $finfo->buffer($imgData);
        $extMap = [
            'image/jpeg' => 'jpg', 'image/png' => 'png',
            'image/gif' => 'gif', 'image/webp' => 'webp',
            'image/bmp' => 'bmp',
        ];
        if (isset($extMap[$imgMime])) {
            $imgExt = $extMap[$imgMime];
        } else {
            // 兜底按原扩展名
            $origExt = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
            $imgExt = in_array($origExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true) ? $origExt : 'png';
        }

        $fileName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $imgExt;
        $destPath = $destDir . '/' . $fileName;
        if (@file_put_contents($destPath, $imgData) === false) {
            continue; // 单张失败不影响整体
        }
        $localUrl = 'data/uploads/' . $subDir . '/' . $fileName;
        $mediaLocalMap['media/' . $basename] = $localUrl;
    }

    // 通过 rels 把 rId 关联到本地 URL
    foreach ($rels as $rid => $target) {
        $target = ltrim($target, '/');
        if (isset($mediaLocalMap[$target])) {
            $ridToUrl[$rid] = $mediaLocalMap[$target];
        }
    }

    // 3) 读取 document.xml
    $docXml = $zip->getFromName('word/document.xml');
    $zip->close();
    if (!$docXml) {
        json_err('无法读取文档内容，可能已损坏');
    }

    $html = docx_xml_to_html($docXml, $ridToUrl);
    json_out(['ok' => true, 'html' => $html]);
}

// 把 Word 的 document.xml 转成 HTML
function docx_xml_to_html(string $xml, array $ridToUrl): string {
    $dom = new DOMDocument();
    if (!@$dom->loadXML($xml)) {
        return '<p>文档解析失败</p>';
    }
    $xp = new DOMXPath($dom);
    $w  = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $r  = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    $a  = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    $wp = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    $xp->registerNamespace('w',  $w);
    $xp->registerNamespace('r',  $r);
    $xp->registerNamespace('a',  $a);
    $xp->registerNamespace('wp', $wp);

    $body = $xp->query('//w:body')->item(0);
    if (!$body) return '';

    $html = '';
    foreach ($body->childNodes as $node) {
        if ($node->nodeType !== XML_ELEMENT_NODE) continue;
        if ($node->localName === 'p') {
            $html .= docx_paragraph_to_html($node, $xp, $ridToUrl);
        } elseif ($node->localName === 'tbl') {
            $html .= docx_table_to_html($node, $xp, $ridToUrl);
        }
    }
    return $html;
}

// 段落转 HTML
function docx_paragraph_to_html(DOMElement $p, DOMXPath $xp, array $ridToUrl): string {
    // 段落样式（标题等）
    $style = '';
    $styleNode = $xp->query('w:pPr/w:pStyle', $p)->item(0);
    if ($styleNode) {
        $style = strtolower($styleNode->getAttribute('w:val'));
    }
    $tag = 'p';
    if (preg_match('/heading1|^1$/', $style) || stripos($style, '标题1') !== false) $tag = 'h1';
    elseif (preg_match('/heading2|^2$/', $style) || stripos($style, '标题2') !== false) $tag = 'h2';
    elseif (preg_match('/heading3|^3$/', $style) || stripos($style, '标题3') !== false) $tag = 'h3';
    elseif (preg_match('/heading4|^4$/', $style) || stripos($style, '标题4') !== false) $tag = 'h4';
    elseif (preg_match('/heading5|^5$/', $style) || stripos($style, '标题5') !== false) $tag = 'h5';
    elseif (preg_match('/heading6|^6$/', $style) || stripos($style, '标题6') !== false) $tag = 'h6';

    $content = '';
    // 处理段落直接子节点：w:r（文本运行）、w:hyperlink、w:bookmarkStart 等
    foreach ($p->childNodes as $node) {
        if ($node->nodeType !== XML_ELEMENT_NODE) continue;
        if ($node->localName === 'r') {
            $content .= docx_run_to_html($node, $xp, $ridToUrl);
        } elseif ($node->localName === 'hyperlink') {
            // 超链接内的文本运行
            $inner = '';
            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE && $child->localName === 'r') {
                    $inner .= docx_run_to_html($child, $xp, $ridToUrl);
                }
            }
            $anchor = $node->getAttribute('w:anchor');
            $rId = $node->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            $href = $anchor ? '#' . $anchor : ($rId ? '' : '');
            if ($href) {
                $content .= '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '">' . $inner . '</a>';
            } else {
                $content .= $inner;
            }
        }
    }

    if (trim(strip_tags($content)) === '' && strpos($content, '<img') === false) {
        return ''; // 空段落跳过
    }
    return '<' . $tag . '>' . $content . '</' . $tag . '>';
}

// 文本运行转 HTML（处理粗体/斜体/下划线/图片/换行/制表符）
function docx_run_to_html(DOMElement $r, DOMXPath $xp, array $ridToUrl): string {
    // 运行属性
    $bold = $italic = $underline = $strike = false;
    $rPr = $xp->query('w:rPr', $r)->item(0);
    if ($rPr) {
        if ($xp->query('w:b', $rPr)->length > 0) $bold = true;
        if ($xp->query('w:i', $rPr)->length > 0) $italic = true;
        if ($xp->query('w:u', $rPr)->length > 0) $underline = true;
        if ($xp->query('w:strike', $rPr)->length > 0) $strike = true;
    }

    $content = '';
    foreach ($r->childNodes as $node) {
        if ($node->nodeType !== XML_ELEMENT_NODE) continue;
        if ($node->localName === 't') {
            $content .= htmlspecialchars($node->nodeValue, ENT_QUOTES, 'UTF-8');
            // 保留空格：xml:space="preserve"
            if ($node->getAttribute('xml:space') === 'preserve') {
                // 已保留
            }
        } elseif ($node->localName === 'br') {
            $content .= '<br>';
        } elseif ($node->localName === 'tab') {
            $content .= '&nbsp;&nbsp;&nbsp;&nbsp;';
        } elseif ($node->localName === 'drawing' || $node->localName === 'pict') {
            $content .= docx_drawing_to_html($node, $xp, $ridToUrl);
        }
    }

    if ($strike) $content = '<del>' . $content . '</del>';
    if ($underline) $content = '<u>' . $content . '</u>';
    if ($italic) $content = '<em>' . $content . '</em>';
    if ($bold) $content = '<strong>' . $content . '</strong>';
    return $content;
}

// 图片转 HTML：通过 a:blip r:embed="rIdX" 关联本地图片
function docx_drawing_to_html(DOMElement $drawing, DOMXPath $xp, array $ridToUrl): string {
    $blips = $xp->query('.//a:blip', $drawing);
    if ($blips->length === 0) return '';

    $out = '';
    foreach ($blips as $blip) {
        $rId = $blip->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed');
        if (!$rId) {
            // 兜底：部分解析器对命名空间属性的处理
            $rId = $blip->getAttribute('r:embed');
        }
        if ($rId && isset($ridToUrl[$rId])) {
            $out .= '<img src="' . htmlspecialchars($ridToUrl[$rId], ENT_QUOTES) . '" alt="" style="max-width:100%;height:auto;" />';
        }
    }
    return $out;
}

// 表格转 HTML
function docx_table_to_html(DOMElement $tbl, DOMXPath $xp, array $ridToUrl): string {
    $html = '<table border="1" style="border-collapse:collapse;width:100%;">';
    $rows = $xp->query('w:tr', $tbl);
    foreach ($rows as $tr) {
        $html .= '<tr>';
        $cells = $xp->query('w:tc', $tr);
        foreach ($cells as $tc) {
            $cellHtml = '';
            foreach ($xp->query('w:p', $tc) as $p) {
                $cellHtml .= docx_paragraph_to_html($p, $xp, $ridToUrl);
            }
            $html .= '<td style="border:1px solid #ccc;padding:4px 8px;">' . $cellHtml . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
    return $html;
}
