<?php
/**
 * 迁移历史上传文件到新目录结构
 *
 * 旧结构: data/uploads/202609/xxx.jpg
 * 新结构: data/uploads/202609/doc123/xxx.jpg
 *
 * 使用方法:
 *   浏览器访问 migrate_uploads.php 查看预览
 *   加 ?run=1 执行实际迁移
 *   加 ?rollback=1 回滚（把文件移回旧目录）
 *
 * 安全提示: 执行前请备份 data/ 目录！
 */

require __DIR__ . '/config.php';

// 只允许本机或登录用户访问
session_start();
$is_local = ($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1' || ($_SERVER['REMOTE_ADDR'] ?? '') === '::1';
$is_admin = !empty($_SESSION['sop_logged_in']);
if (!$is_local && !$is_admin) {
    http_response_code(403);
    die('禁止访问：仅本机或登录用户可执行迁移');
}

header('Content-Type: text/html; charset=utf-8');
$pdo = new PDO('sqlite:' . DB_FILE);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_GET['action'] ?? 'preview';  // preview | run | rollback
$dryRun = ($action === 'preview');
$rollback = ($action === 'rollback');

// ========== 收集所有文档内容中的图片引用 ==========
$stmt = $pdo->query("SELECT id, title, content FROM docs WHERE deleted = 0 AND content != ''");
$docs = $stmt->fetchAll();

$oldPattern = '#data/uploads/(\d{6})/([\w\.]+)#';     // 旧格式: data/uploads/202609/xxx.jpg
$newPattern = '#data/uploads/(\d{6})/doc(\d+)/([\w\.]+)#';  // 新格式: data/uploads/202609/doc123/xxx.jpg

$totalMoved = 0;
$totalUpdated = 0;
$totalSkipped = 0;
$errors = [];
$logs = [];

foreach ($docs as $doc) {
    $docId = (int)$doc['id'];
    $content = $doc['content'];
    $originalContent = $content;

    // 如果已经是新格式，跳过
    if (preg_match($newPattern, $content)) {
        $logs[] = "<div style='color:#059669'>✓ 文档「{$doc['title']}」(id=$docId) 已是新格式，跳过</div>";
        continue;
    }

    // 找出所有旧格式的图片
    preg_match_all($oldPattern, $content, $matches, PREG_SET_ORDER);
    if (empty($matches)) {
        $logs[] = "<div style='color:#6b7280'>- 文档「{$doc['title']}」(id=$docId) 无旧格式图片</div>";
        continue;
    }

    foreach ($matches as $m) {
        $ym = $m[1];          // 202609
        $fileName = $m[2];     // xxx.jpg
        $oldRelPath = "data/uploads/$ym/$fileName";
        $newRelPath = "data/uploads/$ym/doc$docId/$fileName";

        $oldAbsPath = __DIR__ . '/' . $oldRelPath;
        $newAbsPath = __DIR__ . '/' . $newRelPath;

        if ($rollback) {
            // 回滚模式：docID 子目录 → 根目录
            if (file_exists($oldAbsPath)) {
                $logs[] = "<div style='color:#059669'>✓ 已在旧位置: $oldRelPath</div>";
                $totalSkipped++;
                continue;
            }
            // 找新位置的文件移回旧位置
            $newPath = __DIR__ . "/data/uploads/$ym/doc$docId/$fileName";
            if (file_exists($newPath)) {
                if ($dryRun) {
                    $logs[] = "<div style='color:#d97706'>(预览) 将移动: $ym/doc$docId/$fileName → $ym/$fileName</div>";
                } else {
                    @mkdir(dirname($oldAbsPath), 0777, true);
                    if (rename($newPath, $oldAbsPath)) {
                        $logs[] = "<div style='color:#059669'>✓ 已移回: $ym/doc$docId/$fileName → $ym/$fileName</div>";
                        // 更新文档内容中的路径
                        $content = str_replace("data/uploads/$ym/doc$docId/$fileName", "data/uploads/$ym/$fileName", $content);
                        $totalMoved++;
                    } else {
                        $errors[] = "移回失败: $newPath";
                    }
                }
            } else {
                $logs[] = "<div style='color:#dc2626'>✗ 文件不存在: $newPath</div>";
            }
        } else {
            // 迁移模式：根目录 → docID 子目录
            if (!file_exists($oldAbsPath)) {
                // 可能已经迁移过了，检查新位置
                $checkNew = __DIR__ . "/data/uploads/$ym/doc$docId/$fileName";
                if (file_exists($checkNew)) {
                    $logs[] = "<div style='color:#059669'>✓ 已迁移过: $ym/doc$docId/$fileName</div>";
                    // 更新文档内容中的路径（如果还没更新）
                    if (strpos($content, "data/uploads/$ym/$fileName") !== false) {
                        $content = preg_replace(
                            "#data/uploads/$ym/$fileName#",
                            "data/uploads/$ym/doc$docId/$fileName",
                            $content
                        );
                    }
                } else {
                    $errors[] = "文件不存在: $oldAbsPath (文档id=$docId)";
                    $logs[] = "<div style='color:#dc2626'>✗ 文件不存在，跳过: $oldRelPath</div>";
                }
                $totalSkipped++;
                continue;
            }

            if ($dryRun) {
                $logs[] = "<div style='color:#d97706'>(预览) 将移动: $ym/$fileName → $ym/doc$docId/$fileName</div>";
                $totalMoved++;
            } else {
                @mkdir(dirname($newAbsPath), 0777, true);
                if (rename($oldAbsPath, $newAbsPath)) {
                    $logs[] = "<div style='color:#059669'>✓ 已移动: $ym/$fileName → $ym/doc$docId/$fileName</div>";
                    $totalMoved++;
                    // 更新内容中的路径
                    $content = preg_replace(
                        "#data/uploads/$ym/$fileName#",
                        "data/uploads/$ym/doc$docId/$fileName",
                        $content
                    );
                } else {
                    $errors[] = "移动失败: $oldAbsPath → $newAbsPath";
                    $logs[] = "<div style='color:#dc2626'>✗ 移动失败: $oldRelPath</div>";
                }
            }
        }
    }

    // 更新数据库
    if (!$dryRun && $content !== $originalContent) {
        $stmt = $pdo->prepare("UPDATE docs SET content = ? WHERE id = ?");
        $stmt->execute([$content, $docId]);
        $totalUpdated++;
        $logs[] = "<div style='color:#7c3aed'>★ 已更新文档内容: {$doc['title']} (id=$docId)</div>";
    }
}

// ========== 扫描孤儿文件（根目录下未被任何文档引用的文件） ==========
$orphanCount = 0;
// 收集所有文档引用的本地图片路径
$allReferenced = [];
foreach ($docs as $doc) {
    preg_match_all('#data/uploads/(\d{6}/(?:doc\d+/)?[\w\-\.]+)#', $doc['content'], $ref);
    foreach ($ref[1] as $rel) {
        $allReferenced[] = __DIR__ . '/data/uploads/' . $rel;
    }
}

// 扫描年月根目录下的文件
$ymDirs = glob(__DIR__ . '/data/uploads/*', GLOB_ONLYDIR);
foreach ($ymDirs as $ymDir) {
    // 跳过 _orphan 归档目录
    if (basename($ymDir) === '_orphan') continue;
    $rootFiles = glob($ymDir . '/*');
    foreach ($rootFiles as $file) {
        if (!is_file($file)) continue;  // 跳过子目录（docID 目录）
        $isReferenced = in_array($file, $allReferenced, true);
        if (!$isReferenced) {
            $orphanCount++;
            $rel = str_replace(__DIR__ . '/', '', $file);
            if ($dryRun) {
                $logs[] = "<div style='color:#dc2626'>⚠ 孤儿文件（无文档引用）: $rel</div>";
            } elseif ($rollback) {
                // 回滚模式不处理孤儿
            } else {
                // 迁移模式：归档到 _orphan 目录
                $orphanDir = __DIR__ . '/data/uploads/_orphan/' . basename($ymDir);
                @mkdir($orphanDir, 0777, true);
                $dest = $orphanDir . '/' . basename($file);
                if (!file_exists($dest)) {
                    if (@rename($file, $dest)) {
                        $logs[] = "<div style='color:#d97706'>📦 已归档孤儿: $rel → _orphan/" . basename($ymDir) . "/</div>";
                    } else {
                        $logs[] = "<div style='color:#dc2626'>✗ 归档失败: $rel</div>";
                    }
                } else {
                    @unlink($file);
                    $logs[] = "<div style='color:#6b7280'>🗑 已删除重复孤儿: $rel</div>";
                }
            }
        }
    }
}
if ($orphanCount > 0) {
    $msg = $dryRun ? "预览模式下仅提示" : "已自动归档到 _orphan/ 目录";
    $logs[] = "<div style='color:#dc2626;font-weight:600;margin-top:8px'>⚠ 发现 $orphanCount 个孤儿文件（未被任何文档引用），$msg</div>";
}

// 清理空的 docID 目录（仅迁移模式）
if (!$dryRun && !$rollback) {
    $dirs = glob(__DIR__ . '/data/uploads/*/doc*', GLOB_ONLYDIR);
    foreach ($dirs as $dir) {
        $files = glob($dir . '/*');
        if (empty($files)) {
            @rmdir($dir);
            $logs[] = "<div style='color:#6b7280'>- 清理空目录: " . basename(dirname($dir)) . "/" . basename($dir) . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>SOPHub 附件迁移工具</title>
<style>
  body { font-family: -apple-system, "Microsoft YaHei", sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; color: #111827; }
  h1 { font-size: 22px; margin-bottom: 8px; }
  .desc { color: #6b7280; margin-bottom: 24px; font-size: 14px; line-height: 1.7; }
  .actions { display: flex; gap: 12px; margin-bottom: 24px; }
  .btn { padding: 10px 20px; border-radius: 6px; border: none; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block; }
  .btn-primary { background: #4f46e5; color: #fff; }
  .btn-primary:hover { background: #4338ca; }
  .btn-danger { background: #dc2626; color: #fff; }
  .btn-danger:hover { background: #b91c1c; }
  .btn-default { background: #f3f4f6; color: #374151; }
  .btn-default:hover { background: #e5e7eb; }
  .summary { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px 20px; margin-bottom: 20px; font-size: 14px; }
  .summary .num { font-weight: 600; color: #111827; }
  .log-box { background: #1f2937; color: #e5e7eb; border-radius: 8px; padding: 16px; max-height: 500px; overflow-y: auto; font-family: Consolas, monospace; font-size: 12px; line-height: 1.8; }
  .log-box > div { white-space: pre-wrap; word-break: break-all; }
  .warn { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; color: #92400e; }
</style>
</head>
<body>

<h1>SOPHub 附件迁移工具</h1>
<p class="desc">
  把历史上传的图片从 <code>data/uploads/年月/文件名</code> 迁移到 <code>data/uploads/年月/docID/文件名</code>，
  方便区分每个文档的附件。<br>
  同时更新数据库中所有文档的图片引用路径。<br>
  <strong style="color:#dc2626">⚠ 执行前请备份 data/ 目录！</strong>
</p>

<?php if (!$is_local && !$is_admin): ?>
  <div class="warn">⚠ 仅限本机(127.0.0.1)或已登录管理员访问</div>
<?php else: ?>

<div class="actions">
  <?php if ($action === 'preview'): ?>
    <a href="?action=run" class="btn btn-primary" onclick="return confirm('确认执行迁移？执行前请备份 data/ 目录！')">▶ 执行迁移</a>
  <?php elseif ($action === 'run'): ?>
    <a href="?action=rollback" class="btn btn-danger" onclick="return confirm('确认回滚？会把文件移回旧目录！')">↺ 回滚迁移</a>
  <?php elseif ($action === 'rollback'): ?>
    <a href="migrate_uploads.php" class="btn btn-default">← 返回预览</a>
  <?php endif; ?>
  <a href="index.php" class="btn btn-default">← 返回系统</a>
</div>

<div class="summary">
  <div>操作模式: <strong><?= $dryRun ? '🔍 预览（不执行）' : ($rollback ? '↺ 回滚' : '▶ 执行迁移') ?></strong></div>
  <div>扫描文档: <span class="num"><?= count($docs) ?></span> 个</div>
  <div>文件移动: <span class="num"><?= $totalMoved ?></span> 个 <?= $dryRun ? '(预览)' : '' ?></div>
  <div>文档更新: <span class="num"><?= $totalUpdated ?></span> 个</div>
  <div>跳过: <span class="num"><?= $totalSkipped ?></span> 个</div>
  <?php if (!empty($errors)): ?>
    <div style="color:#dc2626">错误: <?= count($errors) ?> 个</div>
  <?php endif; ?>
</div>

<?php if ($dryRun): ?>
  <div class="warn">🔍 当前是预览模式，没有执行任何修改。确认无误后点击"▶ 执行迁移"。</div>
<?php elseif ($rollback): ?>
  <div class="warn">↺ 当前是回滚模式：文件将从 docID 子目录移回旧的年月目录。</div>
<?php endif; ?>

<div class="log-box">
  <?php foreach ($logs as $log) echo $log, "\n"; ?>
  <?php if (empty($logs)) echo '<div style="color:#9ca3af">没有需要处理的文件</div>'; ?>
</div>

<?php endif; ?>
</body>
</html>
