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

    openConfig(index) {
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
      };

      if ((node.type === 'templateMessage' || node.type === 'condition' || node.type === 'enhancedCondition') && this.templatesUrl && !this.templates) {
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

    nodeEl(node, index) {
      const meta = this.meta[node.type] || {};
      const label = node.data?.label || meta.label || node.type;
      const icon = meta.icon || 'play-circle-dark.svg';
      const preview = configApi.getPreview(node);

      const el = document.createElement('div');
      el.className = 'group relative z-10 w-full rounded-lg bg-[#2c3c5e] p-3 shadow-[0px_6px_4px_rgba(109,187,72,0.25)]';
      el.dataset.nodeId = node.id;
      el.innerHTML =
        '<div class="flex items-start gap-2">' +
          '<button type="button" class="flex min-w-0 flex-1 items-start gap-2 text-left" data-drip-configure="' + index + '">' +
            '<img src="' + ASSET + icon + '" alt="" class="mt-0.5 size-5 shrink-0" width="20" height="20">' +
            '<div class="min-w-0 flex-1">' +
              '<span class="block text-sm font-medium leading-[1.5] text-[#eaecef]"></span>' +
              '<p class="mt-1 text-xs leading-[1.4] text-[#eaecef]/60"></p>' +
            '</div>' +
          '</button>' +
          '<button type="button" data-remove-node="' + index + '" class="shrink-0 rounded bg-[rgba(255,0,0,0.15)] p-1.5 opacity-70 transition-opacity hover:opacity-100 group-hover:opacity-100" aria-label="Remove step">' +
            '<img src="' + ASSET + 'trash-white.svg" alt="" class="size-4" width="16" height="16">' +
          '</button>' +
        '</div>';
      el.querySelector('[data-drip-configure] span').textContent = label;
      el.querySelector('p').textContent = preview;
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
          fragment.appendChild(connectorEl());
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
        })
        .catch(() => this.render());
    }

    save() {
      if (!this.saveUrl || this.saving) {
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
        if (window.showAppAlert) {
          window.showAppAlert(flowErrors[0], 'Cannot save flow');
        }
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
        body: JSON.stringify({ nodes: this.nodes, edges: [] }),
      })
        .then((response) => response.json().then((body) => ({ ok: response.ok, status: response.status, body })))
        .then(({ ok, body }) => {
          if (!ok || !body?.success) {
            const message = body?.message || body?.errors?.[Object.keys(body?.errors || {})[0]]?.[0] || 'Unable to save flow';
            throw new Error(message);
          }
          this.dirty = false;
          if (window.showAppAlert) {
            window.showAppAlert('Drip flow saved successfully.', 'Saved');
          }
        })
        .catch((error) => {
          if (window.showAppAlert) {
            window.showAppAlert(error.message || 'Unable to save flow.', 'Save failed');
          }
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
