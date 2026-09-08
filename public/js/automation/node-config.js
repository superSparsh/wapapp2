/**
 * Shared automation node configuration forms (chatbot + drip).
 */
(function (global) {
  'use strict';

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = String(str ?? '');
    return div.innerHTML;
  }

  function truncate(str, len) {
    const value = String(str ?? '');
    return value.length > len ? value.substring(0, len) + '...' : value;
  }

  function field(label, id, value, type, required, placeholder) {
    const reqMark = required ? ' <span class="text-red-500" aria-hidden="true">*</span>' : '';
    const reqAttr = required ? ' required' : '';
    const phAttr = placeholder ? ' placeholder="' + escapeHtml(placeholder) + '"' : '';
    return (
      '<div class="mb-3 flex flex-col gap-1" data-cfg-field="' + id + '">' +
        '<label for="' + id + '" class="text-xs font-semibold text-text-body">' + escapeHtml(label) + reqMark + '</label>' +
        '<input type="' + type + '" id="' + id + '" value="' + escapeHtml(String(value ?? '')) + '"' + reqAttr + phAttr + ' class="w-full rounded-lg border border-divider bg-surface px-3 py-2 text-sm text-text-body focus:border-green-500 focus:outline-none">' +
        '<p class="hidden text-xs text-red-500" data-cfg-error></p>' +
      '</div>'
    );
  }

  function selectField(label, id, value, options, required) {
    const reqMark = required ? ' <span class="text-red-500" aria-hidden="true">*</span>' : '';
    const reqAttr = required ? ' required' : '';
    const opts = options.map(function (option) {
      const selected = String(option.value) === String(value) ? ' selected' : '';
      return '<option value="' + escapeHtml(String(option.value)) + '"' + selected + '>' + escapeHtml(option.label) + '</option>';
    }).join('');

    return (
      '<div class="mb-3 flex flex-col gap-1" data-cfg-field="' + id + '">' +
        '<label for="' + id + '" class="text-xs font-semibold text-text-body">' + escapeHtml(label) + reqMark + '</label>' +
        '<select id="' + id + '"' + reqAttr + ' class="w-full rounded-lg border border-divider bg-surface px-3 py-2 text-sm text-text-body focus:border-green-500 focus:outline-none">' + opts + '</select>' +
        '<p class="hidden text-xs text-red-500" data-cfg-error></p>' +
      '</div>'
    );
  }

  function textarea(label, id, value, required, placeholder) {
    const reqMark = required ? ' <span class="text-red-500" aria-hidden="true">*</span>' : '';
    const reqAttr = required ? ' required' : '';
    const phAttr = placeholder ? ' placeholder="' + escapeHtml(placeholder) + '"' : '';
    return (
      '<div class="mb-3 flex flex-col gap-1" data-cfg-field="' + id + '">' +
        '<label for="' + id + '" class="text-xs font-semibold text-text-body">' + escapeHtml(label) + reqMark + '</label>' +
        '<textarea id="' + id + '" rows="3"' + reqAttr + phAttr + ' class="w-full rounded-lg border border-divider bg-surface px-3 py-2 text-sm text-text-body focus:border-green-500 focus:outline-none">' + escapeHtml(String(value ?? '')) + '</textarea>' +
        '<p class="hidden text-xs text-red-500" data-cfg-error></p>' +
      '</div>'
    );
  }

  function defaultData(label) {
    return {
      label: label || '',
      message: '',
      keywords: '',
      options: [],
      conditions: [],
      quick_replies: [],
      variables: [],
      delay_seconds: 60,
      preset_delay: '1 minute',
      delay_value: 1,
      delay_unit: 'minutes',
      variable_name: '',
      timeout: 120,
      url: '',
      method: 'GET',
      headers: {},
      body: '',
      template_name: '',
      media_url: '',
      media_type: 'image',
      interactive_type: 'button',
      condition_type: 'whatsapp_read',
      target_template: '',
      condition_wait: '1 day',
      wait_value: 1,
      wait_unit: 'days',
      condition_variable: '',
      condition_operator: 'equals',
      condition_value: '',
      function_name: '',
      target_node: '',
      operation_type: 'tag',
      tags: [],
      mail_list_id: '',
      target_list_id: '',
      field_name: '',
      field_value: '',
    };
  }

  function getPreview(node) {
    const data = node.data || {};
    switch (node.type) {
      case 'welcomeMessage':
        return data.message ? truncate(data.message, 60) : 'Click to configure...';
      case 'templateMessage':
        return data.template_name ? 'Template: ' + truncate(data.template_name, 40) : 'Click to configure...';
      case 'interactiveMessage':
        return data.interactive_type
          ? data.interactive_type + ': ' + (data.options || []).length + ' options'
          : 'Click to configure...';
      case 'mediaMessage':
        return data.media_url
          ? (data.media_type || 'image') + ': ' + truncate(data.media_url, 40)
          : 'Click to configure...';
      case 'condition':
      case 'enhancedCondition': {
        const cType = data.condition_type || (data.condition_variable ? 'custom_variable' : 'whatsapp_read');
        const waitStr = data.condition_wait || '1 day';
        const tplStr = data.target_template ? ' ("' + truncate(data.target_template, 20) + '")' : '';
        switch (cType) {
          case 'whatsapp_read':
            return 'If WhatsApp read' + tplStr + ' (wait: ' + waitStr + ')';
          case 'whatsapp_delivered':
            return 'If WhatsApp delivered' + tplStr + ' (wait: ' + waitStr + ')';
          case 'whatsapp_unread':
            return 'If WhatsApp unread' + tplStr + ' (wait: ' + waitStr + ')';
          case 'whatsapp_failed':
            return 'If WhatsApp failed' + tplStr;
          case 'whatsapp_reply':
            return 'If contact replied' + tplStr + ' (wait: ' + waitStr + ')';
          case 'custom_variable':
          default:
            return data.condition_variable
              ? data.condition_variable + ' ' + (data.condition_operator || '==') + ' ' + (data.condition_value || '')
              : 'Evaluate condition';
        }
      }
      case 'contactOperation': {
        const op = data.operation_type || 'tag';
        if (op === 'tag') {
          const tags = Array.isArray(data.tags) ? data.tags.join(', ') : (data.tags || '');
          return tags ? 'Tag: ' + truncate(tags, 40) : 'Tag contact';
        }
        if (op === 'copy') {
          return 'Copy contact to list' + (data.target_list_name ? ': ' + data.target_list_name : '');
        }
        if (op === 'move') {
          return 'Move contact to list' + (data.target_list_name ? ': ' + data.target_list_name : '');
        }
        if (op === 'update') {
          return 'Update: ' + (data.field_name || 'field') + ' = ' + (data.field_value || '');
        }
        return 'Operation: ' + op;
      }
      case 'httpRequest':
        return data.url ? (data.method || 'GET') + ' ' + truncate(data.url, 40) : 'Click to configure...';
      case 'functionCall':
        return data.function_name ? 'fn: ' + data.function_name : 'Click to configure...';
      case 'delay': {
        if (data.preset_delay && data.preset_delay !== 'custom') {
          return 'Wait ' + data.preset_delay;
        }
        if (data.delay_value && data.delay_unit) {
          return 'Wait ' + data.delay_value + ' ' + data.delay_unit;
        }
        if (data.delay_seconds) {
          const secs = Number(data.delay_seconds);
          if (secs >= 86400 && secs % 86400 === 0) return 'Wait ' + (secs / 86400) + ' day(s)';
          if (secs >= 3600 && secs % 3600 === 0) return 'Wait ' + (secs / 3600) + ' hour(s)';
          if (secs >= 60 && secs % 60 === 0) return 'Wait ' + (secs / 60) + ' minute(s)';
          return 'Wait ' + secs + ' second(s)';
        }
        return 'Click to configure wait time...';
      }
      case 'typingIndicator':
        return data.delay_seconds ? data.delay_seconds + ' seconds' : 'Click to configure...';
      case 'waitForResponse':
        return data.variable_name
          ? 'var: ' + data.variable_name + ', timeout: ' + (data.timeout || 120) + 's'
          : 'Click to configure...';
      case 'jumpToStep':
        return data.target_node ? 'Target: ' + data.target_node : 'Click to configure...';
      case 'carouselTemplate':
      case 'whatsappFlowTemplate':
        return 'Coming Soon';
      default:
        return data.message ? truncate(data.message, 60) : 'Click to configure...';
    }
  }

  function parseDurationToSeconds(val, unit) {
    const num = Math.max(1, parseInt(val, 10) || 1);
    switch (unit) {
      case 'seconds': return num;
      case 'minutes': return num * 60;
      case 'hours': return num * 3600;
      case 'days': return num * 86400;
      case 'weeks': return num * 604800;
      case 'months': return num * 2592000;
      default: return num * 60;
    }
  }

  function presetToSeconds(preset) {
    const map = {
      '1 minute': 60,
      '5 minutes': 300,
      '15 minutes': 900,
      '30 minutes': 1800,
      '1 hour': 3600,
      '2 hours': 7200,
      '4 hours': 14400,
      '8 hours': 28800,
      '12 hours': 43200,
      '1 day': 86400,
      '2 days': 172800,
      '3 days': 259200,
      '5 days': 432000,
      '1 week': 604800,
      '2 weeks': 1209600,
      '1 month': 2592000,
    };
    return map[preset] || null;
  }

  function buildForm(node, context) {
    context = context || {};
    const data = node.data || {};
    let html = field('Label', 'cfg-label', data.label || '', 'text', true);

    switch (node.type) {
      case 'welcomeMessage':
        html += textarea('Message', 'cfg-message', data.message || data.text || '', true);
        html += field('Keywords (comma-separated)', 'cfg-keywords', data.keywords || data.triggerKeyword || '', 'text', false);
        html += textarea('Quick Replies (one per line)', 'cfg-quick-replies', (data.quick_replies || []).join('\n'), false);
        break;

      case 'interactiveMessage':
        html += textarea('Message', 'cfg-message', data.message || '', true);
        html += selectField('Type', 'cfg-interactive-type', data.interactive_type || 'button', [
          { value: 'button', label: 'Button' },
          { value: 'list', label: 'List' },
        ], true);
        html += textarea('Options (one per line)', 'cfg-options', (data.options || []).join('\n'), true);
        break;

      case 'templateMessage':
        if (context.templates && context.templates.length) {
          html += selectField('Template', 'cfg-template-name', data.template_name || data.templateCode || '', context.templates, true);
        } else {
          html += field('Template Name', 'cfg-template-name', data.template_name || data.templateCode || '', 'text', true);
        }
        html += textarea('Variables / Parameters (one per line)', 'cfg-variables', (data.variables || []).join('\n'), false, 'e.g. {{1}} or parameter values');
        html += field('Keywords (comma-separated)', 'cfg-keywords', data.keywords || data.triggerKeyword || '', 'text', false);
        break;

      case 'mediaMessage':
        html += field('Media URL', 'cfg-media-url', data.media_url || '', 'url', true);
        html += selectField('Media Type', 'cfg-media-type', data.media_type || 'image', [
          { value: 'image', label: 'Image' },
          { value: 'video', label: 'Video' },
          { value: 'document', label: 'Document' },
          { value: 'audio', label: 'Audio' },
        ], true);
        html += textarea('Caption', 'cfg-message', data.message || '', false);
        break;

      case 'condition':
      case 'enhancedCondition': {
        const curType = data.condition_type || (data.condition_variable ? 'custom_variable' : 'whatsapp_read');
        html += selectField('Condition Criterion', 'cfg-condition-type', curType, [
          { value: 'whatsapp_read', label: 'WhatsApp Message Read' },
          { value: 'whatsapp_delivered', label: 'WhatsApp Message Delivered' },
          { value: 'whatsapp_unread', label: 'WhatsApp Message Unread' },
          { value: 'whatsapp_failed', label: 'WhatsApp Message Delivery Failed' },
          { value: 'whatsapp_reply', label: 'Contact Replied to Message' },
          { value: 'custom_variable', label: 'Custom Contact Field / Variable' },
        ], true);

        // Previous template selection
        const previousTemplates = [{ value: '', label: '-- Latest / Previous Template in Flow --' }];
        (context.allNodes || []).forEach(function (n) {
          if (n.type === 'templateMessage' && n.id !== node.id) {
            const tCode = n.data?.template_name || n.data?.templateCode || n.data?.label || n.id;
            previousTemplates.push({ value: tCode, label: 'Step: ' + (n.data?.label || n.id) + ' (' + tCode + ')' });
          }
        });

        const isWa = curType !== 'custom_variable';
        html += '<div id="cfg-group-wa-condition" class="' + (isWa ? '' : 'hidden') + '">';
        html += selectField('Target Template', 'cfg-target-template', data.target_template || '', previousTemplates, false);

        const waitOptions = [
          { value: '15 minutes', label: 'Wait up to 15 minutes' },
          { value: '1 hour', label: 'Wait up to 1 hour' },
          { value: '2 hours', label: 'Wait up to 2 hours' },
          { value: '4 hours', label: 'Wait up to 4 hours' },
          { value: '8 hours', label: 'Wait up to 8 hours' },
          { value: '12 hours', label: 'Wait up to 12 hours' },
          { value: '1 day', label: 'Wait up to 1 day' },
          { value: '2 days', label: 'Wait up to 2 days' },
          { value: '3 days', label: 'Wait up to 3 days' },
          { value: '5 days', label: 'Wait up to 5 days' },
          { value: '1 week', label: 'Wait up to 1 week' },
          { value: 'custom', label: 'Custom wait duration...' },
        ];
        html += selectField('Evaluation Window (Timeout)', 'cfg-condition-wait', data.condition_wait || '1 day', waitOptions, true);

        const isCustomWait = data.condition_wait === 'custom';
        html += '<div id="cfg-group-custom-wait" class="' + (isCustomWait ? '' : 'hidden') + ' flex gap-2">';
        html += '<div class="flex-1">' + field('Amount', 'cfg-wait-value', String(data.wait_value || 1), 'number', false) + '</div>';
        html += '<div class="flex-1">' + selectField('Unit', 'cfg-wait-unit', data.wait_unit || 'days', [
          { value: 'minutes', label: 'Minutes' },
          { value: 'hours', label: 'Hours' },
          { value: 'days', label: 'Days' },
          { value: 'weeks', label: 'Weeks' },
        ], false) + '</div>';
        html += '</div>';
        html += '</div>';

        // Custom variable condition group
        html += '<div id="cfg-group-var-condition" class="' + (!isWa ? '' : 'hidden') + '">';
        html += field('Contact Variable / Field', 'cfg-condition-variable', data.condition_variable || '', 'text', false, 'e.g. city, plan, status');
        html += selectField('Operator', 'cfg-condition-operator', data.condition_operator || 'equals', [
          { value: 'equals', label: 'Equals' },
          { value: 'not_equals', label: 'Not Equals' },
          { value: 'contains', label: 'Contains' },
          { value: 'greater_than', label: 'Greater Than' },
          { value: 'less_than', label: 'Less Than' },
          { value: 'is_empty', label: 'Is Empty' },
          { value: 'not_empty', label: 'Not Empty' },
        ], false);
        html += field('Value', 'cfg-condition-value', data.condition_value || '', 'text', false);
        html += '</div>';
        break;
      }

      case 'contactOperation': {
        const curOp = data.operation_type || 'tag';
        html += selectField('Operation Type', 'cfg-operation-type', curOp, [
          { value: 'tag', label: 'Tag Contact' },
          { value: 'copy', label: 'Copy Contact to Audience List' },
          { value: 'move', label: 'Move Contact to Audience List' },
          { value: 'update', label: 'Update Contact Field' },
        ], true);

        // Tag group
        const tagsVal = Array.isArray(data.tags) ? data.tags.join('\n') : String(data.tags || '');
        html += '<div id="cfg-group-tag" class="' + (curOp === 'tag' ? '' : 'hidden') + '">';
        html += textarea('Tags (one per line or comma-separated)', 'cfg-tags', tagsVal, false, 'e.g. VIP, Interested, FollowUp');
        html += '</div>';

        // Audience List group for Copy / Move
        const audienceOptions = [{ value: '', label: '-- Select Audience List --' }];
        (context.audiences || []).forEach(function (aud) {
          audienceOptions.push({ value: String(aud.id), label: aud.name || 'List #' + aud.id });
        });
        const selectedList = data.mail_list_id || data.target_list_id || '';
        html += '<div id="cfg-group-target-list" class="' + (curOp === 'copy' || curOp === 'move' ? '' : 'hidden') + '">';
        html += selectField('Target Audience List', 'cfg-target-list-id', selectedList, audienceOptions, false);
        html += '</div>';

        // Update Field group
        html += '<div id="cfg-group-update" class="' + (curOp === 'update' ? '' : 'hidden') + '">';
        html += field('Field Name', 'cfg-field-name', data.field_name || '', 'text', false, 'e.g. notes, status, budget');
        html += field('Field Value', 'cfg-field-value', data.field_value || '', 'text', false);
        html += '</div>';
        break;
      }

      case 'waitForResponse':
        html += field('Variable Name', 'cfg-variable-name', data.variable_name || '', 'text', true, 'e.g. user_reply');
        html += field('Timeout (seconds)', 'cfg-timeout', String(data.timeout || 120), 'number', true);
        break;

      case 'delay': {
        const presets = [
          { value: '1 minute', label: '1 minute' },
          { value: '5 minutes', label: '5 minutes' },
          { value: '15 minutes', label: '15 minutes' },
          { value: '30 minutes', label: '30 minutes' },
          { value: '1 hour', label: '1 hour' },
          { value: '2 hours', label: '2 hours' },
          { value: '4 hours', label: '4 hours' },
          { value: '8 hours', label: '8 hours' },
          { value: '12 hours', label: '12 hours' },
          { value: '1 day', label: '1 day' },
          { value: '2 days', label: '2 days' },
          { value: '3 days', label: '3 days' },
          { value: '5 days', label: '5 days' },
          { value: '1 week', label: '1 week' },
          { value: '2 weeks', label: '2 weeks' },
          { value: '1 month', label: '1 month' },
          { value: 'custom', label: 'Custom duration...' },
        ];
        const curPreset = data.preset_delay || (presetToSeconds(data.preset_delay) ? data.preset_delay : (data.delay_value ? 'custom' : '1 minute'));
        html += selectField('Wait Duration', 'cfg-preset-delay', curPreset, presets, true);

        const isCustomDelay = curPreset === 'custom';
        html += '<div id="cfg-group-custom-delay" class="' + (isCustomDelay ? '' : 'hidden') + ' flex gap-2">';
        html += '<div class="flex-1">' + field('Amount', 'cfg-delay-value', String(data.delay_value || 1), 'number', false) + '</div>';
        html += '<div class="flex-1">' + selectField('Unit', 'cfg-delay-unit', data.delay_unit || 'minutes', [
          { value: 'minutes', label: 'Minutes' },
          { value: 'hours', label: 'Hours' },
          { value: 'days', label: 'Days' },
          { value: 'weeks', label: 'Weeks' },
          { value: 'months', label: 'Months' },
        ], false) + '</div>';
        html += '</div>';
        html += '<input type="hidden" id="cfg-delay-seconds" value="' + (data.delay_seconds || 60) + '">';
        break;
      }

      case 'typingIndicator':
        html += field('Duration (seconds)', 'cfg-delay-seconds', String(data.delay_seconds || 2), 'number', true);
        break;

      case 'httpRequest':
        html += field('URL', 'cfg-url', data.url || '', 'url', true);
        html += selectField('Method', 'cfg-method', data.method || 'GET', [
          { value: 'GET', label: 'GET' },
          { value: 'POST', label: 'POST' },
          { value: 'PUT', label: 'PUT' },
          { value: 'DELETE', label: 'DELETE' },
          { value: 'PATCH', label: 'PATCH' },
        ], true);
        html += textarea('Headers (JSON)', 'cfg-headers', JSON.stringify(data.headers || {}, null, 2), false);
        html += textarea('Body (JSON)', 'cfg-body', data.body || '', false);
        break;

      case 'functionCall':
        html += field('Function Name', 'cfg-function-name', data.function_name || '', 'text', true);
        html += textarea('Arguments (JSON)', 'cfg-body', data.body || '', false);
        break;

      case 'jumpToStep': {
        const targets = (context.allNodes || [])
          .filter(function (candidate) {
            return candidate.id !== node.id;
          })
          .map(function (candidate) {
            return {
              value: candidate.id,
              label: (candidate.data && candidate.data.label) || candidate.type || candidate.id,
            };
          });

        if (targets.length) {
          html += selectField('Target Step', 'cfg-target-node', data.target_node || '', [
            { value: '', label: '-- Select step --' },
          ].concat(targets), true);
        } else {
          html += field('Target Node ID', 'cfg-target-node', data.target_node || '', 'text', true);
        }
        break;
      }

      case 'carouselTemplate':
      case 'whatsappFlowTemplate':
        html += '<p class="mb-3 text-sm text-text-subtle">This node type is coming soon.</p>';
        break;

      default:
        html += textarea('Message', 'cfg-message', data.message || '');
    }

    html +=
      '<div class="mt-4 flex gap-3">' +
        '<button type="button" data-save-config class="rounded-lg bg-green-500 px-4 py-2 text-sm font-semibold text-white hover:opacity-90">Save</button>' +
        '<button type="button" data-cancel-config class="rounded-lg border border-divider px-4 py-2 text-sm font-semibold text-text-body">Cancel</button>' +
      '</div>';

    return html;
  }

  function bindFormEvents(node, root) {
    if (!root) return;

    // Operation type switch
    const opSelect = root.querySelector('#cfg-operation-type');
    if (opSelect) {
      opSelect.addEventListener('change', function () {
        const val = opSelect.value;
        const tagGroup = root.querySelector('#cfg-group-tag');
        const listGroup = root.querySelector('#cfg-group-target-list');
        const updateGroup = root.querySelector('#cfg-group-update');

        if (tagGroup) tagGroup.classList.toggle('hidden', val !== 'tag');
        if (listGroup) listGroup.classList.toggle('hidden', val !== 'copy' && val !== 'move');
        if (updateGroup) updateGroup.classList.toggle('hidden', val !== 'update');
      });
    }

    // Condition criterion switch
    const condSelect = root.querySelector('#cfg-condition-type');
    if (condSelect) {
      condSelect.addEventListener('change', function () {
        const val = condSelect.value;
        const isWa = val !== 'custom_variable';
        const waGroup = root.querySelector('#cfg-group-wa-condition');
        const varGroup = root.querySelector('#cfg-group-var-condition');

        if (waGroup) waGroup.classList.toggle('hidden', !isWa);
        if (varGroup) varGroup.classList.toggle('hidden', isWa);
      });
    }

    // Condition wait switch
    const condWaitSelect = root.querySelector('#cfg-condition-wait');
    if (condWaitSelect) {
      condWaitSelect.addEventListener('change', function () {
        const customGroup = root.querySelector('#cfg-group-custom-wait');
        if (customGroup) customGroup.classList.toggle('hidden', condWaitSelect.value !== 'custom');
      });
    }

    // Delay preset switch
    const delayPreset = root.querySelector('#cfg-preset-delay');
    if (delayPreset) {
      delayPreset.addEventListener('change', function () {
        const customDelayGroup = root.querySelector('#cfg-group-custom-delay');
        const isCustom = delayPreset.value === 'custom';
        if (customDelayGroup) customDelayGroup.classList.toggle('hidden', !isCustom);

        const secField = root.querySelector('#cfg-delay-seconds');
        if (secField) {
          if (!isCustom) {
            secField.value = presetToSeconds(delayPreset.value) || 60;
          } else {
            const val = root.querySelector('#cfg-delay-value')?.value || 1;
            const unit = root.querySelector('#cfg-delay-unit')?.value || 'minutes';
            secField.value = parseDurationToSeconds(val, unit);
          }
        }
      });
    }

    // Custom delay inputs change
    const updateCustomDelay = function () {
      const delayPresetEl = root.querySelector('#cfg-preset-delay');
      if (delayPresetEl && delayPresetEl.value === 'custom') {
        const val = root.querySelector('#cfg-delay-value')?.value || 1;
        const unit = root.querySelector('#cfg-delay-unit')?.value || 'minutes';
        const secField = root.querySelector('#cfg-delay-seconds');
        if (secField) {
          secField.value = parseDurationToSeconds(val, unit);
        }
      }
    };

    root.querySelector('#cfg-delay-value')?.addEventListener('input', updateCustomDelay);
    root.querySelector('#cfg-delay-unit')?.addEventListener('change', updateCustomDelay);
  }

  function applyForm(node, root) {
    const get = function (id) {
      return root.querySelector('#' + id)?.value || '';
    };

    if (!node.data) {
      node.data = defaultData();
    }

    node.data.label = get('cfg-label');
    node.data.message = get('cfg-message');
    node.data.keywords = get('cfg-keywords');
    node.data.template_name = get('cfg-template-name');
    node.data.media_url = get('cfg-media-url');
    node.data.media_type = get('cfg-media-type');
    node.data.interactive_type = get('cfg-interactive-type');
    node.data.variable_name = get('cfg-variable-name');
    node.data.timeout = parseInt(get('cfg-timeout'), 10) || 120;
    node.data.url = get('cfg-url');
    node.data.method = get('cfg-method');
    node.data.function_name = get('cfg-function-name');
    node.data.target_node = get('cfg-target-node');

    // Delay handling
    const presetDelay = get('cfg-preset-delay');
    if (presetDelay) {
      node.data.preset_delay = presetDelay;
      if (presetDelay === 'custom') {
        node.data.delay_value = Math.max(1, parseInt(get('cfg-delay-value'), 10) || 1);
        node.data.delay_unit = get('cfg-delay-unit') || 'minutes';
        node.data.delay_seconds = parseDurationToSeconds(node.data.delay_value, node.data.delay_unit);
      } else {
        node.data.delay_seconds = presetToSeconds(presetDelay) || 60;
        node.data.delay_value = parseInt(presetDelay, 10) || 1;
        node.data.delay_unit = presetDelay.includes('day') ? 'days' : presetDelay.includes('hour') ? 'hours' : presetDelay.includes('week') ? 'weeks' : presetDelay.includes('month') ? 'months' : 'minutes';
      }
    } else {
      node.data.delay_seconds = parseInt(get('cfg-delay-seconds'), 10) || 0;
    }

    // Condition handling
    node.data.condition_type = get('cfg-condition-type') || node.data.condition_type || 'whatsapp_read';
    node.data.target_template = get('cfg-target-template');
    const condWait = get('cfg-condition-wait');
    if (condWait) {
      node.data.condition_wait = condWait;
      if (condWait === 'custom') {
        node.data.wait_value = Math.max(1, parseInt(get('cfg-wait-value'), 10) || 1);
        node.data.wait_unit = get('cfg-wait-unit') || 'days';
        node.data.wait_seconds = parseDurationToSeconds(node.data.wait_value, node.data.wait_unit);
      } else {
        node.data.wait_seconds = presetToSeconds(condWait) || 86400;
        node.data.wait_value = parseInt(condWait, 10) || 1;
        node.data.wait_unit = condWait.includes('day') ? 'days' : condWait.includes('hour') ? 'hours' : condWait.includes('week') ? 'weeks' : 'minutes';
      }
    }
    node.data.condition_variable = get('cfg-condition-variable');
    node.data.condition_operator = get('cfg-condition-operator') || 'equals';
    node.data.condition_value = get('cfg-condition-value');

    // Contact operation handling
    node.data.operation_type = get('cfg-operation-type') || node.data.operation_type || 'tag';
    const tagsRaw = get('cfg-tags');
    if (tagsRaw) {
      node.data.tags = tagsRaw
        .split(/[\n,]+/)
        .map(function (t) { return t.trim(); })
        .filter(Boolean);
    } else if (node.type === 'contactOperation' && node.data.operation_type === 'tag') {
      node.data.tags = [];
    }
    const targetList = get('cfg-target-list-id');
    node.data.mail_list_id = targetList;
    node.data.target_list_id = targetList;
    const targetListEl = root.querySelector('#cfg-target-list-id');
    if (targetListEl && targetListEl.selectedIndex >= 0) {
      node.data.target_list_name = targetListEl.options[targetListEl.selectedIndex].text;
    }
    node.data.field_name = get('cfg-field-name');
    node.data.field_value = get('cfg-field-value');

    const quickReplies = get('cfg-quick-replies');
    node.data.quick_replies = quickReplies ? quickReplies.split('\n').filter(Boolean) : [];

    const options = get('cfg-options');
    node.data.options = options ? options.split('\n').filter(Boolean) : [];

    const variables = get('cfg-variables');
    node.data.variables = variables ? variables.split('\n').filter(Boolean) : [];

    const headers = get('cfg-headers');
    try {
      node.data.headers = headers ? JSON.parse(headers) : {};
    } catch (_) {
      // keep existing headers
    }

    node.data.body = get('cfg-body');

    syncEngineAliases(node);
  }

  function syncEngineAliases(node) {
    const data = node.data || {};

    data.text = data.message || data.text || '';
    data.message = data.text;
    data.triggerKeyword = data.keywords || data.triggerKeyword || '';
    data.keywords = data.triggerKeyword;
    data.bodyText = data.message;
    data.templateCode = data.template_name || data.templateCode || '';
    data.templateId = data.templateCode;
    data.template_name = data.templateCode;
    data.variableName = data.variable_name || data.variableName || '';
    data.variable_name = data.variableName;
    data.mediaUrl = data.media_url || data.mediaUrl || '';
    data.media_url = data.mediaUrl;
    data.mediaType = data.media_type || data.mediaType || 'image';
    data.media_type = data.mediaType;
    data.interactiveType = data.interactive_type || data.interactiveType || 'button';
    data.interactive_type = String(data.interactiveType).toLowerCase();
    data.messageType = data.templateCode ? 'template' : (data.messageType || 'text');

    if (node.type === 'interactiveMessage') {
      data.buttons = (data.options || []).map(function (title, index) {
        return { id: 'btn_' + index, title: title };
      });
    }
  }

  function clearFieldError(wrapper) {
    if (!wrapper) {
      return;
    }
    const input = wrapper.querySelector('input, select, textarea');
    const error = wrapper.querySelector('[data-cfg-error]');
    if (input) {
      input.classList.remove('border-red-500');
    }
    if (error) {
      error.textContent = '';
      error.classList.add('hidden');
    }
  }

  function setFieldError(wrapper, message) {
    if (!wrapper) {
      return;
    }
    const input = wrapper.querySelector('input, select, textarea');
    const error = wrapper.querySelector('[data-cfg-error]');
    if (input) {
      input.classList.add('border-red-500');
    }
    if (error) {
      error.textContent = message;
      error.classList.remove('hidden');
    }
  }

  function validateForm(node, root) {
    const errors = [];
    root.querySelectorAll('[data-cfg-field]').forEach(clearFieldError);

    const requireField = function (id, message) {
      const input = root.querySelector('#' + id);
      const wrapper = root.querySelector('[data-cfg-field="' + id + '"]');
      const value = (input?.value || '').trim();
      if (!value) {
        setFieldError(wrapper, message);
        errors.push(message);
        return false;
      }
      return true;
    };

    requireField('cfg-label', 'Step label is required.');

    switch (node.type) {
      case 'welcomeMessage':
        requireField('cfg-message', 'Message is required.');
        break;
      case 'interactiveMessage':
        requireField('cfg-message', 'Message is required.');
        if (!requireField('cfg-options', 'Add at least one option.')) {
          break;
        }
        break;
      case 'templateMessage':
        requireField('cfg-template-name', 'Template is required.');
        break;
      case 'mediaMessage':
        requireField('cfg-media-url', 'Media URL is required.');
        break;
      case 'condition':
      case 'enhancedCondition': {
        const condType = root.querySelector('#cfg-condition-type')?.value;
        if (condType === 'custom_variable') {
          requireField('cfg-condition-variable', 'Condition variable is required.');
        }
        break;
      }
      case 'contactOperation': {
        const opType = root.querySelector('#cfg-operation-type')?.value || 'tag';
        if (opType === 'tag') {
          requireField('cfg-tags', 'At least one tag is required.');
        } else if (opType === 'copy' || opType === 'move') {
          requireField('cfg-target-list-id', 'Target audience list is required.');
        } else if (opType === 'update') {
          requireField('cfg-field-name', 'Field name is required.');
        }
        break;
      }
      case 'waitForResponse':
        requireField('cfg-variable-name', 'Variable name is required.');
        if (!/^[A-Za-z0-9_]+$/.test(root.querySelector('#cfg-variable-name')?.value || '')) {
          setFieldError(root.querySelector('[data-cfg-field="cfg-variable-name"]'), 'Use letters, numbers, and underscores only.');
          errors.push('Invalid variable name.');
        }
        break;
      case 'delay':
      case 'typingIndicator': {
        const delayPreset = root.querySelector('#cfg-preset-delay')?.value;
        if (delayPreset === 'custom') {
          const val = parseInt(root.querySelector('#cfg-delay-value')?.value || '0', 10);
          if (!val || val < 1) {
            setFieldError(root.querySelector('[data-cfg-field="cfg-delay-value"]'), 'Delay amount must be at least 1.');
            errors.push('Delay amount must be at least 1.');
          }
        }
        break;
      }
      case 'httpRequest':
        requireField('cfg-url', 'URL is required.');
        break;
      case 'functionCall':
        requireField('cfg-function-name', 'Function name is required.');
        break;
      case 'jumpToStep':
        requireField('cfg-target-node', 'Target step is required.');
        break;
      default:
        break;
    }

    return { valid: errors.length === 0, errors: errors };
  }

  function validateNodeData(node) {
    const data = node.data || {};
    const label = data.label || node.type || 'Step';
    const errors = [];

    if (!String(data.label || '').trim()) {
      errors.push(label + ': step label is required.');
    }

    switch (node.type) {
      case 'welcomeMessage':
        if (!String(data.message || '').trim()) {
          errors.push(label + ': message is required.');
        }
        break;
      case 'templateMessage':
        if (!String(data.template_name || data.templateCode || '').trim()) {
          errors.push(label + ': template is required.');
        }
        break;
      case 'interactiveMessage':
        if (!String(data.message || '').trim()) {
          errors.push(label + ': message is required.');
        }
        if (!Array.isArray(data.options) || data.options.length === 0) {
          errors.push(label + ': add at least one option.');
        }
        break;
      case 'mediaMessage':
        if (!String(data.media_url || '').trim()) {
          errors.push(label + ': media URL is required.');
        }
        break;
      case 'delay':
      case 'typingIndicator':
        if (!data.delay_seconds || Number(data.delay_seconds) < 1) {
          errors.push(label + ': delay must be at least 1 second.');
        }
        break;
      case 'condition':
      case 'enhancedCondition':
        if (data.condition_type === 'custom_variable' && !String(data.condition_variable || '').trim()) {
          errors.push(label + ': condition variable is required.');
        }
        break;
      case 'contactOperation': {
        const op = data.operation_type || 'tag';
        if (op === 'tag' && (!data.tags || (Array.isArray(data.tags) && data.tags.length === 0))) {
          errors.push(label + ': at least one tag is required.');
        } else if ((op === 'copy' || op === 'move') && !data.mail_list_id && !data.target_list_id) {
          errors.push(label + ': target audience list is required.');
        } else if (op === 'update' && !String(data.field_name || '').trim()) {
          errors.push(label + ': field name is required.');
        }
        break;
      }
      case 'waitForResponse':
        if (!String(data.variable_name || '').trim()) {
          errors.push(label + ': variable name is required.');
        }
        break;
      case 'httpRequest':
        if (!String(data.url || '').trim()) {
          errors.push(label + ': URL is required.');
        }
        break;
      case 'functionCall':
        if (!String(data.function_name || '').trim()) {
          errors.push(label + ': function name is required.');
        }
        break;
      case 'jumpToStep':
        if (!String(data.target_node || '').trim()) {
          errors.push(label + ': target step is required.');
        }
        break;
      default:
        break;
    }

    return { valid: errors.length === 0, errors: errors };
  }

  global.AutomationNodeConfig = {
    defaultData: defaultData,
    getPreview: getPreview,
    buildForm: buildForm,
    bindFormEvents: bindFormEvents,
    applyForm: applyForm,
    validateForm: validateForm,
    validateNodeData: validateNodeData,
  };
})(typeof window !== 'undefined' ? window : this);
