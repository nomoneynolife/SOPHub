<?php
/**
 * SOP 系统配置文件
 * 部署后只需修改 ACCESS_PASSWORD 即可
 */

// 访问密码（请改成你自己的强密码）
const ACCESS_PASSWORD = 'change-me-to-a-strong-password';

// 数据存储路径（无需修改，自动创建）
define('DATA_DIR', __DIR__ . '/data');
define('DB_FILE', DATA_DIR . '/sop.db');
define('UPLOAD_DIR', DATA_DIR . '/uploads/');

// 上传限制
const MAX_UPLOAD_SIZE = 20 * 1024 * 1024; // 20MB
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];

// 自动创建目录
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0777, true);
}
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}
