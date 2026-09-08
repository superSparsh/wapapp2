/**
 * Chatbot Flow Builder - Vanilla JS module for visual flow design.
 *
 * Manages drag-and-drop node creation, SVG connection lines, configuration
 * panels with proper select dropdowns, and save/load via the builder API.
 */

let flowState = { nodes: [], edges: [] };
let canvasEl = null;
let nodesContainer = null;
let svgOverlay = null;
let tempLine = null;
let nodeIdCounter = 0;
let selectedNode = null;
let draggingConnection = null;
let isDraggingFromPalette = false;
let flowDirty = false;
let edgeRenderScheduled = false;
let canvasZoom = 1;

const nodeConfig = () => window.AutomationNodeConfig;

export function initFlowBuilder(initialData) {
    flowState = {
        nodes: (initialData && initialData.nodes) || [],
        edges: (initialData && initialData.edges) || [],
    };

    canvasEl = document.getElementById('flow-canvas');
    nodesContainer = document.getElementById('nodes-container');

    if (!canvasEl || !nodesContainer) return;

    nodeIdCounter = flowState.nodes.length;

    setupSvgOverlay();
    setupCategoryTabs();
    setupPaletteDrag();
    setupCanvasDrop();
    setupToolbarActions();
    setupConfigPanel();
    setupConnectionDragLine();
    setupDirtyGuard();
    renderAllNodes();
    updateBadges();

    // Defer edge rendering until nodes are in DOM
    scheduleRenderEdges();

    window.addEventListener('resize', () => {
        resizeSvg();
        scheduleRenderEdges();
    });
}

/* ── SVG Overlay for Connection Lines ── */

function setupSvgOverlay() {
    svgOverlay = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svgOverlay.setAttribute('class', 'absolute inset-0 w-full h-full pointer-events-none');
    svgOverlay.style.zIndex = '5';
    svgOverlay.style.overflow = 'visible';

    // Arrowhead marker
    const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
    const marker = document.createElementNS('http://www.w3.org/2000/svg', 'marker');
    marker.setAttribute('id', 'arrowhead');
    marker.setAttribute('markerWidth', '10');
    marker.setAttribute('markerHeight', '7');
    marker.setAttribute('refX', '10');
    marker.setAttribute('refY', '3.5');
    marker.setAttribute('orient', 'auto');
    const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
    polygon.setAttribute('points', '0 0, 10 3.5, 0 7');
    polygon.setAttribute('fill', '#00a884');
    marker.appendChild(polygon);
    defs.appendChild(marker);
    svgOverlay.appendChild(defs);

    nodesContainer.insertBefore(svgOverlay, nodesContainer.firstChild);
    resizeSvg();
}

function resizeSvg() {
    if (!svgOverlay || !nodesContainer) return;
    const w = Math.max(nodesContainer.scrollWidth, nodesContainer.clientWidth, 1200);
    const h = Math.max(nodesContainer.scrollHeight, nodesContainer.clientHeight, 800);
    svgOverlay.setAttribute('width', w);
    svgOverlay.setAttribute('height', h);
    svgOverlay.setAttribute('viewBox', `0 0 ${w} ${h}`);
}

function scheduleRenderEdges() {
    if (edgeRenderScheduled) return;
    edgeRenderScheduled = true;
    requestAnimationFrame(() => {
        edgeRenderScheduled = false;
        scheduleRenderEdges();
    });
}

function markDirty() {
    flowDirty = true;
}

function setupDirtyGuard() {
    window.addEventListener('beforeunload', (e) => {
        if (!flowDirty) return;
        e.preventDefault();
        e.returnValue = '';
    });
}

function renderEdges() {
    if (!svgOverlay) return;
    resizeSvg();

    // Remove old paths (keep defs)
    svgOverlay.querySelectorAll('path').forEach(p => p.remove());

    flowState.edges.forEach(edge => {
        const sourceEl = nodesContainer.querySelector(`[data-node-id="${edge.source}"]`);
        const targetEl = nodesContainer.querySelector(`[data-node-id="${edge.target}"]`);
        if (!sourceEl || !targetEl) return;

        const points = getEdgePoints(sourceEl, targetEl);
        if (!points) return;

        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', buildBezierPath(points));
        path.setAttribute('stroke', '#00a884');
        path.setAttribute('stroke-width', '2');
        path.setAttribute('fill', 'none');
        path.setAttribute('marker-end', 'url(#arrowhead)');
        svgOverlay.appendChild(path);
    });
}

function getEdgePoints(sourceEl, targetEl) {
    const containerRect = nodesContainer.getBoundingClientRect();
    const sourceRect = sourceEl.getBoundingClientRect();
    const targetRect = targetEl.getBoundingClientRect();

    // Output: right-center of source node
    const x1 = sourceRect.right - containerRect.left + nodesContainer.scrollLeft;
    const y1 = sourceRect.top + sourceRect.height / 2 - containerRect.top + nodesContainer.scrollTop;

    // Input: top-center of target node
    const x2 = targetRect.left + targetRect.width / 2 - containerRect.left + nodesContainer.scrollLeft;
    const y2 = targetRect.top - containerRect.top + nodesContainer.scrollTop;

    return { x1, y1, x2, y2 };
}

function buildBezierPath({ x1, y1, x2, y2 }) {
    const dx = Math.abs(x2 - x1);
    const dy = Math.abs(y2 - y1);
    const curvature = Math.max(dx * 0.5, 50);

    const cx1 = x1 + curvature;
    const cy1 = y1;
    const cx2 = x2;
    const cy2 = y2 - Math.min(dy * 0.5, curvature);

    return `M ${x1} ${y1} C ${cx1} ${cy1}, ${cx2} ${cy2}, ${x2} ${y2}`;
}

/* ── Temporary drag line while connecting nodes ── */

function setupConnectionDragLine() {
    document.addEventListener('mousemove', (e) => {
        if (!draggingConnection || !svgOverlay) return;

        const sourceEl = nodesContainer.querySelector(`[data-node-id="${draggingConnection.sourceId}"]`);
        if (!sourceEl) return;

        const containerRect = nodesContainer.getBoundingClientRect();
        const sourceRect = sourceEl.getBoundingClientRect();

        const x1 = sourceRect.right - containerRect.left + nodesContainer.scrollLeft;
        const y1 = sourceRect.top + sourceRect.height / 2 - containerRect.top + nodesContainer.scrollTop;
        const x2 = e.clientX - containerRect.left + nodesContainer.scrollLeft;
        const y2 = e.clientY - containerRect.top + nodesContainer.scrollTop;

        if (!tempLine) {
            tempLine = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            tempLine.setAttribute('stroke', '#00a884');
            tempLine.setAttribute('stroke-width', '2');
            tempLine.setAttribute('stroke-dasharray', '6 3');
            tempLine.setAttribute('fill', 'none');
            svgOverlay.appendChild(tempLine);
        }

        tempLine.setAttribute('d', buildBezierPath({ x1, y1, x2, y2 }));
    });

    document.addEventListener('mouseup', () => {
        if (tempLine) {
            tempLine.remove();
            tempLine = null;
        }
        draggingConnection = null;
    });
}

/* ── Category Tabs ── */

function setupCategoryTabs() {
    const palette = document.getElementById('node-palette');
    if (!palette) return;

    const firstActiveTab = document.querySelector('.category-tab.bg-green-100');
    if (firstActiveTab) {
        const firstCategory = firstActiveTab.dataset.category;
        palette.querySelectorAll('.palette-category').forEach((cat) => {
            cat.classList.toggle('hidden', cat.dataset.paletteCategory !== firstCategory);
        });
    }

    document.querySelectorAll('.category-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            if (isDraggingFromPalette) return;

            const category = tab.dataset.category;
            const isActive = tab.classList.contains('bg-green-100');

            document.querySelectorAll('.category-tab').forEach((t) => {
                t.classList.remove('bg-green-100');
                t.classList.add('bg-elevated');
            });

            if (isActive) {
                palette.classList.add('hidden');
                return;
            }

            tab.classList.add('bg-green-100');
            tab.classList.remove('bg-elevated');

            palette.querySelectorAll('.palette-category').forEach((cat) => {
                cat.classList.toggle('hidden', cat.dataset.paletteCategory !== category);
            });

            palette.classList.remove('hidden');
        });
    });

    document.addEventListener('click', (e) => {
        if (isDraggingFromPalette) return;
        if (!palette.contains(e.target) && !e.target.closest('.category-tab')) {
            palette.classList.add('hidden');
        }
    });
}

/* ── Drag from Palette ── */

function setupPaletteDrag() {
    document.querySelectorAll('.palette-node').forEach((node) => {
        const isComingSoon = node.dataset.comingSoon === '1';

        if (isComingSoon) {
            node.setAttribute('draggable', 'false');
            node.classList.remove('cursor-grab');
            node.classList.add('cursor-not-allowed', 'opacity-60');
            return;
        }

        node.addEventListener('dragstart', (e) => {
            isDraggingFromPalette = true;
            e.dataTransfer.setData('text/plain', JSON.stringify({
                type: node.dataset.nodeType,
                label: node.dataset.nodeLabel,
            }));
            e.dataTransfer.effectAllowed = 'copy';
        });

        node.addEventListener('dragend', () => {
            setTimeout(() => { isDraggingFromPalette = false; }, 100);
        });
    });

    if (nodesContainer) {
        nodesContainer.addEventListener('drop', () => {
            setTimeout(() => { isDraggingFromPalette = false; }, 100);
        });
    }
}

/* ── Drop on Canvas ── */

function setupCanvasDrop() {
    nodesContainer.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    });

    nodesContainer.addEventListener('drop', (e) => {
        e.preventDefault();
        const hint = document.getElementById('empty-canvas-hint');
        if (hint) hint.remove();

        try {
            const data = JSON.parse(e.dataTransfer.getData('text/plain'));
            const rect = nodesContainer.getBoundingClientRect();
            const x = e.clientX - rect.left + nodesContainer.scrollLeft;
            const y = e.clientY - rect.top + nodesContainer.scrollTop;
            addNode(data.type, data.label, x, y);
        } catch {
            // Ignore invalid drops
        }
    });
}

/* ── Node Creation ── */

function addNode(type, label, x, y) {
    nodeIdCounter++;
    const id = `node_${nodeIdCounter}_${Date.now()}`;
    const defaults = nodeConfig()?.defaultData?.() || {};

    const nodeData = {
        id,
        type,
        position: { x: x || 0, y: y || 0 },
        data: {
            ...defaults,
            label,
        },
    };

    flowState.nodes.push(nodeData);
    renderNode(nodeData);
    updateBadges();
    markDirty();
    syncToState();
}

function getNodePreview(node) {
    if (nodeConfig()?.getPreview) {
        return nodeConfig().getPreview(node);
    }

    const d = node.data;
    switch (node.type) {
        case 'welcomeMessage':
            return d.message ? truncate(d.message, 60) : 'Click to configure...';
        case 'templateMessage':
            return d.template_name ? `Template: ${truncate(d.template_name, 40)}` : 'Click to configure...';
        case 'interactiveMessage':
            return d.interactive_type
                ? `${d.interactive_type}: ${(d.options || []).length} options`
                : 'Click to configure...';
        case 'mediaMessage':
            return d.media_url
                ? `${d.media_type || 'image'}: ${truncate(d.media_url, 40)}`
                : 'Click to configure...';
        case 'condition':
        case 'enhancedCondition':
            return d.condition_variable
                ? `${d.condition_variable} ${d.condition_operator || '=='} ${d.condition_value || ''}`
                : 'Click to configure...';
        case 'httpRequest':
            return d.url ? `${d.method || 'GET'} ${truncate(d.url, 40)}` : 'Click to configure...';
        case 'functionCall':
            return d.function_name ? `fn: ${d.function_name}` : 'Click to configure...';
        case 'delay':
            return d.delay_seconds ? `${d.delay_seconds} seconds` : 'Click to configure...';
        case 'typingIndicator':
            return d.delay_seconds ? `${d.delay_seconds} seconds` : 'Click to configure...';
        case 'waitForResponse':
            return d.variable_name
                ? `var: ${d.variable_name}, timeout: ${d.timeout || 120}s`
                : 'Click to configure...';
        case 'jumpToStep':
            return d.target_node ? `Target: ${d.target_node}` : 'Click to configure...';
        case 'carouselTemplate':
        case 'whatsappFlowTemplate':
            return 'Coming Soon';
        default:
            return d.message ? truncate(d.message, 60) : 'Click to configure...';
    }
}

function renderNode(nodeData) {
    const el = document.createElement('div');
    el.className = 'flow-node relative flex flex-col items-start justify-center gap-2 rounded-lg border-2 border-solid border-green-500 bg-green-50 p-3 shadow-[0px_6px_4px_rgba(109,187,72,0.25)] cursor-move';
    el.dataset.nodeId = nodeData.id;
    el.draggable = true;

    const iconMap = {
        welcomeMessage: 'message-notif-node.svg',
        templateMessage: 'document-text.svg',
        interactiveMessage: 'messages.svg',
        mediaMessage: 'gallery.svg',
        condition: 'hierarchy-3.svg',
        enhancedCondition: 'hierarchy-3.svg',
        waitForResponse: 'message-notif.svg',
        delay: 'clock.svg',
        typingIndicator: 'more.svg',
        httpRequest: 'scroll.svg',
        functionCall: 'scroll.svg',
        jumpToStep: 'routing-2.svg',
        carouselTemplate: 'car.svg',
        whatsappFlowTemplate: 'routing-2.svg',
    };

    const icon = iconMap[nodeData.type] || 'message-notif-node.svg';
    const preview = getNodePreview(nodeData);

    el.style.position = 'absolute';
    el.style.left = (nodeData.position.x || 0) + 'px';
    el.style.top = (nodeData.position.y || 0) + 'px';
    el.style.zIndex = '10';

    el.innerHTML = `
        <div class="flex items-center gap-2">
            <img src="/images/automation/${icon}" alt="" class="size-4" width="16" height="16">
            <span class="text-sm font-medium leading-[1.5] whitespace-nowrap text-text-subtle">${escapeHtml(nodeData.data.label || nodeData.type)}</span>
            <button type="button" aria-label="Delete node" data-delete-node="${nodeData.id}" class="flex items-center justify-center rounded bg-[#ffd5d5] p-1 hover:bg-[#ffb8b8]">
                <img src="/images/automation/trash-node.svg" alt="" class="size-4" width="16" height="16">
            </button>
        </div>
        <div class="text-sm font-medium leading-[1.4] text-text-body opacity-[0.57] node-preview">
            <p>${escapeHtml(preview)}</p>
        </div>
        <img src="/images/automation/connector-dot.svg" alt="" class="connector-input absolute top-[-5px] left-1/2 size-1.5 -translate-x-1/2 cursor-crosshair" width="6" height="6" data-handle="input" data-node-id="${nodeData.id}">
        <img src="/images/automation/connector-dot.svg" alt="" class="connector-output absolute top-1/2 right-[-5px] size-1.5 -translate-y-1/2 cursor-crosshair" width="6" height="6" data-handle="output" data-node-id="${nodeData.id}">
        <img src="/images/automation/connector-handle.svg" alt="" class="absolute top-1/2 right-[-22px] size-2 -translate-y-1/2 cursor-crosshair" width="8" height="8" data-handle="output" data-node-id="${nodeData.id}">
    `;

    // Click to configure
    el.addEventListener('click', (e) => {
        if (e.target.closest('[data-delete-node]') || e.target.closest('[data-handle]')) return;
        openConfigPanel(nodeData.id);
    });

    // Delete button
    el.querySelector('[data-delete-node]')?.addEventListener('click', () => {
        removeNode(nodeData.id);
    });

    // Connection handles
    setupConnectionHandles(el, nodeData.id);

    // Drag to reposition
    let isDragging = false;
    let startX, startY, origX, origY;

    el.addEventListener('mousedown', (e) => {
        if (e.target.closest('[data-handle]') || e.target.closest('[data-delete-node]')) return;
        isDragging = true;
        startX = e.clientX;
        startY = e.clientY;
        origX = parseInt(el.style.left) || 0;
        origY = parseInt(el.style.top) || 0;
        el.style.zIndex = '100';
    });

    document.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        const dx = e.clientX - startX;
        const dy = e.clientY - startY;
        el.style.left = (origX + dx) + 'px';
        el.style.top = (origY + dy) + 'px';
        scheduleRenderEdges();
    });

    document.addEventListener('mouseup', () => {
        if (!isDragging) return;
        isDragging = false;
        el.style.zIndex = '';
        const node = flowState.nodes.find((n) => n.id === nodeData.id);
        if (node) {
            node.position = { x: parseInt(el.style.left) || 0, y: parseInt(el.style.top) || 0 };
            markDirty();
            syncToState();
        }
    });

    nodesContainer.appendChild(el);
}

function setupConnectionHandles(el, nodeId) {
    el.querySelectorAll('[data-handle="output"]').forEach((handle) => {
        handle.addEventListener('mousedown', (e) => {
            e.stopPropagation();
            draggingConnection = { sourceId: nodeId };
        });
    });

    el.querySelectorAll('[data-handle="input"]').forEach((handle) => {
        handle.addEventListener('mouseup', (e) => {
            e.stopPropagation();
            if (draggingConnection && draggingConnection.sourceId !== nodeId) {
                addEdge(draggingConnection.sourceId, nodeId);
            }
            draggingConnection = null;
        });
    });
}

/* ── Edges ── */

function addEdge(sourceId, targetId, sourceHandle) {
    const exists = flowState.edges.find(
        (e) => e.source === sourceId && e.target === targetId
    );
    if (exists) return;

    const edge = {
        id: `edge_${sourceId}_${targetId}`,
        source: sourceId,
        target: targetId,
        sourceHandle: sourceHandle || 'output_1',
    };

    flowState.edges.push(edge);
    updateBadges();
    scheduleRenderEdges();
    markDirty();
    syncToState();
}

/* ── Node Removal ── */

function removeNode(nodeId) {
    flowState.nodes = flowState.nodes.filter((n) => n.id !== nodeId);
    flowState.edges = flowState.edges.filter(
        (e) => e.source !== nodeId && e.target !== nodeId
    );

    const el = nodesContainer.querySelector(`[data-node-id="${nodeId}"]`);
    if (el) el.remove();

    if (flowState.nodes.length === 0) {
        nodesContainer.insertAdjacentHTML(
            'beforeend',
            '<div id="empty-canvas-hint" class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-center"><p class="text-sm font-medium text-text-muted">Drag a node here to start building your flow</p></div>',
        );
    }

    updateBadges();
    scheduleRenderEdges();
    markDirty();
    syncToState();
}

/* ── Render All ── */

function renderAllNodes() {
    nodesContainer.querySelectorAll('.flow-node').forEach((n) => n.remove());

    if (flowState.nodes.length === 0) {
        if (!document.getElementById('empty-canvas-hint')) {
            nodesContainer.insertAdjacentHTML(
                'beforeend',
                '<div id="empty-canvas-hint" class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-center"><p class="text-sm font-medium text-text-muted">Drag a node here to start building your flow</p></div>',
            );
        }
        return;
    }

    const hint = document.getElementById('empty-canvas-hint');
    if (hint) hint.remove();

    flowState.nodes.forEach((node) => renderNode(node));
}

/* ── Config Panel ── */

function setupConfigPanel() {
    const panel = document.getElementById('node-config-panel');
    const closeBtn = document.getElementById('close-config-panel');

    closeBtn?.addEventListener('click', () => {
        panel?.classList.add('hidden');
        selectedNode = null;
    });
}

function openConfigPanel(nodeId) {
    const node = flowState.nodes.find((n) => n.id === nodeId);
    if (!node) return;

    const config = nodeConfig();
    if (!config?.buildForm) return;

    selectedNode = nodeId;
    const panel = document.getElementById('node-config-panel');
    const title = document.getElementById('config-panel-title');
    const body = document.getElementById('config-panel-body');

    if (!panel || !title || !body) return;

    title.textContent = `Configure: ${node.data.label || node.type}`;
    body.innerHTML = config.buildForm(node, { nodes: flowState.nodes });

    panel.classList.remove('hidden');

    body.querySelector('[data-save-config]')?.addEventListener('click', () => {
        saveConfigFromForm(nodeId, body);
    });

    body.querySelector('[data-cancel-config]')?.addEventListener('click', () => {
        panel.classList.add('hidden');
        selectedNode = null;
    });
}

function saveConfigFromForm(nodeId, root) {
    const node = flowState.nodes.find((n) => n.id === nodeId);
    const config = nodeConfig();
    if (!node || !config?.applyForm) return;

    const validation = config.validateForm?.(node, root);
    if (validation && !validation.valid) {
        showStatus(validation.errors[0] || 'Please fix the highlighted fields.', 'error');
        return;
    }

    config.applyForm(node, root);

    const el = nodesContainer.querySelector(`[data-node-id="${nodeId}"]`);
    if (el) {
        const preview = el.querySelector('.node-preview p');
        if (preview) {
            preview.textContent = getNodePreview(node);
        }
    }

    document.getElementById('node-config-panel')?.classList.add('hidden');
    selectedNode = null;
    markDirty();
    syncToState();
}

/* ── Toolbar Actions ── */

function setupToolbarActions() {
    document.querySelector('[data-action="save-flow"]')?.addEventListener('click', saveFlow);
    document.querySelector('[data-action="export-flow"]')?.addEventListener('click', exportFlow);
    document.querySelector('[data-action="import-flow"]')?.addEventListener('click', () => {
        const modal = document.getElementById('modal-import-flow');
        modal?.classList.remove('hidden');
        modal?.classList.add('flex');
    });

    document.querySelector('[data-action="zoom-in"]')?.addEventListener('click', () => applyCanvasZoom(canvasZoom + 0.1));
    document.querySelector('[data-action="zoom-out"]')?.addEventListener('click', () => applyCanvasZoom(canvasZoom - 0.1));

    const importForm = document.getElementById('import-flow-form');
    importForm?.addEventListener('submit', handleImport);
}

function applyCanvasZoom(nextZoom) {
    canvasZoom = Math.min(1.5, Math.max(0.6, nextZoom));
    if (nodesContainer) {
        nodesContainer.style.transformOrigin = 'top left';
        nodesContainer.style.transform = `scale(${canvasZoom})`;
    }
    scheduleRenderEdges();
}

async function saveFlow() {
    const saveUrl = canvasEl?.dataset.saveUrl;
    if (!saveUrl) return;

    showStatus('Saving flow...');

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const resp = await fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                nodes: flowState.nodes,
                edges: flowState.edges,
            }),
        });

        const result = await resp.json();

        if (resp.ok && result.success) {
            flowDirty = false;
            showStatus(`Flow saved! Nodes: ${result.node_count}`, 'success');
        } else if (result.errors) {
            const firstError = Object.values(result.errors).flat()[0];
            showStatus(firstError || result.message || 'Failed to save flow.', 'error');
        } else {
            showStatus(result.message || 'Failed to save flow.', 'error');
        }
    } catch (err) {
        showStatus('Error saving flow: ' + err.message, 'error');
    }
}

async function exportFlow() {
    const exportUrl = canvasEl?.dataset.exportUrl;
    if (!exportUrl) return;

    try {
        const resp = await fetch(exportUrl);
        const data = await resp.json();
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `chatbot-flow-${data.flow?.name || 'export'}.json`;
        a.click();
        URL.revokeObjectURL(url);
        showStatus('Flow exported!', 'success');
    } catch (err) {
        showStatus('Export failed: ' + err.message, 'error');
    }
}

async function handleImport(e) {
    e.preventDefault();

    const nameInput = document.getElementById('import-name');
    const fileInput = document.getElementById('import-file');
    const replaceCurrent = document.getElementById('import-replace-current')?.checked;

    if (!fileInput?.files?.[0]) return;
    if (!replaceCurrent && !nameInput?.value) return;

    const file = fileInput.files[0];
    const text = await file.text();

    try {
        const data = JSON.parse(text);
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        const importUrl = replaceCurrent
            ? canvasEl?.dataset.importFlowUrl
            : canvasEl?.dataset.importUrl;

        if (!importUrl) {
            showStatus('Import URL is not configured.', 'error');
            return;
        }

        const payload = replaceCurrent
            ? { exported_data: data.flow?.exported_data || data }
            : {
                name: nameInput.value.trim(),
                exported_data: data.flow?.exported_data || data,
            };

        const resp = await fetch(importUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const result = await resp.json();

        if (resp.ok && result.success) {
            if (replaceCurrent && result.data) {
                flowState = {
                    nodes: result.data.nodes || [],
                    edges: result.data.edges || [],
                };
                nodeIdCounter = flowState.nodes.length;
                renderAllNodes();
                scheduleRenderEdges();
                updateBadges();
                flowDirty = false;
                document.getElementById('modal-import-flow')?.classList.add('hidden');
                document.getElementById('modal-import-flow')?.classList.remove('flex');
                showStatus('Flow imported into the current builder.', 'success');
                return;
            }

            const editUrl = result.edit_url || (result.flow_id ? `/automation/chatbot/${result.flow_id}/edit` : null);
            if (editUrl) {
                window.location.href = editUrl;
            }
        } else {
            showStatus(result.message || 'Import failed.', 'error');
        }
    } catch (err) {
        showStatus('Import error: ' + err.message, 'error');
    }
}

/* ── Helpers ── */

function syncToState() {
    updateBadges();
}

function updateBadges() {
    const nodeBadge = document.getElementById('node-count-badge');
    const edgeBadge = document.getElementById('edge-count-badge');

    if (nodeBadge) nodeBadge.textContent = `Nodes: ${flowState.nodes.length}`;
    if (edgeBadge) edgeBadge.textContent = `Connections: ${flowState.edges.length}`;
}

function showStatus(message, type) {
    const el = document.getElementById('flow-builder-status');
    if (!el) return;

    el.textContent = message;
    el.className = `rounded-lg px-4 py-3 text-sm font-medium ${
        type === 'error'
            ? 'bg-red-50 text-red-700'
            : type === 'success'
            ? 'bg-green-50 text-green-700'
            : 'bg-blue-50 text-blue-700'
    }`;
    el.classList.remove('hidden');

    if (type !== 'error') {
        setTimeout(() => el.classList.add('hidden'), 3000);
    }
}

function field(label, id, value, type) {
    return `
        <div class="mb-3 flex flex-col gap-1">
            <label for="${id}" class="text-xs font-semibold text-text-body">${escapeHtml(label)}</label>
            <input type="${type}" id="${id}" value="${escapeHtml(String(value))}" class="w-full rounded-lg border border-divider bg-surface px-3 py-2 text-sm text-text-body focus:border-green-500 focus:outline-none">
        </div>
    `;
}

function selectField(label, id, value, options) {
    const opts = options.map(o =>
        `<option value="${escapeHtml(o.value)}" ${o.value === value ? 'selected' : ''}>${escapeHtml(o.label)}</option>`
    ).join('');
    return `
        <div class="mb-3 flex flex-col gap-1">
            <label for="${id}" class="text-xs font-semibold text-text-body">${escapeHtml(label)}</label>
            <select id="${id}" class="w-full rounded-lg border border-divider bg-surface px-3 py-2 text-sm text-text-body focus:border-green-500 focus:outline-none">${opts}</select>
        </div>
    `;
}

function textarea(label, id, value) {
    return `
        <div class="mb-3 flex flex-col gap-1">
            <label for="${id}" class="text-xs font-semibold text-text-body">${escapeHtml(label)}</label>
            <textarea id="${id}" rows="3" class="w-full rounded-lg border border-divider bg-surface px-3 py-2 text-sm text-text-body focus:border-green-500 focus:outline-none">${escapeHtml(String(value))}</textarea>
        </div>
    `;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function truncate(str, len) {
    return str.length > len ? str.substring(0, len) + '...' : str;
}
