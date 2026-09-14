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

  .body { flex: 1; display: flex; min-height: 0; }

  /* 侧边栏 */
  .sidebar {
    width: 280px; background: #fff; border-right: 1px solid #e5e7eb;
    display: flex; flex-direction: column; flex-shrink: 0;
  }
  .sidebar-toolbar {
    padding: 8px; border-bottom: 1px solid #f3f4f6; display: flex; gap: 6px; flex-wrap: wrap;
  }
  .sidebar-toolbar .btn { flex: 1; min-width: 0; font-size: 12px; padding: 4px 8px; }
  .tree {
    flex: 1; overflow-y: auto; padding: 4px 0;
  }
  .tree-node {
    display: flex; align-items: center; padding: 4px 8px; cursor: pointer;
    border-radius: 4px; user-select: none;
  }
  .tree-node:hover { background: #f3f4f6; }
  .tree-node.active { background: #e0e7ff; color: #4338ca; }
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
  .tree-node:hover .tree-btn { opacity: 1; }
  body:not(.authed) .tree-node .tree-btn { display: none; }
  .tree-children { margin-left: 18px; }

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
  #read-view .read-title {
    font-size: 22px; font-weight: 500; margin-bottom: 16px; color: #1f2937;
    padding-bottom: 12px; border-bottom: 1px solid #e5e7eb;
  }
  #read-view .read-content { line-height: 1.7; color: #374151; }
  #read-view .read-content img { max-width: 100%; height: auto; }
  #read-view .read-content table { border-collapse: collapse; width: 100%; margin: 12px 0; }
  #read-view .read-content th, #read-view .read-content td { border: 1px solid #d1d5db; padding: 6px 10px; }
  #read-view .read-content code { background: #f3f4f6; padding: 2px 4px; border-radius: 3px; font-size: 13px; }
  #read-view .read-content pre { background: #f3f4f6; padding: 12px; border-radius: 4px; overflow-x: auto; margin: 12px 0; }
  body:not(.authed) #read-view { display: block; }
  body:not(.authed) #editor { display: none; }

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
</style>
</head>
<body>

<!-- 主应用（默认显示，游客也能浏览） -->
<div id="app">
  <div class="topbar">
    <h1>
      SOPHub <span class="subtitle">· SOP 文档中心</span>
      <span id="user-status" class="subtitle">游客模式 · 仅查看</span>
    </h1>
    <div class="actions">
      <span id="save-status" class="save-status auth-only"></span>
      <button class="btn btn-primary auth-only" onclick="saveDoc()" title="保存 (Ctrl+S)">保存</button>
      <button id="login-btn" class="btn btn-primary" onclick="showLoginModal()">登录编辑</button>
      <button id="logout-btn" class="btn auth-only" onclick="doLogout()">退出登录</button>
    </div>
  </div>
  <div class="body">
    <aside class="sidebar">
      <div class="sidebar-toolbar">
        <button class="btn auth-only" onclick="createNode(0, 1)" title="在根目录新建文件夹">📁 新建文件夹</button>
        <button class="btn auth-only" onclick="createDocInRoot()" title="在根目录新建文档">📝 新建文档</button>
        <button class="btn" onclick="loadTree()" title="刷新">🔄 刷新</button>
      </div>
      <div id="tree" class="tree"></div>
    </aside>
    <main class="main">
      <div id="editor-area" style="display:none;">
        <div class="editor-header">
          <input type="text" id="doc-title" placeholder="文档标题" readonly>
        </div>
        <div class="editor-body">
          <!-- 阅读视图（游客用） -->
          <div id="read-view">
            <div class="read-title" id="read-title"></div>
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

<script>
/* ========== 全局状态 ========== */
let isLoggedIn = false;
let currentDocId = null;
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

function setSaveStatus(msg) {
  document.getElementById('save-status').textContent = msg;
}

function updateAuthUI() {
  document.body.classList.toggle('authed', isLoggedIn);
  document.getElementById('user-status').textContent = isLoggedIn
    ? '已登录 · 可编辑'
    : '游客模式 · 仅查看';
  // 标题输入框：登录时可编辑
  document.getElementById('doc-title').readOnly = !isLoggedIn;
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
    updateAuthUI();
    hideLoginModal();
    // 切换到编辑模式：初始化编辑器，把当前文档内容塞进去
    await initEditor();
    if (currentDocId) {
      // 重新加载当前文档到编辑器
      const r2 = await api('get', { id: currentDocId }, 'GET');
      if (r2.ok && editor) {
        editor.setContent(r2.data.content || '');
        document.getElementById('doc-title').value = r2.data.title || '';
      }
    }
  } else {
    err.textContent = r.msg || '登录失败';
  }
}

async function doLogout() {
  await api('logout');
  isLoggedIn = false;
  updateAuthUI();
  // 销毁编辑器，切回阅读视图
  if (editor) {
    editor.remove();
    editor = null;
  }
  // 重新渲染当前文档（如果有的话）
  if (currentDocId) {
    const r = await api('get', { id: currentDocId }, 'GET');
    if (r.ok) {
      document.getElementById('read-title').textContent = r.data.title;
      document.getElementById('read-content').innerHTML = r.data.content || '';
    }
  }
  // 重新加载树（隐藏删除按钮等）
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

function renderNode(node) {
  const div = document.createElement('div');
  div.className = 'tree-node' + (currentDocId === node.id ? ' active' : '');
  div.onclick = (e) => {
    e.stopPropagation();
    selectNode(node);
  };

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
    if (!confirm(`确认删除「${node.title}」及其所有子内容？此操作不可恢复。`)) return;
    const r = await api('delete', { id: node.id }, 'GET');
    if (r.ok) {
      if (currentDocId === node.id) {
        currentDocId = null;
        document.getElementById('editor-area').style.display = 'none';
        document.getElementById('empty-state').style.display = 'flex';
      }
      loadTree();
    } else {
      alert(r.msg || '删除失败');
    }
  };
  div.appendChild(del);

  // 子节点容器
  if (node.is_folder == 1 && node.children.length) {
    const childContainer = document.createElement('div');
    childContainer.className = 'tree-children';
    node.children.forEach(c => childContainer.appendChild(renderNode(c)));
    div.appendChild(childContainer);

    div.onclick = (e) => {
      e.stopPropagation();
      childContainer.style.display = childContainer.style.display === 'none' ? 'block' : 'none';
      icon.textContent = childContainer.style.display === 'none' ? '📂' : '📁';
    };

    // 文件夹"+ 新建"按钮（仅登录用户）
    const addBtn = document.createElement('button');
    addBtn.className = 'tree-btn auth-only edit';
    addBtn.textContent = '+';
    addBtn.title = '在此文件夹下新建';
    addBtn.onclick = (e) => {
      e.stopPropagation();
      addMenu(node.id);
    };
    div.appendChild(addBtn);
  }

  return div;
}

// 文件夹内新建（弹出选择）
async function addMenu(parentId) {
  if (!isLoggedIn) { showLoginModal(); return; }
  const type = prompt('新建：1=文档，2=文件夹', '1');
  if (type === null) return;
  if (type === '1') {
    const title = prompt('文档标题：', '新文档');
    if (!title) return;
    await createNode(parentId, 0, title);
  } else if (type === '2') {
    const title = prompt('文件夹名称：', '新文件夹');
    if (!title) return;
    await createNode(parentId, 1, title);
  } else {
    alert('请输入 1 或 2');
  }
}

async function createNode(parentId, isFolder = 0, title = null) {
  if (!isLoggedIn) { showLoginModal(); return; }
  if (!title) {
    title = prompt(isFolder ? '文件夹名称：' : '文档标题：', isFolder ? '新文件夹' : '新文档');
    if (!title) return;
  }
  const r = await api('create', { parent_id: parentId, title, is_folder: isFolder });
  if (r.ok) {
    await loadTree();
    if (!isFolder) selectNode({ id: r.id, title, is_folder: 0 });
  } else {
    alert(r.msg || '创建失败');
  }
}

function createDocInRoot() {
  createNode(0, 0);
}

// 重命名节点
async function renameNode(node) {
  if (!isLoggedIn) { showLoginModal(); return; }
  const newTitle = prompt('请输入新名称：', node.title);
  if (newTitle === null) return; // 用户取消
  const trimmed = newTitle.trim();
  if (!trimmed) { alert('名称不能为空'); return; }
  if (trimmed === node.title) return; // 没变

  const r = await api('save', { id: node.id, title: trimmed });
  if (r.ok) {
    // 如果当前正在编辑/查看这个文档，同步更新标题
    if (currentDocId === node.id) {
      document.getElementById('doc-title').value = trimmed;
      if (!isLoggedIn) {
        document.getElementById('read-title').textContent = trimmed;
      }
    }
    await loadTree();
  } else {
    alert(r.msg || '重命名失败');
  }
}

/* ========== 选择节点 + 加载内容 ========== */
async function selectNode(node) {
  if (node.is_folder == 1) return; // 文件夹不进入编辑

  currentDocId = node.id;
  currentDocTitle = node.title;
  document.getElementById('editor-area').style.display = 'flex';
  document.getElementById('empty-state').style.display = 'none';
  document.getElementById('doc-title').value = node.title;
  setSaveStatus('加载中...');

  // 获取完整内容
  const r = await api('get', { id: node.id }, 'GET');
  if (!r.ok) {
    alert(r.msg);
    return;
  }
  const content = r.data.content || '';

  if (isLoggedIn && editor) {
    // 编辑模式：写入编辑器
    editor.setContent(content);
    setSaveStatus('');
  } else {
    // 阅读模式：直接渲染 HTML
    document.getElementById('read-title').textContent = r.data.title || node.title;
    document.getElementById('read-content').innerHTML = content;
    setSaveStatus('');
  }

  // 高亮当前节点
  renderTree();
}

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
      paste_retain_style_properties: 'all',
      paste_word_tags: true,
      images_upload_url: 'api.php?action=upload',
      images_upload_credentials: true,
      file_picker_types: 'image',
      images_file_types: 'jpeg,jpg,png,gif,webp,bmp',
      images_upload_handler: async (blobInfo, progress) => {
        const fd = new FormData();
        fd.append('file', blobInfo.blob(), blobInfo.filename());
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
      }
    });
  });
}

/* ========== 保存 ========== */
let saveTimer = null;
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
  const content = editor ? editor.getContent() : '';
  setSaveStatus('保存中...');
  const r = await api('save', { id: currentDocId, title, content });
  if (r.ok) {
    setSaveStatus('已保存 ' + new Date().toLocaleTimeString());
    currentDocTitle = title;
    await loadTree();
  } else {
    setSaveStatus('保存失败：' + (r.msg || ''));
    if (r.code === 401) {
      isLoggedIn = false;
      updateAuthUI();
      showLoginModal();
    } else {
      alert(r.msg || '保存失败');
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

/* ========== 启动：检查登录态并加载文档树 ========== */
(async function init() {
  try {
    const r = await api('check', {}, 'GET');
    if (r.ok && r.logged_in) {
      isLoggedIn = true;
      updateAuthUI();
      await initEditor();
    } else {
      updateAuthUI();
    }
    // 无论登录与否，都加载文档树供查看
    await loadTree();
  } catch (e) {
    console.error('初始化失败:', e);
  }
})();
</script>

</body>
</html>
