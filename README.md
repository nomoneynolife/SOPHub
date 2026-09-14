# SOPHub · SOP 文档中心

一个轻量级的 SOP（标准作业程序）文档管理系统，基于 PHP + SQLite，开箱即用。

## 特性

- **无需数据库**：使用 SQLite，零配置部署
- **树状导航**：左侧层级目录树，右侧内容编辑/查看
- **富文本编辑**：基于 TinyMCE 6，支持从 Word 直接复制带图内容
- **图片自动上传**：剪贴板粘贴图片自动上传到服务器，不再丢失
- **权限分级**：游客只读查看，登录用户可编辑
- **离线部署**：TinyMCE 资源可下载到本地，内网也能用
- **中文界面**：完整本地化

## 环境要求

- PHP 7.4+（推荐 8.x）
- PHP 扩展：`pdo_sqlite`、`fileinfo`、`gd` 或 `imagick`
- Web 服务器：Nginx / Apache / IIS（任意一种）

## 快速开始

### 1. 部署到服务器

将整个目录复制到 Web 根目录，例如 `D:\phpstudy_pro\WWW\sop-system`。

### 2. 修改配置

编辑 `config.php`，修改访问密码：

```php
const ACCESS_PASSWORD = 'your-strong-password';
```

### 3. 配置 Web 服务器

以 Nginx 为例：

```nginx
server {
    listen 8080;
    server_name localhost;
    root D:/phpstudy_pro/WWW/sop-system;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    client_max_body_size 100m;
}
```

### 4. 下载 TinyMCE 离线包（可选）

如果部署在无外网环境，运行 PowerShell 脚本下载资源：

```powershell
.\download-tinymce.ps1
```

### 5. 访问

浏览器打开 `http://localhost:8080`，输入密码登录即可开始使用。

## 使用说明

### 游客模式

- 默认进入游客模式，可查看所有文档但不可编辑
- 点击顶部「登录编辑」输入密码后切换到编辑模式

### 创建文档

- 点击左上角「新建文件夹」创建分类目录
- 点击「新建文档」创建 SOP 页面
- 鼠标悬停在节点上显示操作按钮：
  - ✎ 重命名
  - × 删除
  - + 在该文件夹下新建子项（仅文件夹显示）

### 从 Word 复制内容

1. 在 Word 中选中带图片的内容，Ctrl+C 复制
2. 在 SOPHub 编辑器中 Ctrl+V 粘贴
3. 图片会自动上传到 `data/uploads/YYYYMM/` 目录
4. 文字格式保留，图片插入正文

### 数据存储

- 文档数据：`data/sop.db`（SQLite）
- 上传图片：`data/uploads/YYYYMM/`
- 备份：直接复制 `data/` 目录即可

## 目录结构

```
sop-system/
├── config.php              配置文件（密码、路径）
├── api.php                 后端 API（登录、CRUD、图片上传）
├── index.php               前端主页面
├── download-tinymce.ps1    TinyMCE 离线包下载脚本
├── LICENSE                 MIT 协议
├── README.md               本文件
├── tinymce/                TinyMCE 离线资源（运行脚本后生成）
│   └── js/
└── data/                   运行时生成
    ├── sop.db
    └── uploads/
```

## 安全注意事项

- **部署后立即修改默认密码**：编辑 `config.php` 中的 `ACCESS_PASSWORD`
- **限制写入权限**：仅给 `data/` 目录写权限，其他目录只读
- **启用 HTTPS**：生产环境建议配置 SSL 证书
- **定期备份**：备份 `data/` 目录到安全位置
- **上传限制**：默认限制单文件 10MB，可在 `config.php` 中调整

## 常见问题

**Q：图片上传失败怎么办？**
A：检查 `data/uploads/` 目录是否有写权限，PHP 是否启用 `fileinfo` 扩展。

**Q：TinyMCE 加载失败？**
A：先运行 `download-tinymce.ps1` 下载离线包；如果用 CDN 模式，确保服务器能访问 `cdn.jsdelivr.net`。

**Q：游客看不到任何文档？**
A：数据库为空时显示「暂无文档」，登录后创建第一个文档即可。

**Q：如何迁移到其他服务器？**
A：复制整个 `sop-system/` 目录到新服务器，配置 Web 服务器指向该目录即可。

## 开源协议

本项目基于 [MIT License](LICENSE) 开源，可自由使用、修改、分发。

## 致谢

- [TinyMCE](https://www.tiny.cloud/) - 富文本编辑器
- [jsdelivr](https://www.jsdelivr.com/) - CDN 服务

---

© 2026 SOPHub · by_sw
