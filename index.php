<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SOPHub · SOP 文档中心</title>
<?php
/**
 * TinyMCE 加载策略：本地优先，CDN 兜底
 * 部署时运行 download-tinymce.ps1 下载离线包到 tinymce/ 目录
 * 内网/无外网环境使用本地；外网可自动回退到 CDN
 */
$tinymceLocalPath = __DIR__ . '/tinymce/js/tinymce.min.js';
$tinymceUrl = file_exists($tinymceLocalPath)
    ? 'tinymce/js/tinymce.min.js'
    : 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4/tinymce.min.js';
?>
<script src="<?= htmlspecialchars($tinymceUrl) ?>"></script>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { height: 100%; font-family: -apple-system, "Microsoft YaHei", sans-serif; font-size: 14px; }
  body { background: #f5f5f5; color: #333; }

  /* 主应用：默认显示，游客也能看 */
  #app { display: flex; height: 100vh; flex-direction: column; }

  /* 页脚 */
  .app-footer {
    height: 28px; flex-shrink: 0; background: #fff; border-top: 1px solid #e5e7eb;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; color: #9ca3af;
  }
  .app-footer a { color: #9ca3af; text-decoration: none; }
  .app-footer a:hover { color: #4f46e5; }

  /* 顶栏 */
  .topbar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 16px; background: #fff; border-bottom: 1px solid #e5e7eb;
    height: 48px; flex-shrink: 0;
  }
  .topbar h1 { font-size: 16px; font-weight: 500; color: #1f2937; }
  .topbar h1 .subtitle { color: #9ca3af; font-size: 12px; margin-left: 8px; font-weight: normal; }
  .topbar .actions { display: flex; gap: 8px; align-items: center; }
  .btn {
    padding: 6px 14px; border: 1px solid #d1d5db; background: #fff;
    border-radius: 4px; cursor: pointer; font-size: 13px; color: #374151;
    transition: all .15s;
  }
  .btn:hover { background: #f9fafb; border-color: #9ca3af; }
  .btn-primary { background: #4f46e5; color: #fff; border-color: #4f46e5; }
  .btn-primary:hover { background: #4338ca; }
  .btn-danger { color: #dc2626; border-color: #fecaca; }
  .btn-danger:hover { background: #fef2f2; }
  /* 游客态隐藏的元素 */
  .auth-only { display: none; }
  body.authed .auth-only { display: inline-flex; align-items: center; }
  .guest-only { display: inline-flex; align-items: center; }
  body.authed .guest-only { display: none; }
  /* 编辑模式才显示 */
  .editing-only { display: none !important; }
  body.editing .editing-only { display: inline-flex !important; align-items: center; }
  /* 非编辑模式才显示（已登录但未编辑） */
  body.editing .not-editing { display: none !important; }

  .body { flex: 1; display: flex; min-height: 0; }

  /* 侧边栏 */
  .sidebar {
    width: 280px; min-width: 160px; max-width: 600px;
    background: #fff; border-right: 1px solid #e5e7eb;
    display: flex; flex-direction: column; flex-shrink: 0;
    transition: width .1s;  /* 平滑过渡 */
  }
  /* 拖拽分割线 */
  .sidebar-resizer {
    width: 6px; flex-shrink: 0; cursor: col-resize;
    background: transparent; position: relative; z-index: 10;
    transition: background .15s;
  }
  .sidebar-resizer::after {
    content: ''; position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 3px; height: 32px; border-radius: 2px;
    background: #d1d5db; transition: background .15s, height .15s;
  }
  .sidebar-resizer:hover::after,
  .sidebar-resizer.dragging::after {
    background: #4f46e5; height: 48px;
  }
  body.resizing { cursor: col-resize; user-select: none !important; }
  .sidebar-toolbar {
    padding: 8px; border-bottom: 1px solid #f3f4f6; display: flex; gap: 6px; flex-wrap: wrap;
  }
  .sidebar-toolbar .btn { flex: 1; min-width: 0; font-size: 12px; padding: 4px 8px; }
  .sidebar-footer {
    padding: 8px; border-top: 1px solid #f3f4f6; flex-shrink: 0;
  }
  .sidebar-footer .btn-trash-toggle {
    width: 100%; padding: 8px; background: #f9fafb; border: 1px solid #e5e7eb;
    border-radius: 6px; color: #6b7280; font-size: 13px; cursor: pointer;
    transition: all .15s;
  }
  .sidebar-footer .btn-trash-toggle:hover { background: #f3f4f6; color: #374151; }
  .sidebar-footer .btn-trash-toggle.active { background: #fef2f2; border-color: #fecaca; color: #dc2626; }
  .tree {
    flex: 1; overflow-y: auto; padding: 4px 0;
  }
  .tree-node {
    display: flex; align-items: center; padding: 4px 8px; cursor: pointer;
    border-radius: 4px; user-select: none;
  }
  .tree-node:hover { background: #f3f4f6; }
  .tree-node.active { background: #e0e7ff; color: #4338ca; }
  .tree-node.folder-selected { background: #fef3c7; color: #92400e; box-shadow: inset 3px 0 0 #f59e0b; }
  .tree-node .icon { width: 16px; margin-right: 6px; color: #6b7280; }
  .tree-node .title {
    flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .tree-node .tree-btn {
    opacity: 0; background: none; border: none;
    cursor: pointer; padding: 0 4px; font-size: 12px;
    margin-left: 2px;
  }
  .tree-node .tree-btn.edit { color: #4f46e5; }
  .tree-node .tree-btn.danger { color: #dc2626; }
  .tree-node .tree-btn.expand-toggle { opacity: 1; font-size: 11px; } /* 默认展开按钮常驻显示 */
  .tree-node .tree-btn.expand-toggle.on { opacity: 1; }
  .tree-node:hover .tree-btn { opacity: 1; }
  body:not(.authed) .tree-node .tree-btn { display: none; }
  .tree-children { margin-left: 18px; display: block; }
  .tree-node-wrapper { display: block; }

  /* 拖拽指示器 */
  .tree-node.dragging { opacity: 0.4; }
  .tree-node.drag-over-before { box-shadow: inset 0 2px 0 0 #4f46e5; background: #e0e7ff; }
  .tree-node.drag-over-after  { box-shadow: inset 0 -2px 0 0 #4f46e5; background: #e0e7ff; }
  .tree-node.drag-over-in     { box-shadow: inset 0 0 0 2px #10b981; background: #d1fae5; }

  /* 主区域 */
  .main {
    flex: 1; display: flex; flex-direction: column; min-width: 0;
    background: #fff;
  }
  .editor-header {
    padding: 8px 16px; border-bottom: 1px solid #e5e7eb; display: flex; gap: 12px;
    align-items: center; height: 48px; flex-shrink: 0;
  }
  .editor-header input {
    flex: 1; border: 1px solid #ddd; padding: 6px 10px; border-radius: 4px;
    font-size: 14px;
  }
  .editor-header input:focus { outline: none; border-color: #4f46e5; }
  .editor-header .btn-icon {
    flex-shrink: 0; padding: 6px 10px; font-size: 16px; line-height: 1;
    background: none; border: 1px solid #ddd; border-radius: 4px; cursor: pointer;
  }
  .editor-header .btn-icon:hover { background: #f3f4f6; border-color: #4f46e5; }
  /* 游客态下标题输入框只读 */
  body:not(.authed) .editor-header input { background: #f9fafb; color: #374151; cursor: default; }
  .editor-body { flex: 1; overflow: hidden; position: relative; min-height: 0; }
  /* 编辑区域容器：flex 纵向排列，撑满右侧主区域 */
  #editor-area { display: flex; flex-direction: column; flex: 1; min-width: 0; min-height: 0; overflow: hidden; }
  .empty-state {
    display: flex; align-items: center; justify-content: center;
    height: 100%; color: #9ca3af; font-size: 14px; flex-direction: column; gap: 8px;
  }
  .save-status { font-size: 12px; color: #6b7280; margin-right: 8px; }

  /* 阅读视图（游客用） */
  #read-view {
    position: absolute; inset: 0; overflow-y: auto; padding: 24px 32px;
    background: #fff; display: none;
  }
  #read-view .read-content { line-height: 1.7; color: #374151; }
  #read-view .read-content img { max-width: 100%; height: auto; }
  #read-view .read-content table { border-collapse: collapse; width: 100%; margin: 12px 0; }
  #read-view .read-content th, #read-view .read-content td { border: 1px solid #d1d5db; padding: 6px 10px; }
  #read-view .read-content code { background: #f3f4f6; padding: 2px 4px; border-radius: 3px; font-size: 13px; }
  #read-view .read-content pre { background: #f3f4f6; padding: 12px; border-radius: 4px; overflow-x: auto; margin: 12px 0; }
  body:not(.editing) #read-view { display: block; }
  body.editing #read-view { display: none; }
  body:not(.editing) #editor { display: none; }
  body.editing #editor { display: block; }

  /* TinyMCE 容器 */
  #editor { width: 100%; height: 100%; }
  .tox-tinymce { border: none !important; height: calc(100vh - 124px) !important; }

  /* 登录弹窗 */
  #login-modal {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5);
    z-index: 1000; align-items: center; justify-content: center;
  }
  #login-modal.show { display: flex; }
  #login-box {
    background: #fff; padding: 32px 40px; border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0,0,0,.2); width: 360px;
  }
  #login-box h2 { text-align: center; margin-bottom: 24px; color: #333; font-weight: 500; }
  #login-box input {
    width: 100%; padding: 10px 12px; border: 1px solid #ddd;
    border-radius: 4px; font-size: 14px; margin-bottom: 16px;
  }
  #login-box button {
    width: 100%; padding: 10px; background: #4f46e5; color: #fff;
    border: none; border-radius: 4px; font-size: 14px; cursor: pointer;
    transition: background .15s;
  }
  #login-box button:hover { background: #4338ca; }
  #login-err { color: #dc2626; font-size: 12px; min-height: 18px; margin-bottom: 8px; }
  #login-box .close-btn {
    position: absolute; top: 12px; right: 16px;
    background: none; border: none; font-size: 20px; color: #9ca3af; cursor: pointer;
    width: auto; padding: 0;
  }
  #login-box .close-btn:hover { color: #4b5563; background: none; }

  /* ========== 现代化弹窗 ========== */
  #modal-overlay {
    display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, .45);
    z-index: 2000; align-items: center; justify-content: center;
    backdrop-filter: blur(2px);
    animation: modalFadeIn .18s ease-out;
  }
  #modal-overlay.show { display: flex; }
  @keyframes modalFadeIn { from { opacity: 0; } to { opacity: 1; } }
  @keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(-10px) scale(.96); }
    to   { opacity: 1; transform: translateY(0)    scale(1); }
  }
  #modal-box {
    background: #fff; border-radius: 12px; padding: 28px 32px 24px;
    width: 400px; max-width: calc(100vw - 32px);
    box-shadow: 0 20px 60px rgba(15, 23, 42, .25), 0 0 0 1px rgba(15, 23, 42, .04);
    animation: modalSlideIn .22s cubic-bezier(.16, 1, .3, 1);
    text-align: center;
  }
  #modal-icon {
    width: 56px; height: 56px; margin: 0 auto 14px;
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    font-size: 26px;
  }
  #modal-icon.danger  { background: #fef2f2; color: #dc2626; }
  #modal-icon.warning { background: #fffbeb; color: #d97706; }
  #modal-icon.info    { background: #eff6ff; color: #2563eb; }
  #modal-icon.success { background: #ecfdf5; color: #10b981; }
  #modal-title {
    font-size: 17px; font-weight: 600; color: #111827; margin-bottom: 6px;
  }
  #modal-message {
    font-size: 14px; color: #6b7280; line-height: 1.6; margin-bottom: 20px;
    white-space: pre-wrap;
  }
  #modal-input-wrap { display: none; margin-bottom: 20px; }
  #modal-input-wrap.show { display: block; }
  #modal-input {
    width: 100%; padding: 9px 12px; border: 1px solid #d1d5db;
    border-radius: 6px; font-size: 14px; outline: none;
    transition: border-color .15s, box-shadow .15s;
  }
  #modal-input:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, .15);
  }
  #modal-actions { display: flex; gap: 10px; justify-content: center; }
  #modal-actions button {
    min-width: 96px; padding: 9px 18px; border-radius: 6px;
    font-size: 14px; font-weight: 500; cursor: pointer;
    border: 1px solid transparent; transition: all .15s;
  }
  #modal-actions .btn-cancel {
    background: #fff; color: #374151; border-color: #d1d5db;
  }
  #modal-actions .btn-cancel:hover { background: #f9fafb; border-color: #9ca3af; }
  #modal-actions .btn-danger {
    background: #dc2626; color: #fff;
  }
  #modal-actions .btn-danger:hover { background: #b91c1c; }
  #modal-actions .btn-primary {
    background: #4f46e5; color: #fff;
  }
  #modal-actions .btn-primary:hover { background: #4338ca; }
</style>
</head>
<body>

<!-- 主应用（默认显示，游客也能浏览） -->
<div id="app">
  <div class="topbar">
    <h1>
      SOPHub <span class="subtitle">· SOP 文档中心</span>
      <span id="user-status" class="subtitle">预览模式 · 仅查看</span>
    </h1>
    <div class="actions">
      <span id="save-status" class="save-status auth-only"></span>
      <button class="btn btn-primary editing-only" onclick="saveDoc()" title="保存 (Ctrl+S)">保存</button>
      <button id="login-btn" class="btn btn-primary guest-only" onclick="showLoginModal()">登录</button>
      <button id="logout-btn" class="btn auth-only" onclick="doLogout()">退出登录</button>
    </div>
  </div>
  <div class="body">
    <aside class="sidebar">
      <div class="sidebar-toolbar">
        <button class="btn auth-only" onclick="createDocInRootFolder()" title="在选中文件夹或根目录新建文件夹">📁 新建文件夹</button>
        <button class="btn auth-only" onclick="createDocInRoot()" title="在选中文件夹或根目录新建文档">📝 新建文档</button>
        <button class="btn" onclick="loadTree()" title="刷新">🔄 刷新</button>
      </div>
      <div id="tree" class="tree"></div>
      <div id="trash-tree" class="tree" style="display:none;"></div>
      <div class="sidebar-footer">
        <button class="btn-trash-toggle auth-only" onclick="toggleTrash()" title="查看回收站">🗑 回收站</button>
      </div>
    </aside>
    <div id="sidebar-resizer" class="sidebar-resizer" title="拖动调整侧边栏宽度"></div>
    <main class="main">
      <div id="editor-area" style="display:none;">
        <div class="editor-header">
          <input type="text" id="doc-title" placeholder="文档标题" readonly>
          <button id="btn-edit" class="btn-icon auth-only not-editing" title="进入编辑模式" onclick="enterEditMode()">✎</button>
          <button id="btn-exit-edit" class="btn-icon editing-only" title="退出编辑模式" onclick="exitEditMode()">📖</button>
          <button id="btn-copy-link" class="btn-icon" title="复制此文档的链接" onclick="copyCurrentLink()">🔗</button>
        </div>
        <div class="editor-body">
          <!-- 阅读视图（游客用） -->
          <div id="read-view">
            <div class="read-content" id="read-content"></div>
          </div>
          <!-- 编辑器（登录用户用） -->
          <textarea id="editor"></textarea>
        </div>
      </div>
      <div id="empty-state" class="empty-state">
        <div style="font-size: 32px;">📋</div>
        <div>请从左侧选择一个文档查看</div>
      </div>
    </main>
  </div>
  <footer class="app-footer">
    <span id="footer-text">© <?= date('Y') ?> <a href="https://github.com/nomoneynolife/SOPHub" target="_blank" rel="noopener">SOPHub</a> · by_sw</span>
  </footer>
</div>

<!-- 登录弹窗（按需弹出） -->
<div id="login-modal">
  <div id="login-box" style="position: relative;">
    <button class="close-btn" onclick="hideLoginModal()">×</button>
    <h2>登录编辑</h2>
    <div id="login-err"></div>
    <input type="password" id="pwd" placeholder="请输入访问密码" autofocus>
    <button id="login-submit" onclick="doLogin()">登录</button>
  </div>
</div>

<!-- 现代化弹窗容器（confirm / alert / prompt 共用） -->
<div id="modal-overlay">
  <div id="modal-box">
    <div id="modal-icon"></div>
    <div id="modal-title"></div>
    <div id="modal-message"></div>
    <div id="modal-input-wrap"><input type="text" id="modal-input"></div>
    <div id="modal-actions"></div>
  </div>
</div>

<script>
/* ========== 调试开关 ========== */
const DEBUG = false;  // 生产环境设为 false，调试时改为 true
const _log = (...a) => { if (DEBUG) console.log('[SOPHub]', ...a); };
const _warn = (...a) => { if (DEBUG) console.warn('[SOPHub]', ...a); };

/* ========== 全局状态 ========== */
let isLoggedIn = false;
let isEditing = false;   // 是否进入编辑模式（登录后默认 false，需点编辑按钮才 true）
let currentDocId = null;
let selectedFolderId = null;  // 当前选中的文件夹 ID（用于顶栏新建按钮的父级）
let expandedFolders = new Set();  // 当前会话展开的文件夹 ID（避免 loadTree 后丢失展开状态）
let draggedNode = null;  // 拖拽中的节点
let currentDocTitle = '';
let editor = null;
let treeData = [];

/* ========== 工具函数 ========== */
async function api(action, params = {}, method = 'POST') {
  const opts = { method, credentials: 'same-origin' };
  if (method === 'POST') {
    const formData = new FormData();
    for (const k in params) formData.append(k, params[k]);
    opts.body = formData;
  } else {
    const q = new URLSearchParams(params).toString();
    const url = `api.php?action=${action}${q ? '&' + q : ''}`;
    const res = await fetch(url, opts);
    return res.json();
  }
  const res = await fetch(`api.php?action=${action}`, opts);
  return res.json();
}

/* ========== 现代化弹窗（替代原生 alert/confirm/prompt） ========== */
// type: 'danger' | 'warning' | 'info' | 'success'
// 返回 Promise：confirm/prompt → true/false（prompt 失败返回 false，成功返回字符串）
//              alert → 无返回
function showModal({ title = '', message = '', type = 'info', input = null, inputDefault = '', confirmText = '确定', confirmClass = 'btn-primary', cancelText = '取消' }) {
  return new Promise(resolve => {
    const overlay = document.getElementById('modal-overlay');
    const iconEl = document.getElementById('modal-icon');
    const titleEl = document.getElementById('modal-title');
    const msgEl = document.getElementById('modal-message');
    const inputWrap = document.getElementById('modal-input-wrap');
    const inputEl = document.getElementById('modal-input');
    const actions = document.getElementById('modal-actions');

    const icons = { danger: '⚠', warning: '⚠', info: 'ℹ', success: '✓' };
    iconEl.textContent = icons[type] || icons.info;
    iconEl.className = type;
    titleEl.textContent = title;
    msgEl.textContent = message;

    if (input) {
      inputWrap.classList.add('show');
      inputEl.value = inputDefault;
      inputEl.placeholder = input;
      setTimeout(() => inputEl.focus(), 50);
    } else {
      inputWrap.classList.remove('show');
    }

    actions.innerHTML = '';

    // 取消按钮（confirm/prompt 才显示）
    if (input !== null || confirmText && cancelText) {
      const cancelBtn = document.createElement('button');
      cancelBtn.className = 'btn-cancel';
      cancelBtn.textContent = cancelText;
      cancelBtn.onclick = () => { close(); resolve(input !== null ? false : false); };
      actions.appendChild(cancelBtn);
    }

    // 确定按钮
    const okBtn = document.createElement('button');
    okBtn.className = confirmClass;
    okBtn.textContent = confirmText;
    okBtn.onclick = () => {
      if (input !== null) {
        const val = inputEl.value;
        close();
        resolve(val);  // 返回用户输入
      } else {
        close();
        resolve(true);
      }
    };
    actions.appendChild(okBtn);

    function close() {
      overlay.classList.remove('show');
      document.removeEventListener('keydown', keyHandler);
    }
    function keyHandler(e) {
      if (e.key === 'Escape') { close(); resolve(null); }  // null 表示用户关闭弹窗（非保存/非丢弃）
      else if (e.key === 'Enter' && input !== null) { okBtn.click(); }
      else if (e.key === 'Enter' && input === null) { close(); resolve(true); }
    }
    document.addEventListener('keydown', keyHandler);

    overlay.classList.add('show');
  });
}

// 便捷封装
function modalAlert(message, title = '提示', type = 'info') {
  return showModal({ title, message, type, confirmText: '确定', cancelText: '' });
}
function modalConfirm(message, title = '请确认', type = 'warning') {
  return showModal({ title, message, type, confirmText: '确定', cancelText: '取消' });
}
function modalPrompt(message, defaultVal = '', title = '请输入') {
  return showModal({ title, message, input: '请输入内容', inputDefault: defaultVal, confirmText: '确定', cancelText: '取消' });
}

function setSaveStatus(msg) {
  document.getElementById('save-status').textContent = msg;
}

function updateAuthUI() {
  document.body.classList.toggle('authed', isLoggedIn);
  // editing 类只在 isEditing && isLoggedIn 时启用
  document.body.classList.toggle('editing', isEditing && isLoggedIn);
  document.getElementById('user-status').textContent = isLoggedIn
    ? (isEditing ? '编辑模式' : '已登录 · 阅读模式')
    : '预览模式 · 仅查看';
  // 标题输入框：仅在编辑模式可写
  document.getElementById('doc-title').readOnly = !(isEditing && isLoggedIn);
}

/* ========== 登录弹窗 ========== */
function showLoginModal() {
  document.getElementById('login-modal').classList.add('show');
  document.getElementById('pwd').value = '';
  document.getElementById('login-err').textContent = '';
  setTimeout(() => document.getElementById('pwd').focus(), 50);
}

function hideLoginModal() {
  document.getElementById('login-modal').classList.remove('show');
}

async function doLogin() {
  const pwd = document.getElementById('pwd').value;
  const err = document.getElementById('login-err');
  err.textContent = '';
  const r = await api('login', { password: pwd });
  if (r.ok) {
    isLoggedIn = true;
    isEditing = false;  // 登录后默认阅读模式
    updateAuthUI();
    hideLoginModal();
    // 重新加载当前文档到阅读视图
    if (currentDocId) {
      const r2 = await api('get', { id: currentDocId }, 'GET');
      if (r2.ok) {
        document.getElementById('doc-title').value = r2.data.title || '';
        document.getElementById('read-content').innerHTML = r2.data.content || '';
      }
    }
  } else {
    err.textContent = r.msg || '登录失败';
  }
}

/* 进入编辑模式 */
async function enterEditMode() {
  if (!isLoggedIn) { showLoginModal(); return; }
  if (isEditing) return;  // 已在编辑模式
  if (!currentDocId) { modalAlert('请先选择一个文档', '提示', 'warning'); return; }

  setSaveStatus('加载编辑器...');
  await initEditor();
  isEditing = true;
  updateAuthUI();

  // 把当前文档内容塞到编辑器
  if (editor) {
    const r = await api('get', { id: currentDocId }, 'GET');
    if (r.ok) {
      editor.setContent(r.data.content || '');
      document.getElementById('doc-title').value = r.data.title || '';
      // 记录初始状态，用于检测未保存改动
      lastSavedTitle = r.data.title || '';
      lastSavedContent = r.data.content || '';
    }
  }
  setSaveStatus('');
}

/* 退出编辑模式（不退出登录） */
async function exitEditMode() {
  if (!isEditing) return;
  // 退出前询问是否保存
  const ok = await confirmSaveBeforeSwitch();
  if (!ok) return;  // 用户取消退出
  isEditing = false;
  if (editor) {
    editor.remove();
    editor = null;
  }
  updateAuthUI();
  // 重新渲染当前文档到阅读视图
  if (currentDocId) {
    selectNode({ id: currentDocId, title: currentDocTitle, is_folder: 0 });
  }
}

async function doLogout() {
  // 退出登录前询问是否保存未保存改动
  if (isEditing) {
    const ok = await confirmSaveBeforeSwitch();
    if (!ok) return;  // 用户取消退出
  }
  await api('logout');
  isLoggedIn = false;
  isEditing = false;
  if (editor) {
    editor.remove();
    editor = null;
  }
  // 退出时关闭回收站视图
  if (isTrashView) {
    isTrashView = false;
    document.getElementById('tree').style.display = 'block';
    document.getElementById('trash-tree').style.display = 'none';
    document.querySelector('.btn-trash-toggle').classList.remove('active');
    document.querySelector('.btn-trash-toggle').textContent = '🗑 回收站';
  }
  updateAuthUI();
  // 重新渲染当前文档到阅读视图
  if (currentDocId) {
    const r = await api('get', { id: currentDocId }, 'GET');
    if (r.ok) {
      document.getElementById('read-content').innerHTML = r.data.content || '';
    }
  }
  loadTree();
}

document.getElementById('pwd').addEventListener('keydown', e => {
  if (e.key === 'Enter') doLogin();
});
document.getElementById('login-modal').addEventListener('click', e => {
  if (e.target.id === 'login-modal') hideLoginModal();
});

/* ========== 树形导航 ========== */
async function loadTree() {
  const r = await api('list', {}, 'GET');
  if (!r.ok) return;
  treeData = r.data;
  // 同步数据库中 expanded=1 的文件夹到 expandedFolders（首次加载或刷新）
  // 但不清除用户手动折叠的（已从 expandedFolders 删除的不会重新加回）
  treeData.forEach(d => {
    if (d.is_folder == 1 && d.expanded == 1) {
      expandedFolders.add(parseInt(d.id));
    }
  });
  renderTree();
}

function buildTree(flatData) {
  const map = {};
  flatData.forEach(d => { map[d.id] = { ...d, children: [] }; });
  const roots = [];
  flatData.forEach(d => {
    if (d.parent_id && map[d.parent_id]) {
      map[d.parent_id].children.push(map[d.id]);
    } else {
      roots.push(map[d.id]);
    }
  });
  return roots;
}

function renderTree() {
  const root = document.getElementById('tree');
  root.innerHTML = '';
  const tree = buildTree(treeData);
  if (tree.length === 0) {
    root.innerHTML = '<div style="padding:16px;color:#9ca3af;text-align:center;">暂无文档</div>';
    return;
  }
  tree.forEach(node => root.appendChild(renderNode(node)));
}

/* ========== 回收站 ========== */
let isTrashView = false;
let trashData = [];

async function toggleTrash() {
  isTrashView = !isTrashView;
  const treeEl = document.getElementById('tree');
  const trashEl = document.getElementById('trash-tree');
  const btn = document.querySelector('.btn-trash-toggle');

  if (isTrashView) {
    treeEl.style.display = 'none';
    trashEl.style.display = 'block';
    btn.classList.add('active');
    btn.textContent = '← 返回文档';
    await loadTrashTree();
  } else {
    treeEl.style.display = 'block';
    trashEl.style.display = 'none';
    btn.classList.remove('active');
    btn.textContent = '🗑 回收站';
  }
}

async function loadTrashTree() {
  const r = await api('trash_list', {}, 'GET');
  if (!r.ok) return;
  trashData = r.data;
  renderTrashTree();
}

function renderTrashTree() {
  const root = document.getElementById('trash-tree');
  root.innerHTML = '';

  if (trashData.length === 0) {
    root.innerHTML = '<div style="padding:16px;color:#9ca3af;text-align:center;">回收站为空</div>';
    return;
  }

  // 用 trashData 构建：只在 deleted=1 节点集合中找父子关系
  // 若 parent_id 不在 trashData 中，则作为根节点显示
  const tree = buildTree(trashData);
  tree.forEach(node => root.appendChild(renderTrashNode(node)));
}

function renderTrashNode(node) {
  const wrapper = document.createElement('div');
  wrapper.className = 'tree-node-wrapper';

  const div = document.createElement('div');
  div.className = 'tree-node';

  const icon = document.createElement('span');
  icon.className = 'icon';
  icon.textContent = node.is_folder == 1 ? '📁' : '📄';
  icon.style.opacity = '0.5';

  const title = document.createElement('span');
  title.className = 'title';
  title.textContent = node.title;
  title.style.opacity = '0.6';
  title.style.fontStyle = 'italic';

  // 还原按钮
  const restoreBtn = document.createElement('button');
  restoreBtn.className = 'tree-btn edit';
  restoreBtn.textContent = '↩';
  restoreBtn.title = '还原';
  restoreBtn.onclick = async (e) => {
    e.stopPropagation();
    const ok = await modalConfirm(`确认还原「${node.title}」及其所有子内容？`, '还原确认', 'info');
    if (!ok) return;
    const r = await api('restore', { id: node.id }, 'GET');
    if (r.ok) {
      modalAlert(`已还原 ${r.restored} 个项目`, '还原成功', 'success');
      await loadTrashTree();
      await loadTree();  // 同步刷新主树
    } else {
      modalAlert(r.msg || '还原失败', '还原失败', 'danger');
    }
  };

  // 彻底删除按钮
  const purgeBtn = document.createElement('button');
  purgeBtn.className = 'tree-btn danger';
  purgeBtn.textContent = '×';
  purgeBtn.title = '彻底删除';
  purgeBtn.onclick = async (e) => {
    e.stopPropagation();
    const ok = await modalConfirm(`「${node.title}」将被永久删除，此操作不可恢复！`, '彻底删除确认', 'danger');
    if (!ok) return;
    // 二次确认
    const ok2 = await modalConfirm('真的要永久删除吗？此操作无法撤销！', '再次确认', 'danger');
    if (!ok2) return;
    const r = await api('purge', { id: node.id }, 'GET');
    if (r.ok) {
      modalAlert(`已永久删除 ${r.purged} 个项目`, '删除完成', 'success');
      await loadTrashTree();
    } else {
      modalAlert(r.msg || '删除失败', '删除失败', 'danger');
    }
  };

  div.appendChild(icon);
  div.appendChild(title);
  div.appendChild(restoreBtn);
  div.appendChild(purgeBtn);

  // 回收站里的节点默认展开，方便用户查看被删的结构
  if (node.is_folder == 1) {
    const childContainer = document.createElement('div');
    childContainer.className = 'tree-children';
    if (node.children && node.children.length) {
      node.children.forEach(c => childContainer.appendChild(renderTrashNode(c)));
    }
    wrapper.appendChild(div);
    wrapper.appendChild(childContainer);
  } else {
    wrapper.appendChild(div);
  }

  return wrapper;
}

function renderNode(node) {
  // 外层 wrapper：包含标题行 + 子节点容器（独立块级元素）
  const wrapper = document.createElement('div');
  wrapper.className = 'tree-node-wrapper';

  // 标题行：flex 横排 [图标][标题][按钮们]
  const div = document.createElement('div');
  div.className = 'tree-node' + (currentDocId === node.id ? ' active' : '');
  div.dataset.nodeId = node.id;  // 用于 selectNode 时只更新高亮，避免整树重渲染
  div.onclick = (e) => {
    e.stopPropagation();
    selectNode(node);
  };

  // 拖拽（仅登录用户）
  if (isLoggedIn) {
    div.draggable = true;
    div.addEventListener('dragstart', (e) => {
      draggedNode = node;
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', String(node.id));
      div.classList.add('dragging');
    });
    div.addEventListener('dragend', () => {
      div.classList.remove('dragging');
      document.querySelectorAll('.drag-over-before,.drag-over-after,.drag-over-in')
        .forEach(el => el.classList.remove('drag-over-before','drag-over-after','drag-over-in'));
      draggedNode = null;
    });
    div.addEventListener('dragover', (e) => {
      if (!draggedNode || draggedNode.id === node.id) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      const rect = div.getBoundingClientRect();
      const offset = (e.clientY - rect.top) / rect.height;
      // 清除之前的指示
      ['drag-over-before','drag-over-after','drag-over-in'].forEach(c => div.classList.remove(c));
      if (node.is_folder == 1 && offset > 0.35 && offset < 0.65) {
        div.classList.add('drag-over-in');
      } else if (offset < 0.5) {
        div.classList.add('drag-over-before');
      } else {
        div.classList.add('drag-over-after');
      }
    });
    div.addEventListener('dragleave', () => {
      ['drag-over-before','drag-over-after','drag-over-in'].forEach(c => div.classList.remove(c));
    });
    div.addEventListener('drop', async (e) => {
      if (!draggedNode || draggedNode.id === node.id) return;
      e.preventDefault();
      e.stopPropagation();
      const rect = div.getBoundingClientRect();
      const offset = (e.clientY - rect.top) / rect.height;
      ['drag-over-before','drag-over-after','drag-over-in'].forEach(c => div.classList.remove(c));
      await handleDrop(draggedNode, node, offset);
      draggedNode = null;
    });
  }

  const icon = document.createElement('span');
  icon.className = 'icon';
  icon.textContent = node.is_folder == 1 ? '📁' : '📄';

  const title = document.createElement('span');
  title.className = 'title';
  title.textContent = node.title;

  div.appendChild(icon);
  div.appendChild(title);

  // 重命名按钮（hover 显示，仅登录用户）
  const editBtn = document.createElement('button');
  editBtn.className = 'tree-btn auth-only edit';
  editBtn.textContent = '✎';
  editBtn.title = '重命名';
  editBtn.onclick = async (e) => {
    e.stopPropagation();
    if (!isLoggedIn) { showLoginModal(); return; }
    await renameNode(node);
  };
  div.appendChild(editBtn);

  // 删除按钮（hover 显示，仅登录用户可见，由 CSS 控制）
  const del = document.createElement('button');
  del.className = 'tree-btn auth-only danger';
  del.textContent = '×';
  del.title = '删除';
  del.onclick = async (e) => {
    e.stopPropagation();
    const confirmed = await modalConfirm(`确认删除「${node.title}」及其所有子内容？此操作不可恢复。`, '删除确认', 'danger');
    if (!confirmed) return;
    const r = await api('delete', { id: node.id }, 'GET');
    if (r.ok) {
      if (currentDocId === node.id) {
        currentDocId = null;
        document.getElementById('editor-area').style.display = 'none';
        document.getElementById('empty-state').style.display = 'flex';
      }
      loadTree();
    } else {
      modalAlert(r.msg || '删除失败', '删除失败', 'danger');
    }
  };
  div.appendChild(del);

  // 文件夹：在标题行加 + 按钮和"默认展开"切换按钮，并附加独立的子节点容器
  let childContainer = null;
  if (node.is_folder == 1) {
    // + 新建按钮（在标题行里）
    const addBtn = document.createElement('button');
    addBtn.className = 'tree-btn auth-only edit';
    addBtn.textContent = '+';
    addBtn.title = '在此文件夹下新建';
    addBtn.onclick = (e) => {
      e.stopPropagation();
      addMenu(node.id);
    };
    div.appendChild(addBtn);

    // 默认展开切换按钮（仅登录用户）
    // ☑ = 当前已设为"默认展开"，☐ = 默认折叠
    const expandBtn = document.createElement('button');
    expandBtn.className = 'tree-btn auth-only expand-toggle' + (node.expanded == 1 ? ' on' : '');
    expandBtn.innerHTML = node.expanded == 1 ? '☑' : '☐';
    expandBtn.title = node.expanded == 1 ? '已设为默认展开，点击取消' : '设为默认展开';
    expandBtn.style.color = node.expanded == 1 ? '#10b981' : '#9ca3af';
    expandBtn.onclick = async (e) => {
      e.stopPropagation();
      if (!isLoggedIn) { showLoginModal(); return; }
      const newVal = node.expanded == 1 ? 0 : 1;
      const r = await api('save', { id: node.id, expanded: newVal });
      if (r.ok) {
        node.expanded = newVal;
        expandBtn.innerHTML = newVal ? '☑' : '☐';
        expandBtn.title = newVal ? '已设为默认展开，点击取消' : '设为默认展开';
        expandBtn.style.color = newVal ? '#10b981' : '#9ca3af';
        // 同步当前显示状态到新设置的值
        if (childContainer) {
          childContainer.style.display = newVal ? 'block' : 'none';
          icon.textContent = newVal ? '📁' : '📂';
        }
      } else {
        modalAlert(r.msg || '设置失败', '设置失败', 'danger');
      }
    };
    div.appendChild(expandBtn);

    // 点击文件夹标题行：折叠/展开 + 选中
    // 选中态下再次点击：只折叠/展开，不取消选中（避免误操作丢失选中）
    // 取消选中只能通过：点击其他文件夹 / 点击文档
    div.onclick = (e) => {
      if (e.target.closest('.tree-btn')) return;
      e.stopPropagation();

      // 选中切换：仅在未选中或选中的不是当前文件夹时才切换
      if (selectedFolderId !== node.id) {
        // 清除其他文件夹的选中
        document.querySelectorAll('.folder-selected').forEach(el => el.classList.remove('folder-selected'));
        selectedFolderId = node.id;
        div.classList.add('folder-selected');
      }
      // 如果已经选中当前文件夹，保持选中，只做折叠/展开

      // 折叠/展开
      if (childContainer) {
        const willHide = childContainer.style.display !== 'none';
        childContainer.style.display = willHide ? 'none' : 'block';
        icon.textContent = willHide ? '📂' : '📁';
        // 同步到 expandedFolders，避免 loadTree 后丢失状态
        const fid = parseInt(node.id);
        if (willHide) expandedFolders.delete(fid);
        else expandedFolders.add(fid);
      }
    };
  }

  wrapper.appendChild(div);

  // 子节点容器：作为 wrapper 的兄弟元素，独立块级，缩进显示
  if (node.is_folder == 1) {
    childContainer = document.createElement('div');
    childContainer.className = 'tree-children';
    // 展开：数据库 expanded=1 或会话中已展开的
    const isExpanded = node.expanded == 1 || expandedFolders.has(parseInt(node.id));
    childContainer.style.display = isExpanded ? 'block' : 'none';
    if (isExpanded) {
      icon.textContent = '📁';
    } else {
      icon.textContent = '📂';
    }
    if (node.children.length) {
      node.children.forEach(c => childContainer.appendChild(renderNode(c)));
    }
    wrapper.appendChild(childContainer);
  }

  return wrapper;
}

/* ========== 拖拽处理 ==========
 * draggedNode: 被拖动的节点
 * targetNode: 释放目标节点
 * offset: 释放位置在目标节点的垂直比例 (0-1)
 *   < 0.35      → 插入到目标之前（同级）
 *   > 0.65      → 插入到目标之后（同级）
 *   0.35-0.65   → 若目标是文件夹，移入文件夹
 */
async function handleDrop(draggedNode, targetNode, offset) {
  if (!draggedNode || !targetNode || draggedNode.id === targetNode.id) return;

  // 防止把文件夹拖入自己的子孙（避免循环）
  if (targetNode.is_folder == 1 && offset > 0.35 && offset < 0.65) {
    if (isDescendant(targetNode, draggedNode)) {
      modalAlert('不能把文件夹拖入自己的子文件夹', '操作无效', 'warning');
      return;
    }
    // 移入文件夹：新 parent = targetNode.id，放最后
    const newParentId = targetNode.id;
    const siblings = getSiblings(newParentId);
    // 排除被拖动节点（如果它本来就在这个 parent 下）
    const filtered = siblings.filter(n => n.id !== draggedNode.id);
    filtered.push(draggedNode);
    await submitReorder(filtered, newParentId);
  } else {
    // 同级插入：新 parent = targetNode.parent_id
    const newParentId = targetNode.parent_id;
    // 防止拖入自己的子孙文件夹
    if (isDescendant(targetNode, draggedNode)) {
      modalAlert('不能移动到自己的子项中', '操作无效', 'warning');
      return;
    }
    const siblings = getSiblings(newParentId);
    const filtered = siblings.filter(n => n.id !== draggedNode.id);
    // 找到 targetNode 在 filtered 中的位置
    const targetIdx = filtered.findIndex(n => n.id === targetNode.id);
    let insertAt;
    if (offset < 0.5) {
      insertAt = targetIdx;  // 之前
    } else {
      insertAt = targetIdx + 1;  // 之后
    }
    filtered.splice(insertAt, 0, draggedNode);
    await submitReorder(filtered, newParentId);
  }
}

// 获取某个 parent 下的所有节点（从 treeData 平铺）
function getSiblings(parentId) {
  function walk(nodes) {
    const result = [];
    for (const n of nodes) {
      if (n.parent_id == parentId) result.push(n);
      if (n.children && n.children.length) result.push(...walk(n.children));
    }
    return result;
  }
  return walk(treeData);
}

// 判断 possibleDescendant 是否是 ancestor 的子孙
function isDescendant(ancestor, possibleDescendant) {
  if (!ancestor || !ancestor.children) return false;
  for (const child of ancestor.children) {
    if (child.id === possibleDescendant.id) return true;
    if (isDescendant(child, possibleDescendant)) return true;
  }
  return false;
}

// 提交重排：items 是 [{id, parent_id}] 数组，顺序即新 sort
async function submitReorder(items, parentId) {
  const payload = items.map(n => ({ id: n.id, parent_id: parentId }));
  const r = await api('reorder', { items: JSON.stringify(payload) });
  if (r.ok) {
    await loadTree();
  } else {
    modalAlert(r.msg || '排序失败', '排序失败', 'danger');
  }
}

async function addMenu(parentId) {
  if (!isLoggedIn) { showLoginModal(); return; }
  // 选择新建类型：文档 / 文件夹
  const choice = await showModal({
    title: '新建',
    message: '请选择要创建的类型：',
    type: 'info',
    confirmText: '文档',
    confirmClass: 'btn-primary',
    cancelText: '文件夹'
  });
  if (choice === true) {
    // 用户选"文档"
    const title = await modalPrompt('请输入文档标题：', '新文档', '新建文档');
    if (!title) return;
    await createNode(parentId, 0, title);
  } else {
    // 用户选"文件夹"
    const title = await modalPrompt('请输入文件夹名称：', '新文件夹', '新建文件夹');
    if (!title) return;
    await createNode(parentId, 1, title);
  }
}

async function createNode(parentId, isFolder = 0, title = null) {
  if (!isLoggedIn) { showLoginModal(); return; }
  if (!title) {
    title = await modalPrompt(
      isFolder ? '请输入文件夹名称：' : '请输入文档标题：',
      isFolder ? '新文件夹' : '新文档',
      isFolder ? '新建文件夹' : '新建文档'
    );
    if (!title) return;
  }
  const r = await api('create', { parent_id: parentId, title, is_folder: isFolder });
  if (r.ok) {
    // 如果在文件夹下创建，确保父文件夹保持展开和选中状态
    if (parentId) {
      expandedFolders.add(parseInt(parentId));
      // 保持文件夹选中，便于连续创建
      // 不调用 selectNode，避免清除文件夹选中
    }
    await loadTree();
    // 同步恢复 folder-selected 状态（loadTree 重建 DOM 后丢失）
    if (parentId) {
      selectedFolderId = parseInt(parentId);
      requestAnimationFrame(() => {
        const folderEl = document.querySelector(`.tree-node[data-node-id="${parentId}"]`);
        if (folderEl) folderEl.classList.add('folder-selected');
      });
    }
    if (!isFolder && !parentId) {
      // 只在根目录创建时才自动选中文档
      selectNode({ id: r.id, title, is_folder: 0 });
    }
  } else {
    modalAlert(r.msg || '创建失败', '创建失败', 'danger');
  }
}

function createDocInRoot() {
  createNode(selectedFolderId || 0, 0);
}

function createDocInRootFolder() {
  createNode(selectedFolderId || 0, 1);
}

// 重命名节点
async function renameNode(node) {
  if (!isLoggedIn) { showLoginModal(); return; }
  const newTitle = await modalPrompt('请输入新名称：', node.title, '重命名');
  if (newTitle === false) return; // 用户取消
  const trimmed = newTitle.trim();
  if (!trimmed) { modalAlert('名称不能为空', '提示', 'warning'); return; }
  if (trimmed === node.title) return; // 没变

  const r = await api('save', { id: node.id, title: trimmed });
  if (r.ok) {
    // 如果当前正在编辑/查看这个文档，同步更新标题
    if (currentDocId === node.id) {
      document.getElementById('doc-title').value = trimmed;
    }
    await loadTree();
  } else {
    modalAlert(r.msg || '重命名失败', '重命名失败', 'danger');
  }
}

/* ========== 选择节点 + 加载内容 ========== */
async function selectNode(node) {
  if (node.is_folder == 1) return; // 文件夹不进入编辑

  // 切换文档前：如果有未保存改动，询问用户
  if (node.id != currentDocId) {
    const ok = await confirmSaveBeforeSwitch();
    if (!ok) return;  // 用户取消切换
  }

  // 切换文档时自动退出编辑模式（避免误改其他文档）
  if (isEditing) {
    isEditing = false;
    if (editor) {
      editor.remove();
      editor = null;
    }
    updateAuthUI();
  }

  // 点击文档时清除文件夹选中
  if (selectedFolderId !== null) {
    selectedFolderId = null;
    document.querySelectorAll('.folder-selected').forEach(el => el.classList.remove('folder-selected'));
  }

  currentDocId = node.id;
  currentDocTitle = node.title;
  document.getElementById('editor-area').style.display = 'flex';
  document.getElementById('empty-state').style.display = 'none';
  document.getElementById('doc-title').value = node.title;
  setSaveStatus('加载中...');

  // 更新 URL 为固定链接（不刷新页面）
  const newUrl = `?id=${node.id}`;
  if (history.state?.id != node.id) {
    history.replaceState({ id: node.id }, '', newUrl);
    // 同步页面标题
    document.title = `${node.title} · SOPHub`;
  }

  // 获取完整内容
  const r = await api('get', { id: node.id }, 'GET');
  if (!r.ok) {
    modalAlert(r.msg, '加载失败', 'danger');
    return;
  }
  const content = r.data.content || '';

  if (isEditing && editor) {
    // 编辑模式：写入编辑器
    editor.setContent(content);
    setSaveStatus('');
  } else {
    // 阅读模式：直接渲染 HTML
    document.getElementById('read-content').innerHTML = content;
    setSaveStatus('');
  }

  // 只更新高亮，不重新渲染整棵树（避免折叠状态丢失）
  document.querySelectorAll('.tree-node.active').forEach(el => el.classList.remove('active'));
  // 找到当前节点对应的 DOM 元素并高亮
  document.querySelectorAll('.tree-node').forEach(el => {
    if (el.dataset.nodeId == node.id) el.classList.add('active');
  });
}

/* ========== URL 路由 ========== */
// 从 URL ?id=xxx 解析出要打开的文档 id
function getDocIdFromUrl() {
  const params = new URLSearchParams(location.search);
  const id = params.get('id');
  return id ? parseInt(id, 10) : null;
}

// 在 treeData 中查找指定 id 的节点
function findNodeById(nodes, id) {
  // id 可能是数字或字符串，统一用 == 宽松比较
  for (const n of nodes) {
    if (n.id == id) return n;
    if (n.children && n.children.length) {
      const found = findNodeById(n.children, id);
      if (found) return found;
    }
  }
  return null;
}

// 在 treeData 中查找节点的所有祖先（用于自动展开）
function findAncestors(nodes, id, ancestors = []) {
  for (const n of nodes) {
    if (n.id === id) return ancestors;
    if (n.children && n.children.length) {
      const result = findAncestors(n.children, id, [...ancestors, n]);
      if (result) return result;
    }
  }
  return null;
}

// 复制当前文档链接到剪贴板
async function copyCurrentLink() {
  if (!currentDocId) {
    modalAlert('请先选择一个文档', '提示', 'warning');
    return;
  }
  const url = `${location.origin}${location.pathname}?id=${currentDocId}`;
  try {
    await navigator.clipboard.writeText(url);
    // 临时提示
    const btn = document.getElementById('btn-copy-link');
    const origText = btn.textContent;
    btn.textContent = '✓ 已复制';
    btn.style.color = '#10b981';
    setTimeout(() => {
      btn.textContent = origText;
      btn.style.color = '';
    }, 1500);
  } catch (e) {
    // 回退方案
    const input = document.createElement('input');
    input.value = url;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    modalAlert('链接已复制: ' + url, '复制成功', 'success');
  }
}

// 监听浏览器前进/后退
window.addEventListener('popstate', (e) => {
  const id = e.state?.id || getDocIdFromUrl();
  if (id) {
    const node = findNodeById(treeData, id);
    if (node) selectNode(node);
  } else {
    // 回到首页
    currentDocId = null;
    document.getElementById('editor-area').style.display = 'none';
    document.getElementById('empty-state').style.display = 'flex';
    document.title = 'SOPHub · SOP 文档中心';
    renderTree();
  }
});

/* ========== 编辑器初始化 ========== */
async function initEditor() {
  if (editor) return; // 已初始化

  // 检测是否使用本地 TinyMCE
  const scripts = document.querySelectorAll('script[src]');
  let isLocal = false;
  scripts.forEach(s => {
    if (s.src.indexOf('tinymce/js/tinymce.min.js') > -1) isLocal = true;
  });
  const basePath = isLocal ? 'tinymce/js' : 'https://cdn.jsdelivr.net/npm/tinymce@6.8.4';

  return new Promise(resolve => {
    tinymce.init({
      selector: '#editor',
      height: '100%',
      menubar: false,
      branding: false,
      promotion: false,
      language: 'zh_CN',
      language_url: isLocal ? basePath + '/langs/zh_CN.js' : undefined,
      icons: 'default',
      icons_url: isLocal ? basePath + '/icons/default/icons.min.js' : undefined,
      skin: 'oxide',
      content_css: isLocal ? basePath + '/skins/content/default/content.min.css' : 'default',
      theme: 'silver',
      theme_url: isLocal ? basePath + '/themes/silver/theme.min.js' : undefined,
      plugins: 'image lists link table autolink fullscreen searchreplace wordcount',
      toolbar: 'undo redo | bold italic underline strikethrough | forecolor backcolor | h1 h2 h3 h4 | bullist numlist outdent indent | alignleft aligncenter alignright | link image table | removeformat fullscreen',
      paste_data_images: true,
      paste_word_tags: true,
      images_upload_url: 'api.php?action=upload',
      images_upload_credentials: true,
      file_picker_types: 'image',
      images_file_types: 'jpeg,jpg,png,gif,webp,bmp',
      images_upload_handler: async (blobInfo, progress) => {
        const fd = new FormData();
        fd.append('file', blobInfo.blob(), blobInfo.filename());
        if (currentDocId) fd.append('doc_id', currentDocId);
        const res = await fetch('api.php?action=upload', { method: 'POST', body: fd, credentials: 'same-origin' });
        const json = await res.json();
        if (!json.ok) {
          return Promise.reject(json.msg || '上传失败');
        }
        return json.location;
      },
      content_style: 'body { font-family: -apple-system, "Microsoft YaHei", sans-serif; font-size: 14px; line-height: 1.6; } img { max-width: 100%; height: auto; }',
      setup: (ed) => {
        editor = ed;
        ed.on('init', () => resolve());
        ed.addShortcut('ctrl+s', '保存', saveDoc);
        // 粘贴后扫描编辑器里的外链图片，下载到本地并替换 src
        ed.on('paste', () => {
          setTimeout(() => fetchRemoteImagesInEditor(ed), 500);
        });
      }
    });
  });
}

/* ========== 粘贴时自动下载外链图片到本地 ========== */

// URL 映射表：外链URL → 本地路径
const remoteImgMap = new Map();

// 下载单张外链图片，完成后把映射写入 remoteImgMap
async function fetchRemoteImgToLocal(imgEl, docId) {
  const url = imgEl.getAttribute('src');
  if (!url || !url.startsWith('http')) return;
  if (url.startsWith('data/uploads') || url.startsWith('data:')) return;
  if (imgEl.dataset.fetched) return;
  // 如果已经下载过（映射表里有），直接用映射表替换
  if (remoteImgMap.has(url)) {
    imgEl.setAttribute('src', remoteImgMap.get(url));
    imgEl.dataset.fetched = '1';
    return;
  }

  imgEl.dataset.fetched = '1';  // 先标记避免重复请求

  try {
    const res = await fetch(`api.php?action=fetch_url&url=${encodeURIComponent(url)}&doc_id=${docId}`, {
      credentials: 'same-origin'
    });
    if (!res.ok) {
      _warn('fetch_url HTTP', res.status, 'for', url.substring(0, 80));
      return;
    }
    const json = await res.json();
    if (json.ok) {
      _log('✓ 外链已本地化:', url.substring(0, 60), '→', json.location);
      // 写入映射表
      remoteImgMap.set(url, json.location);
      // 改 DOM（虽然 TinyMCE 可能不感知，但至少视觉上是对的）
      imgEl.setAttribute('src', json.location);
    } else {
      _warn('✗ 下载失败:', json.msg, url.substring(0, 80));
    }
  } catch (err) {
    _warn('✗ 下载异常:', err.message, url.substring(0, 80));
  }
}

// 扫描编辑器里所有外链图片，下载并替换
async function fetchRemoteImagesInEditor(ed) {
  const body = ed.getBody();
  if (!body) return;

  // 找所有 src 以 http 开头且还没处理过的 img
  const pending = [];
  const imgs = body.querySelectorAll('img[src^="http"]');
  for (const img of imgs) {
    if (!img.dataset.fetched) {
      const url = img.getAttribute('src');
      if (!url.startsWith('data/uploads') && !url.startsWith('data:')) {
        pending.push(img);
      }
    }
  }

  if (pending.length === 0) return;

  _log(`发现 ${pending.length} 张外链图片，开始下载...`);
  setSaveStatus(`正在下载 ${pending.length} 张外链图片到本地...`);

  const docId = currentDocId || 'temp';
  // 并发下载
  await Promise.all(pending.map(img => fetchRemoteImgToLocal(img, docId)));

  setSaveStatus('');
  // 下载完成后自动保存（映射表里有了，saveDoc 会替换）
  _log('外链图片处理完成，触发自动保存');
  scheduleAutoSave();
}

// 在 HTML 字符串中替换所有已映射的外链为本地路径
function applyRemoteImgMap(html) {
  if (remoteImgMap.size === 0) return html;
  let result = html;
  for (const [remoteUrl, localUrl] of remoteImgMap) {
    // 精确匹配 src="..." 中的 URL（处理可能的引号变体）
    const escaped = remoteUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    // 处理 src="url" 和 src='url' 两种情况，以及可能带 &amp; 编码的
    result = result.replace(new RegExp('src="(' + escaped.replace(/\//g, '\\/') + ')"', 'gi'), 'src="' + localUrl + '"');
    result = result.replace(new RegExp("src='(" + escaped.replace(/\//g, '\\/') + ")'", 'gi'), "src='" + localUrl + "'");
    // 处理 &amp; 编码的情况（HTML 里 & 会被转成 &amp;）
    const encodedUrl = remoteUrl.replace(/&/g, '&amp;');
    if (encodedUrl !== remoteUrl) {
      result = result.replace(new RegExp('src="(' + encodedUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\//g, '\\/') + ')"', 'gi'), 'src="' + localUrl + '"');
      result = result.replace(new RegExp("src='(" + encodedUrl.replace(/[.*+?^${}()|[\]\\]/g, '\\$&').replace(/\//g, '\\/') + ")'", 'gi'), "src='" + localUrl + "'");
    }
  }
  return result;
}

/* ========== 保存 ========== */
let saveTimer = null;
let lastSavedTitle = '';   // 上次保存的标题（用于检测未保存改动）
let lastSavedContent = ''; // 上次保存的内容

// 检查当前文档是否有未保存的改动
function hasUnsavedChanges() {
  if (!isEditing || !currentDocId) return false;
  const currentTitle = document.getElementById('doc-title').value;
  const currentContent = editor ? editor.getContent() : '';
  return currentTitle !== lastSavedTitle || currentContent !== lastSavedContent;
}

// 询问用户是否保存未保存的改动（返回 true 表示已处理，可继续切换）
async function confirmSaveBeforeSwitch() {
  if (!hasUnsavedChanges()) return true;

  const choice = await showModal({
    title: '保存更改',
    message: `文档「${currentDocTitle}」有未保存的改动，是否保存？`,
    type: 'warning',
    confirmText: '保存',
    confirmClass: 'btn-primary',
    cancelText: '不保存'
  });
  if (choice === true) {
    // 用户选"保存"
    await saveDoc();
    return true;
  } else if (choice === false) {
    // 用户选"不保存"，丢弃改动
    return true;
  }
  // 用户按 Escape 关闭弹窗，取消切换
  return false;
}
function scheduleAutoSave() {
  if (!isLoggedIn) return;
  clearTimeout(saveTimer);
  setSaveStatus('未保存');
  saveTimer = setTimeout(saveDoc, 3000);
}

async function saveDoc() {
  if (!isLoggedIn) { showLoginModal(); return; }
  if (!currentDocId) return;
  const title = document.getElementById('doc-title').value.trim() || '未命名';
  let content = editor ? editor.getContent() : '';
  // 用映射表替换外链图片为本地路径（TinyMCE 内部缓存可能没同步 DOM 修改）
  content = applyRemoteImgMap(content);
  setSaveStatus('保存中...');
  const r = await api('save', { id: currentDocId, title, content });
  if (r.ok) {
    setSaveStatus('已保存 ' + new Date().toLocaleTimeString());
    currentDocTitle = title;
    lastSavedTitle = title;
    lastSavedContent = content;
    await loadTree();
  } else {
    setSaveStatus('保存失败：' + (r.msg || ''));
    if (r.code === 401) {
      isLoggedIn = false;
      updateAuthUI();
      showLoginModal();
    } else {
      modalAlert(r.msg || '保存失败', '保存失败', 'danger');
    }
  }
}

document.addEventListener('input', (e) => {
  if (e.target.id === 'doc-title') scheduleAutoSave();
});

document.addEventListener('keydown', (e) => {
  if ((e.ctrlKey || e.metaKey) && e.key === 's') {
    e.preventDefault();
    saveDoc();
  }
});

/* ========== 侧边栏拖拽调整 ========== */
(function initResizer() {
  const resizer = document.getElementById('sidebar-resizer');
  const sidebar = document.querySelector('.sidebar');
  let startX = 0, startWidth = 0;

  resizer.addEventListener('mousedown', (e) => {
    startX = e.clientX;
    startWidth = sidebar.offsetWidth;
    resizer.classList.add('dragging');
    document.body.classList.add('resizing');
    e.preventDefault();  // 防止选中文字
  });

  document.addEventListener('mousemove', (e) => {
    if (!document.body.classList.contains('resizing')) return;
    const delta = e.clientX - startX;
    const newWidth = Math.min(600, Math.max(160, startWidth + delta));
    sidebar.style.width = newWidth + 'px';
  });

  document.addEventListener('mouseup', () => {
    if (!document.body.classList.contains('resizing')) return;
    document.body.classList.remove('resizing');
    resizer.classList.remove('dragging');
    // 刷新后宽度回到初始值（不持久化）
  });
})();

/* ========== 启动：检查登录态并加载文档树 ========== */
(async function init() {
  try {
    const r = await api('check', {}, 'GET');
    if (r.ok && r.logged_in) {
      isLoggedIn = true;
      // 登录态默认阅读模式，编辑器按需初始化
      updateAuthUI();
    } else {
      updateAuthUI();
    }
    // 无论登录与否，都加载文档树供查看
    await loadTree();
    // 检查 URL 中是否指定了文档 id，自动打开
    const urlId = getDocIdFromUrl();
    if (urlId) {
      const node = findNodeById(treeData, urlId);
      if (node) {
        await selectNode(node);
      } else {
        // 文档不存在，清理 URL
        history.replaceState(null, '', location.pathname);
      }
    }
  } catch (e) {
    console.error('初始化失败:', e);
  }
})();
</script>

</body>
</html>
