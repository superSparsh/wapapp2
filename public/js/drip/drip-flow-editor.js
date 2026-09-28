/**
 * Vertical drip flow editor with per-node configuration panels.
 */
(function () {
  'use strict';

  const ASSET = '/images/automation/';
  const configApi = window.AutomationNodeConfig;

  function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  }

  function uid() {
    return 'node_' + Math.random().toString(36).slice(2, 10);
  }

  function connectorEl() {
    const el = document.createElement('div');
    el.className = 'flex h-8 w-px items-center justify-center';
    el.innerHTML = '<img src="' + ASSET + 'flow-connector-line.svg" alt="" class="h-8 w-px" width="1" height="32">';
    return el;
  }

  function addButtonEl(index) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'relative z-10 flex size-10 items-center justify-center rounded-full border border-solid border-green-500 bg-elevated p-2 transition-transform hover:scale-105';
    btn.setAttribute('aria-label', 'Add step');
    btn.dataset.dripInsert = String(index);
    btn.innerHTML = '<img src="' + ASSET + 'add-circle.svg" alt="" class="size-6" width="24" height="24">';
    return btn;
  }

  class DripFlowEditor {
    constructor(root) {
      this.root = root;
      this.container = root.querySelector('[data-drip-flow-nodes]');
      this.picker = root.querySelector('[data-drip-node-picker]');
      this.saveBtn = root.querySelector('[data-drip-save-flow]');
      this.configPanel = document.querySelector('[data-drip-node-config-panel]');
      this.configTitle = document.querySelector('[data-drip-config-title]');
      this.configBody = document.querySelector('[data-drip-config-body]');
      this.saveUrl = root.dataset.saveUrl || '';
      this.loadUrl = root.dataset.loadUrl || '';
      this.templatesUrl = root.dataset.templatesUrl || '';
      this.triggerLabel = root.dataset.triggerLabel || 'Trigger';
      this.meta = this.buildMeta(root.dataset.nodeTypes || '[]');
      this.audiences = this.buildAudiences(root.dataset.audiences || '[]');
      this.nodes = [];
      this.insertAt = null;
      this.configIndex = null;
      this.templates = null;
      this.templatesLoading = false;
      this.dirty = false;
      this.saving = false;

      if (!this.container || !configApi) {
        return;
      }

      if (this.picker && this.picker.parentElement !== document.body) {
        this.pickerPlaceholder = document.createComment('drip-node-picker-anchor');
        this.picker.parentElement?.insertBefore(this.pickerPlaceholder, this.picker);
        document.body.appendChild(this.picker);
      }

      this.container.addEventListener('click', (event) => this.onStackClick(event));
      this.picker?.addEventListener('click', (event) => this.onPickerClick(event));
      this.saveBtn?.addEventListener('click', () => this.save());

      document.querySelector('[data-drip-config-close]')?.addEventListener('click', () => this.closeConfig());

      root.querySelectorAll('[data-drip-picker-category]').forEach((tab) => {
        tab.addEventListener('click', () => this.switchCategory(tab.dataset.dripPickerCategory));
      });

      document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
          return;
        }
        if (this.picker && !this.picker.classList.contains('hidden')) {
          this.closePicker();
        }
        if (this.configPanel && !this.configPanel.classList.contains('hidden')) {
          this.closeConfig();
        }
      });

      this.load();
    }

    buildMeta(json) {
      const map = Object.create(null);
      try {
        JSON.parse(json).forEach((item) => {
          map[item.type] = { label: item.label, icon: item.icon || 'message-notif.svg' };
        });
      } catch (_) {
        // ignore malformed payload
      }
      return map;
    }

    buildAudiences(json) {
      try {
        const parsed = JSON.parse(json);
        return Array.isArray(parsed) ? parsed : [];
      } catch (_) {
        return [];
      }
    }

    onStackClick(event) {
      const branchBtn = event.target.closest('[data-drip-branch]');
      if (branchBtn) {
        const nodeCard = branchBtn.closest('[data-node-id]');
        const configureBtn = nodeCard?.querySelector('[data-drip-configure]');
        const index = Number(configureBtn?.dataset.dripConfigure);
        if (!Number.isNaN(index)) {
          this.openConfig(index, branchBtn.dataset.dripBranch === 'no' ? 'cfg-no-target' : 'cfg-yes-target');
        }
        return;
      }

      const configureBtn = event.target.closest('[data-drip-configure]');
      if (configureBtn) {
        const index = Number(configureBtn.dataset.dripConfigure);
        if (!Number.isNaN(index)) {
          this.openConfig(index);
        }
        return;
      }

      const insertBtn = event.target.closest('[data-drip-insert]');
      if (insertBtn) {
        this.openPicker(Number(insertBtn.dataset.dripInsert));
        return;
      }

      const removeBtn = event.target.closest('[data-remove-node]');
      if (removeBtn) {
        event.stopPropagation();
        const index = Number(removeBtn.dataset.removeNode);
        if (Number.isNaN(index)) {
          return;
        }

        const node = this.nodes[index];
        const label = node?.data?.label || node?.type || 'this step';
        const confirmRemove = () => {
          if (typeof window.showAppConfirm === 'function') {
            return window.showAppConfirm(
              'Remove "' + label + '" from this automation flow?',
              'Remove action',
              'Remove',
              'danger',
            );
          }
          return Promise.resolve(window.confirm('Remove this step from the flow?'));
        };

        confirmRemove().then((confirmed) => {
          if (!confirmed) {
            return;
          }
          this.nodes.splice(index, 1);
          this.dirty = true;
          if (this.configIndex === index) {
            this.closeConfig();
          }
          this.render();
        });
      }
    }

    onPickerClick(event) {
      if (event.target === this.picker) {
        this.closePicker();
        return;
      }

      if (event.target.closest('[data-drip-picker-close]')) {
        this.closePicker();
        return;
      }

      const addBtn = event.target.closest('[data-drip-add-node]');
      if (!addBtn || addBtn.disabled) {
        return;
      }

      this.addNode(addBtn.dataset.dripAddNode, addBtn.dataset.dripNodeLabel || addBtn.dataset.dripAddNode);
    }

    switchCategory(category) {
      this.root.querySelectorAll('[data-drip-picker-category]').forEach((tab) => {
        const active = tab.dataset.dripPickerCategory === category;
        tab.classList.toggle('bg-green-100', active);
        tab.classList.toggle('text-text-primary', active);
        tab.classList.toggle('bg-elevated', !active);
      });

      this.root.querySelectorAll('[data-drip-picker-category-item]').forEach((item) => {
        item.classList.toggle('hidden', item.dataset.dripPickerCategoryItem !== category);
      });
    }

    openPicker(index) {
      this.insertAt = index;
      if (!this.picker) {
        return;
      }
      if (this.picker.parentElement !== document.body) {
        document.body.appendChild(this.picker);
      }
      this.picker.classList.remove('hidden');
      this.picker.classList.add('flex');
      this.picker.setAttribute('aria-hidden', 'false');
      document.body.classList.add('overflow-hidden');
    }

    closePicker() {
      if (!this.picker) {
        return;
      }
      this.picker.classList.add('hidden');
      this.picker.classList.remove('flex');
      this.picker.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('overflow-hidden');
      this.insertAt = null;
    }

    addNode(type, label) {
      const node = {
        id: uid(),
        type,
        data: configApi.defaultData(label),
      };

      const index = this.insertAt ?? this.nodes.length;
      this.nodes.splice(index, 0, node);
      this.dirty = true;
      this.closePicker();
      this.render();
      this.openConfig(index);
    }

    ensureTemplates() {
      if (this.templates || this.templatesLoading || !this.templatesUrl) {
        return Promise.resolve(this.templates || []);
      }

      this.templatesLoading = true;

      return fetch(this.templatesUrl, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
        .then((response) => (response.ok ? response.json() : Promise.reject()))
        .then((payload) => {
          this.templates = (payload.items || []).map((item) => ({
            value: item.code || item.value || item.name || '',
            label: item.name
              ? item.name + (item.code ? ' (' + item.code + ')' : '')
              : (item.label || item.code || ''),
          })).filter((item) => item.value);
          this.hydrateTemplateDisplayNames();
          return this.templates;
        })
        .catch(() => {
          this.templates = [];
          return this.templates;
        })
        .finally(() => {
          this.templatesLoading = false;
        });
    }

    openConfig(index, focusFieldId) {
      const node = this.nodes[index];
      if (!node || !this.configPanel || !this.configBody || !this.configTitle) {
        return;
      }

      this.configIndex = index;
      this.configTitle.textContent = 'Configure: ' + (node.data?.label || node.type);

      const renderPanel = () => {
        this.configBody.innerHTML = configApi.buildForm(node, {
          allNodes: this.nodes,
          templates: this.templates || [],
          audiences: this.audiences || [],
        });

        this.configPanel.classList.remove('hidden');

        if (typeof configApi.bindFormEvents === 'function') {
          configApi.bindFormEvents(node, this.configBody, {
            allNodes: this.nodes,
            templates: this.templates || [],
            audiences: this.audiences || [],
          });
        }

        this.configBody.querySelector('[data-save-config]')?.addEventListener('click', () => this.saveConfig());
        this.configBody.querySelector('[data-cancel-config]')?.addEventListener('click', () => this.closeConfig());

        if (focusFieldId) {
          const focusEl = this.configBody.querySelector('#' + focusFieldId);
          if (focusEl) {
            focusEl.scrollIntoView({ block: 'center', behavior: 'smooth' });
            focusEl.focus();
          }
        }
      };

      if ((node.type === 'templateMessage' || node.type === 'whatsappFlowTemplate' || node.type === 'condition' || node.type === 'enhancedCondition') && this.templatesUrl && !this.templates) {
        this.ensureTemplates().then(renderPanel);
        return;
      }

      renderPanel();
    }

    saveConfig() {
      if (this.configIndex === null || !this.configBody) {
        return;
      }

      const node = this.nodes[this.configIndex];
      if (!node) {
        return;
      }

      const validation = configApi.validateForm(node, this.configBody);
      if (!validation.valid) {
        if (window.showAppAlert) {
          window.showAppAlert(validation.errors[0] || 'Please fix the highlighted fields.', 'Validation');
        }
        return;
      }

      configApi.applyForm(node, this.configBody);
      this.dirty = true;
      this.closeConfig();
      this.render();
    }

    closeConfig() {
      this.configIndex = null;
      this.configPanel?.classList.add('hidden');
      if (this.configBody) {
        this.configBody.innerHTML = '';
      }
    }

    triggerEl() {
      const el = document.createElement('div');
      el.className = 'relative z-10 rounded-lg border-2 border-solid border-green-500 bg-green-50 p-3 shadow-[0px_6px_4px_rgba(109,187,72,0.25)]';
      el.setAttribute('data-drip-trigger-node', '');
      el.innerHTML =
        '<div class="flex items-center gap-2">' +
          '<img src="' + ASSET + 'play-circle.svg" alt="" class="size-5" width="20" height="20">' +
          '<span class="text-sm font-medium leading-[1.5] whitespace-nowrap text-text-subtle"></span>' +
        '</div>';
      el.querySelector('span').textContent = this.triggerLabel;
      return el;
    }

    placeholderEl() {
      const el = document.createElement('div');
      el.className = 'relative z-10 rounded-lg bg-[#2c3c5e] p-3 shadow-[0px_6px_4px_rgba(109,187,72,0.25)]';
      el.innerHTML =
        '<div class="flex items-center gap-2">' +
          '<img src="' + ASSET + 'play-circle-dark.svg" alt="" class="size-5" width="20" height="20">' +
          '<span class="text-sm font-medium leading-[1.5] whitespace-nowrap text-[#eaecef]">Add nodes to your flow</span>' +
        '</div>';
      return el;
    }

    resolveBranchTargetId(node, index, key) {
      const raw = String(node?.data?.[key] || '').trim();
      const fallback = key === 'no_target' ? 'end' : 'next';
      const value = raw || fallback;

      if (value === 'end') {
        return null;
      }
      if (value === 'next') {
        return this.nextLinearNode(index)?.id || null;
      }
      if (value === node.id) {
        return null;
      }
      const exists = this.nodes.some((candidate) => candidate.id === value);
      return exists ? value : null;
    }

    nextLinearNode(index) {
      const skipped = new Set();
      this.nodes.forEach((node) => {
        if (node.type !== 'condition' && node.type !== 'enhancedCondition') {
          return;
        }
        ['yes_target', 'no_target'].forEach((key) => {
          const target = String(node.data?.[key] || '').trim();
          if (target && target !== 'end' && target !== 'next') {
            skipped.add(target);
          }
        });
      });

      return this.nodes.slice(index + 1).find((candidate) => !skipped.has(candidate.id)) || null;
    }

    resolveBranchLabel(node, key) {
      const raw = String(node?.data?.[key] || '').trim() || (key === 'no_target' ? 'end' : 'next');
      if (raw === 'end') {
        return 'End';
      }
      if (raw === 'next') {
        return 'Next step';
      }
      const target = this.nodes.find((candidate) => candidate.id === raw);
      if (!target) {
        return raw;
      }
      if (target.type === 'templateMessage') {
        return target.data?.template_display_name || target.data?.template_name || target.data?.label || target.type;
      }
      return target.data?.label || this.meta[target.type]?.label || target.type || raw;
    }

    resolveNoBranchLabel(node) {
      return this.resolveBranchLabel(node, 'no_target');
    }

    conditionBranchEl(node) {
      const el = document.createElement('div');
      el.className = 'relative z-10 mt-2 flex w-full items-stretch gap-2 border-t border-solid border-white/10 pt-2';
      el.setAttribute('data-drip-condition-branches', '');
      el.innerHTML =
        '<button type="button" class="flex min-w-0 flex-1 items-center gap-1.5 text-left" data-drip-branch="yes" title="Configure Yes path">' +
          '<span class="inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-[#22c55e] text-[10px] font-bold leading-none text-white">Y</span>' +
          '<span class="truncate text-[11px] font-medium leading-[1.3] text-[#86efac]" data-drip-yes-label></span>' +
        '</button>' +
        '<button type="button" class="flex min-w-0 flex-1 items-center justify-end gap-1.5 text-right" data-drip-branch="no" title="Configure No path">' +
          '<span class="truncate text-[11px] font-medium leading-[1.3] text-[#fca5a5]" data-drip-no-label></span>' +
          '<span class="inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-[#ef4444] text-[10px] font-bold leading-none text-white">N</span>' +
        '</button>';
      el.querySelector('[data-drip-yes-label]').textContent = this.resolveBranchLabel(node, 'yes_target');
      el.querySelector('[data-drip-no-label]').textContent = this.resolveBranchLabel(node, 'no_target');
      return el;
    }

    conditionConnectorEl() {
      const el = document.createElement('div');
      el.className = 'relative z-10 flex w-full flex-col items-center';
      el.setAttribute('data-drip-condition-connector', '');
      el.innerHTML =
        '<div class="flex w-full max-w-[220px] items-start justify-between px-1">' +
          '<div class="flex w-10 flex-col items-center">' +
            '<span class="mb-1 inline-flex size-5 items-center justify-center rounded-full bg-[#22c55e] text-[10px] font-bold leading-none text-white">Y</span>' +
            '<div class="flex h-8 w-px items-center justify-center">' +
              '<img src="' + ASSET + 'flow-connector-line.svg" alt="" class="h-8 w-px" width="1" height="32">' +
            '</div>' +
          '</div>' +
          '<div class="flex w-10 flex-col items-center opacity-70">' +
            '<span class="mb-1 inline-flex size-5 items-center justify-center rounded-full bg-[#ef4444] text-[10px] font-bold leading-none text-white">N</span>' +
            '<div class="h-8 w-px border-l border-dashed border-[#ef4444]/70"></div>' +
          '</div>' +
        '</div>';
      return el;
    }

    nodeEl(node, index) {
      const meta = this.meta[node.type] || {};
      let label = node.data?.label || meta.label || node.type;
      if (node.type === 'templateMessage') {
        const templateTitle = node.data?.template_display_name || node.data?.template_name;
        if (templateTitle) {
          label = templateTitle;
        }
      }
      const icon = meta.icon || 'play-circle-dark.svg';
      const preview = configApi.getPreview(node);
      const isCondition = node.type === 'condition' || node.type === 'enhancedCondition';

      const el = document.createElement('div');
      el.className = 'group relative z-10 w-full rounded-lg bg-[#2c3c5e] p-3 shadow-[0px_6px_4px_rgba(109,187,72,0.25)]';
      el.dataset.nodeId = node.id;
      el.innerHTML =
        '<div class="flex items-start gap-2">' +
          '<button type="button" class="flex min-w-0 flex-1 items-start gap-2 text-left" data-drip-configure="' + index + '">' +
            '<img src="' + ASSET + icon + '" alt="" class="mt-0.5 size-5 shrink-0" width="20" height="20">' +
            '<div class="min-w-0 flex-1">' +
              '<span class="block truncate text-sm font-medium leading-[1.5] text-[#eaecef]"></span>' +
              '<p class="mt-1 truncate text-xs leading-[1.4] text-[#eaecef]/60"></p>' +
            '</div>' +
          '</button>' +
          '<button type="button" data-remove-node="' + index + '" class="shrink-0 rounded bg-[rgba(255,0,0,0.15)] p-1.5 opacity-70 transition-opacity hover:opacity-100 group-hover:opacity-100" aria-label="Remove step">' +
            '<img src="' + ASSET + 'trash-white.svg" alt="" class="size-4" width="16" height="16">' +
          '</button>' +
        '</div>';
      el.querySelector('[data-drip-configure] span').textContent = label;
      const previewEl = el.querySelector('p');
      // Avoid repeating the same template name in title + preview.
      if (node.type === 'templateMessage' && node.data?.template_display_name && node.data?.template_name) {
        previewEl.textContent = node.data.template_name !== node.data.template_display_name
          ? ('Code: ' + node.data.template_name)
          : 'WhatsApp template';
      } else {
        previewEl.textContent = preview;
      }
      if (isCondition) {
        el.appendChild(this.conditionBranchEl(node));
      }
      return el;
    }

    render() {
      const fragment = document.createDocumentFragment();
      fragment.appendChild(this.triggerEl());
      fragment.appendChild(connectorEl());
      fragment.appendChild(addButtonEl(0));

      if (this.nodes.length === 0) {
        fragment.appendChild(connectorEl());
        fragment.appendChild(this.placeholderEl());
      } else {
        this.nodes.forEach((node, index) => {
          fragment.appendChild(connectorEl());
          fragment.appendChild(this.nodeEl(node, index));
          if (node.type === 'condition' || node.type === 'enhancedCondition') {
            fragment.appendChild(this.conditionConnectorEl());
          } else {
            fragment.appendChild(connectorEl());
          }
          fragment.appendChild(addButtonEl(index + 1));
        });
      }

      this.container.replaceChildren(fragment);
      this.updateSaveState();
    }

    updateSaveState() {
      if (!this.saveBtn) {
        return;
      }
      this.saveBtn.disabled = this.saving;
      this.saveBtn.setAttribute('aria-busy', this.saving ? 'true' : 'false');
    }

    hydrateTemplateDisplayNames() {
      if (!Array.isArray(this.templates) || this.templates.length === 0) {
        return false;
      }

      let changed = false;
      this.nodes.forEach((node) => {
        if (node.type !== 'templateMessage' || !node.data) {
          return;
        }
        const code = String(node.data.template_name || node.data.templateCode || '').trim();
        if (!code) {
          return;
        }
        const match = this.templates.find((item) => item.value === code);
        if (!match) {
          return;
        }
        const label = String(match.label || '').replace(/\s*\([^)]*\)\s*$/, '').trim() || match.label || code;
        if (node.data.template_display_name !== label) {
          node.data.template_display_name = label;
          changed = true;
        }
      });

      return changed;
    }

    load() {
      if (!this.loadUrl) {
        this.render();
        return;
      }

      fetch(this.loadUrl, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
        .then((response) => (response.ok ? response.json() : Promise.reject()))
        .then((payload) => {
          const data = payload?.data || {};
          this.nodes = Array.isArray(data.nodes) ? data.nodes : [];
          this.dirty = false;
          this.render();
          if (this.templatesUrl && this.nodes.some((n) => n.type === 'templateMessage')) {
            this.ensureTemplates().then(() => {
              if (this.hydrateTemplateDisplayNames()) {
                this.render();
              }
            });
          }
        })
        .catch(() => this.render());
    }

    buildEdges() {
      const edges = [];

      this.nodes.forEach((node, index) => {
        if (node.type === 'condition' || node.type === 'enhancedCondition') {
          const yesId = this.resolveBranchTargetId(node, index, 'yes_target');
          if (yesId) {
            edges.push({ source: node.id, target: yesId, sourceHandle: 'yes' });
          }
          const noId = this.resolveBranchTargetId(node, index, 'no_target');
          if (noId) {
            edges.push({ source: node.id, target: noId, sourceHandle: 'no' });
          }
          return;
        }

        const next = this.nextLinearNode(index);
        if (next) {
          edges.push({ source: node.id, target: next.id });
        }
      });

      return edges;
    }

    save() {
      const notify = (message, title) => {
        if (typeof window.showAppAlert === 'function') {
          window.showAppAlert(message, title);
          return;
        }
        window.alert((title ? title + ': ' : '') + message);
      };

      if (!this.saveUrl) {
        notify('Save URL is missing. Refresh the page and try again.', 'Save failed');
        return;
      }
      if (this.saving) {
        return;
      }

      const flowErrors = [];
      this.nodes.forEach((node) => {
        const result = configApi.validateNodeData(node);
        if (!result.valid) {
          flowErrors.push.apply(flowErrors, result.errors);
        }
      });

      if (flowErrors.length) {
        notify(flowErrors[0], 'Cannot save flow');
        return;
      }

      this.saving = true;
      this.updateSaveState();

      fetch(this.saveUrl, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ nodes: this.nodes, edges: this.buildEdges() }),
      })
        .then(async (response) => {
          let body = null;
          const contentType = response.headers.get('content-type') || '';
          if (contentType.includes('application/json')) {
            body = await response.json();
          } else {
            const text = await response.text();
            throw new Error(text?.trim()?.slice(0, 180) || ('Save failed (HTTP ' + response.status + ')'));
          }
          return { ok: response.ok, status: response.status, body };
        })
        .then(({ ok, body }) => {
          if (!ok || !body?.success) {
            const message = body?.message
              || body?.errors?.[Object.keys(body?.errors || {})[0]]?.[0]
              || 'Unable to save flow';
            throw new Error(message);
          }
          this.dirty = false;
          notify('Drip flow saved successfully.', 'Saved');
        })
        .catch((error) => {
          notify(error.message || 'Unable to save flow.', 'Save failed');
        })
        .finally(() => {
          this.saving = false;
          this.updateSaveState();
        });
    }
  }

  function boot() {
    document.querySelectorAll('[data-drip-flow-editor]').forEach((root) => new DripFlowEditor(root));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
