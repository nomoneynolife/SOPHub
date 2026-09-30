<#
.SYNOPSIS
    下载 wangEditor-next v6 离线包到 sop-system/wangeditor/ 目录
.DESCRIPTION
    从 jsdelivr CDN 下载 wangEditor-next 编辑器核心 JS 和 CSS
    部署到内网/无外网环境时使用
#>

$ErrorActionPreference = 'Stop'

if ($PSScriptRoot) {
    $scriptDir = $PSScriptRoot
} elseif ($MyInvocation.MyCommand.Path) {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
} else {
    $scriptDir = (Get-Location).Path
}
$destDir = Join-Path $scriptDir 'wangeditor'
$distDir = Join-Path $destDir 'dist'
$cssDir = Join-Path $distDir 'css'

Write-Host "脚本目录: $scriptDir" -ForegroundColor Gray

if (Test-Path $destDir) {
    Write-Host "目录已存在，跳过下载：$destDir" -ForegroundColor Yellow
    Write-Host "如需重新下载，请先删除该目录。"
    exit 0
}

New-Item -ItemType Directory -Force -Path $cssDir | Out-Null

# 下载列表：URL -> 本地相对路径 -> 显示名称
$files = @(
    @{ Url = 'https://cdn.jsdelivr.net/npm/@wangeditor-next/editor@latest/dist/index.js';      Dest = 'dist\index.js';      Name = '核心 index.js' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/@wangeditor-next/editor@latest/dist/index.min.js';  Dest = 'dist\index.min.js';  Name = '核心 index.min.js' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/@wangeditor-next/editor@latest/dist/css/style.css'; Dest = 'dist\css\style.css'; Name = '样式 style.css' }
)

Write-Host "开始下载 wangEditor-next v6 离线包..." -ForegroundColor Cyan
Write-Host "目标目录: $destDir" -ForegroundColor Gray
Write-Host ""

$success = 0
$failed = 0
$failedFiles = @()

foreach ($f in $files) {
    $destPath = Join-Path $destDir $f.Dest
    $destDirForFile = Split-Path -Parent $destPath
    if (-not (Test-Path $destDirForFile)) {
        New-Item -ItemType Directory -Force -Path $destDirForFile | Out-Null
    }

    $fileName = Split-Path -Leaf $f.Dest
    $displayName = $f.Name
    Write-Host ("[{0,2}/{1}] {2} ... " -f ($success + $failed + 1), $files.Count, $displayName) -NoNewline
    try {
        Invoke-WebRequest -Uri $f.Url -OutFile $destPath -UseBasicParsing -TimeoutSec 60
        $size = (Get-Item $destPath).Length
        Write-Host "OK ($size bytes)" -ForegroundColor Green
        $success++
    } catch {
        Write-Host "FAILED: $($_.Exception.Message)" -ForegroundColor Red
        $failed++
        $failedFiles += $displayName
    }
}

Write-Host ""
Write-Host "================================" -ForegroundColor Cyan
Write-Host "完成！" -ForegroundColor Cyan
Write-Host "成功: $success 个文件" -ForegroundColor Green
if ($failed -gt 0) {
    Write-Host "失败: $failed 个文件" -ForegroundColor Red
    Write-Host ""
    Write-Host "失败文件列表:" -ForegroundColor Yellow
    $failedFiles | ForEach-Object { Write-Host "  - $_" -ForegroundColor Yellow }
    Write-Host ""
    Write-Host "请检查网络后重试，或手动从 jsdelivr 下载。" -ForegroundColor Gray
    exit 1
}

# 生成说明文件
$readme = @'
# wangEditor-next v6 离线包说明

## 目录结构
```
wangeditor/
└── dist/
    ├── index.js            主程序（开发版，带日志）
    ├── index.min.js        主程序（压缩版，生产用）
    └── css/
        └── style.css       编辑器样式
```

## 使用方式

index.php 已经配置好使用本地路径：
```html
<link rel="stylesheet" href="wangeditor/dist/css/style.css">
<script src="wangeditor/dist/index.js"></script>
```

如果需要换回 CDN 版本，修改 index.php 中的加载逻辑。

## 更新 wangEditor

删除 wangeditor/ 目录后重新运行 download-wangeditor.ps1 即可。
'@

$readmePath = Join-Path $destDir 'README.md'
Set-Content -Path $readmePath -Value $readme -Encoding UTF8

Write-Host "说明文件已生成: $readmePath" -ForegroundColor Gray
