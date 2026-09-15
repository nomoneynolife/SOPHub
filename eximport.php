<?php
/**
 * 批量下载外链图片到本地，并更新数据库中的引用
 *
 * 使用方法:
 *   浏览器访问  eximport.php?action=preview   预览（不下载）
 *   浏览器访问  eximport.php?action=run       执行下载
 */

require __DIR__ . '/config.php';

session_start();
header('Content-Type: text/html; charset=utf-8');
$pdo = new PDO('sqlite:' . DB_FILE);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_GET['action'] ?? 'preview';
$dryRun = ($action === 'preview');

$logs = [];
$totalDownloaded = 0;
$totalUpdated = 0;
$totalFailed = 0;
$totalSkipped = 0;

// 支持的图片扩展名（用于从 URL 推断）
$exts = [
    'image/jpeg' => 'jpg',
    'image/jpg'  => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    'image/bmp'  => 'bmp',
];

// 外链图片的正则
$urlPatterns = [
    '#https?://[^\s"\'<>]+\.(?:jpg|jpeg|png|gif|webp|bmp)(?:\?[^\s"\'<>]*)?#i',
    '#src="(https?://[^"]+)"#i',
];

$stmt = $pdo->query("SELECT id, title, content FROM docs WHERE deleted = 0 AND content LIKE '%http%'");
$docs = $stmt->fetchAll();

foreach ($docs as $doc) {
    $docId = (int)$doc['id'];
    $content = $doc['content'];
    $original = $content;

    // 找出所有 http(s) 图片链接
    preg_match_all('#https?://[^\s"\'<>]+?(?:jpg|jpeg|png|gif|webp|bmp)(?:\?[^\s"\'<>]*)?#i', $content, $matches);
    $urls = array_unique($matches[0] ?? []);

    if (empty($urls)) {
        $logs[] = "<div style='color:#6b7280'>- 文档「{$doc['title']}」(id=$docId) 无外链图片</div>";
        continue;
    }

    foreach ($urls as $url) {
        // 跳过已经是本地的
        if (strpos($url, 'data/uploads') !== false || strpos($url, 'uploads/') !== false) continue;

        // 跳过 data: URI
        if (strpos($url, 'data:') === 0) continue;

        // 推断扩展名
        $ext = 'jpg';
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)/i', $url, $em)) {
            $ext = strtolower($em[1]);
            if ($ext === 'jpeg') $ext = 'jpg';
        }

        // 目标路径: 年月/docID/文件名
        $ym = date('Ym');
        $fileName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $subDir = $ym . '/doc' . $docId;
        $destDir = UPLOAD_DIR . $subDir;
        $localUrl = 'data/uploads/' . $subDir . '/' . $fileName;
        $destPath = __DIR__ . '/' . $localUrl;

        if ($dryRun) {
            $logs[] = "<div style='color:#d97706'>(预览) 将下载: $url → $localUrl</div>";
            $content = str_replace($url, $localUrl, $content);
            $totalDownloaded++;
        } else {
            // 下载
            @mkdir($destDir, 0777, true);
            $opts = [
                'http' => [
                    'timeout' => 15,
                    'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
                ],
            ];
            $ctx = stream_context_create($opts);
            $data = @file_get_contents($url, false, $ctx);

            if ($data === false || $data === '') {
                $logs[] = "<div style='color:#dc2626'>✗ 下载失败: $url</div>";
                $totalFailed++;
                continue;
            }

            // MIME 校验
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($data);
            if (!in_array($mime, array_keys($exts), true)) {
                $logs[] = "<div style='color:#dc2626'>✗ 不支持的 MIME: $mime ($url)</div>";
                $totalFailed++;
                continue;
            }
            $ext = $exts[$mime];
            // 扩展名可能被 MIME 修正
            $fileName = preg_replace('/\.\w+$/', '.' . $ext, $fileName);
            $localUrl = 'data/uploads/' . $subDir . '/' . $fileName;
            $destPath = __DIR__ . '/' . $localUrl;

            if (@file_put_contents($destPath, $data)) {
                $logs[] = "<div style='color:#059669'>✓ 已下载: $url → $localUrl (" . strlen($data) . " bytes)</div>";
                $content = str_replace($url, $localUrl, $content);
                $totalDownloaded++;
            } else {
                $logs[] = "<div style='color:#dc2626'>✗ 保存失败: $url → $destPath</div>";
                $totalFailed++;
            }
        }
    }

    // 更新数据库
    if (!$dryRun && $content !== $original) {
        $stmt = $pdo->prepare("UPDATE docs SET content = ? WHERE id = ?");
        $stmt->execute([$content, $docId]);
        $totalUpdated++;
        $logs[] = "<div style='color:#7c3aed'>★ 已更新文档: {$doc['title']} (id=$docId)</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>SOPHub 外链图片批量下载</title>
<style>
  body { font-family: -apple-system, "Microsoft YaHei", sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; color: #111827; }
  h1 { font-size: 22px; margin-bottom: 8px; }
  .desc { color: #6b7280; margin-bottom: 24px; font-size: 14px; line-height: 1.7; }
  .actions { display: flex; gap: 12px; margin-bottom: 24px; }
  .btn { padding: 10px 20px; border-radius: 6px; border: none; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block; }
  .btn-primary { background: #4f46e5; color: #fff; }
  .btn-primary:hover { background: #4338ca; }
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

<h1>🔗 外链图片批量下载</h1>
<p class="desc">
  扫描所有文档中的外链图片（腾讯云、图床等），下载到本地 <code>data/uploads/年月/docID/</code>，并更新数据库引用。<br>
  <strong style="color:#dc2626">⚠ 执行前请备份 data/ 目录和数据库！</strong>
</p>

<div class="actions">
  <?php if ($dryRun): ?>
    <a href="?action=run" class="btn btn-primary" onclick="return confirm('确认下载所有外链图片？执行前请备份！')">▶ 执行下载</a>
  <?php else: ?>
    <a href="eximport.php" class="btn btn-default">← 返回预览</a>
  <?php endif; ?>
  <a href="index.php" class="btn btn-default">← 返回系统</a>
</div>

<div class="summary">
  <div>操作模式: <strong><?= $dryRun ? '🔍 预览（不执行）' : '▶ 执行下载' ?></strong></div>
  <div>扫描文档: <span class="num"><?= count($docs) ?></span> 个（含 http 内容）</div>
  <div>将下载: <span class="num"><?= $totalDownloaded ?></span> 个</div>
  <div>已更新文档: <span class="num"><?= $totalUpdated ?></span> 个</div>
  <div>失败: <span class="num" style="color:#dc2626"><?= $totalFailed ?></span> 个</div>
</div>

<?php if ($dryRun): ?>
  <div class="warn">🔍 当前是预览模式，没有执行任何下载。确认无误后点击"▶ 执行下载"。</div>
<?php endif; ?>

<div class="log-box">
  <?php foreach ($logs as $log) echo $log, "\n"; ?>
  <?php if (empty($logs)) echo '<div style="color:#9ca3af">没有需要下载的外链图片</div>'; ?>
</div>

</body>
</html>
