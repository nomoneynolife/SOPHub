<#
.SYNOPSIS
    下载 TinyMCE 6 离线包到 sop-system/tinymce/ 目录
.DESCRIPTION
    从 jsdelivr CDN 下载 TinyMCE 6.8.4 完整包 + 中文语言包
    部署到内网/无外网环境时使用
#>

$ErrorActionPreference = 'Stop'

# 目标目录：脚本所在目录下的 tinymce/
# 用 $PSScriptRoot（PS3+ 自动变量，比 $MyInvocation 更稳健）
# 兜底：交互执行时 $PSScriptRoot 可能为空，回退到当前工作目录
if ($PSScriptRoot) {
    $scriptDir = $PSScriptRoot
} elseif ($MyInvocation.MyCommand.Path) {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
} else {
    $scriptDir = (Get-Location).Path
}
$destDir = Join-Path $scriptDir 'tinymce'
$jsDir = Join-Path $destDir 'js'

Write-Host "脚本目录: $scriptDir" -ForegroundColor Gray

if (Test-Path $destDir) {
    Write-Host "目录已存在，跳过下载：$destDir" -ForegroundColor Yellow
    Write-Host "如需重新下载，请先删除该目录。"
    exit 0
}

New-Item -ItemType Directory -Force -Path $jsDir | Out-Null

# 下载列表：URL -> 本地相对路径 -> 显示名称
# 注意：TinyMCE 6 已将 paste 插件合并进核心，不再单独发布 plugin.min.js
$files = @(
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/tinymce.min.js';                       Dest = 'tinymce.min.js';                              Name = '核心 tinymce.min.js' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/icons/default/icons.min.js';            Dest = 'icons\default\icons.min.js';                   Name = '图标 icons.min.js' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/models/dom/model.min.js';              Dest = 'models\dom\model.min.js';                     Name = 'DOM 模型 model.min.js' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/themes/silver/theme.min.js';           Dest = 'themes\silver\theme.min.js';                  Name = '主题 silver/theme.min.js' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/plugins/image/plugin.min.js';          Dest = 'plugins\image\plugin.min.js';                 Name = '插件 image (图片插入)' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/plugins/lists/plugin.min.js';          Dest = 'plugins\lists\plugin.min.js';                 Name = '插件 lists (列表)' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/plugins/link/plugin.min.js';           Dest = 'plugins\link\plugin.min.js';                  Name = '插件 link (链接)' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/plugins/table/plugin.min.js';          Dest = 'plugins\table\plugin.min.js';                Name = '插件 table (表格)' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/plugins/autolink/plugin.min.js';       Dest = 'plugins\autolink\plugin.min.js';            Name = '插件 autolink (自动链接)' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/plugins/fullscreen/plugin.min.js';     Dest = 'plugins\fullscreen\plugin.min.js';           Name = '插件 fullscreen (全屏)' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/plugins/searchreplace/plugin.min.js';  Dest = 'plugins\searchreplace\plugin.min.js';       Name = '插件 searchreplace (查找替换)' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/plugins/wordcount/plugin.min.js';      Dest = 'plugins\wordcount\plugin.min.js';           Name = '插件 wordcount (字数统计)' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/skins/ui/oxide/skin.min.css';          Dest = 'skins\ui\oxide\skin.min.css';                Name = '皮肤 skin.min.css' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/skins/ui/oxide/content.min.css';        Dest = 'skins\ui\oxide\content.min.css';             Name = '皮肤 content.min.css (UI)' },
    @{ Url = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/skins/content/default/content.min.css'; Dest = 'skins\content\default\content.min.css';       Name = '皮肤 content.min.css (内容区)' }
)

# 中文语言包（TinyMCE 官方语言包仓库）
$langUrl = 'https://cdn.jsdelivr.net/npm/tinymce-i18n@24.9.30/langs/zh_CN.js'

Write-Host "开始下载 TinyMCE 6.8.4 离线包..." -ForegroundColor Cyan
Write-Host "目标目录: $destDir" -ForegroundColor Gray
Write-Host ""

$success = 0
$failed = 0
$failedFiles = @()

foreach ($f in $files) {
    $destPath = Join-Path $jsDir $f.Dest
    $destDirForFile = Split-Path -Parent $destPath
    if (-not (Test-Path $destDirForFile)) {
        New-Item -ItemType Directory -Force -Path $destDirForFile | Out-Null
    }

    $fileName = Split-Path -Leaf $f.Dest
    $displayName = $f.Name
    Write-Host ("[{0,2}/{1}] {2} ... " -f ($success + $failed + 1), $files.Count, $displayName) -NoNewline
    try {
        Invoke-WebRequest -Uri $f.Url -OutFile $destPath -UseBasicParsing -TimeoutSec 30
        $size = (Get-Item $destPath).Length
        Write-Host "OK ($size bytes)" -ForegroundColor Green
        $success++
    } catch {
        Write-Host "FAILED: $($_.Exception.Message)" -ForegroundColor Red
        $failed++
        $failedFiles += $displayName
    }
}

# 下载中文语言包
$langPath = Join-Path $jsDir 'langs\zh_CN.js'
$langDirForFile = Split-Path -Parent $langPath
if (-not (Test-Path $langDirForFile)) {
    New-Item -ItemType Directory -Force -Path $langDirForFile | Out-Null
}

Write-Host ("[{0,2}/{1}] 中文语言包 zh_CN.js ... " -f ($success + $failed + 1), ($files.Count + 1)) -NoNewline
try {
    Invoke-WebRequest -Uri $langUrl -OutFile $langPath -UseBasicParsing -TimeoutSec 30
    $size = (Get-Item $langPath).Length
    Write-Host "OK ($size bytes)" -ForegroundColor Green
    $success++
} catch {
    Write-Host "FAILED: $($_.Exception.Message)" -ForegroundColor Red
    $failed++
    $failedFiles += '中文语言包 zh_CN.js'
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
# TinyMCE 离线包说明

## 目录结构
```
tinymce/
└── js/
    ├── tinymce.min.js                 主程序
    ├── icons/default/icons.min.js     图标
    ├── models/dom/model.min.js        DOM 模型
    ├── themes/silver/theme.min.js     主题（必需）
    ├── langs/zh_CN.js                 中文语言
    ├── plugins/                       插件目录
    │   ├── image/plugin.min.js
    │   ├── lists/plugin.min.js
    │   ├── link/plugin.min.js
    │   ├── table/plugin.min.js
    │   ├── autolink/plugin.min.js
    │   ├── fullscreen/plugin.min.js
    │   ├── searchreplace/plugin.min.js
    │   └── wordcount/plugin.min.js
    └── skins/                        皮肤样式
        ├── ui/oxide/skin.min.css
        ├── ui/oxide/content.min.css
        └── content/default/content.min.css
```

## 使用方式

index.php 已经配置好使用本地路径：
```html
<script src="tinymce/js/tinymce.min.js"></script>
```

如果需要换回 CDN 版本，修改 index.php 第 6 行：
```html
<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.4/tinymce.min.js"></script>
```

## 更新 TinyMCE

修改 download-tinymce.ps1 中的版本号（如 6.8.4 -> 6.8.5），
删除 tinymce/ 目录后重新运行脚本。
'@

$readmePath = Join-Path $destDir 'README.md'
Set-Content -Path $readmePath -Value $readme -Encoding UTF8

Write-Host "说明文件已生成: $readmePath" -ForegroundColor Gray
