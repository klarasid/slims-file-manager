/*
 * File Manager plugin — client behaviour (vanilla JS, no dependencies).
 *
 * The admin loads plugin pages into #mainContent with AJAX, so this script can
 * run many times: event handlers are bound once on document (delegation) and
 * only per-page state is refreshed on every load.
 *
 * Security: user/server data is only ever inserted with textContent or
 * element properties, never via innerHTML. The only innerHTML use is for the
 * static icon markup defined below.
 */
(function () {
  'use strict';

  var ICONS = {
    loader: '<path d="M21 12a9 9 0 1 1-6.219-8.56"/>',
    check: '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
    error: '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/>',
    x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
    eye: '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
    eyeOff: '<path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/>',
    alert: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
    book: '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>'
  };

  function icon(name) {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'fm-icon');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');
    svg.innerHTML = ICONS[name] || ''; // static markup only
    return svg;
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = String(text);
    return node;
  }

  function currentRoot() {
    var roots = document.querySelectorAll('[data-fm-root]');
    return roots.length ? roots[roots.length - 1] : null;
  }

  function i18n(key, fallback) {
    var root = currentRoot();
    if (root && !root._fmI18n) {
      try { root._fmI18n = JSON.parse(root.getAttribute('data-fm-i18n') || '{}'); } catch (e) { root._fmI18n = {}; }
    }
    var value = root && root._fmI18n ? root._fmI18n[key] : undefined;
    return typeof value === 'string' ? value : (fallback || key);
  }

  /* ---------------------------------------------------------------- toast */
  function toast(message, type) {
    if (!message) return;
    var host = document.querySelector('.fm-toaster-host');
    if (!host) {
      host = el('div', 'fm-root fm-toaster-host');
      var list = el('div', 'fm-toaster');
      list.setAttribute('role', 'status');
      list.setAttribute('aria-live', 'polite');
      host.appendChild(list);
      document.body.appendChild(host);
    }
    var toaster = host.querySelector('.fm-toaster');
    var item = el('div', 'fm-toast ' + (type === 'error' ? 'is-error' : 'is-success'));
    item.appendChild(icon(type === 'error' ? 'error' : 'check'));
    item.appendChild(el('span', '', message));
    toaster.appendChild(item);
    setTimeout(function () {
      item.classList.add('is-leaving');
      setTimeout(function () {
        item.remove();
        if (!toaster.children.length) host.remove();
      }, 250);
    }, type === 'error' ? 6000 : 3500);
  }

  /* --------------------------------------------------------------- reload */
  function navigate(url) {
    if (!url) return;
    var $ = window.jQuery;
    var container = $ && $('#mainContent');
    if (container && container.length && typeof container.simbioAJAX === 'function') {
      container.simbioAJAX(url);
    } else {
      window.location.href = url;
    }
  }

  /* --------------------------------------------------------------- dialog */
  function openDialog(dialog) {
    if (!dialog) return;
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  }

  function closeDialog(dialog) {
    if (!dialog) return;
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
  }

  function confirmDialog(message, okLabel) {
    return new Promise(function (resolve) {
      var root = currentRoot() || document.body;
      var dialog = el('dialog', 'fm-dialog');
      dialog.setAttribute('role', 'alertdialog');
      var wrap = el('div', 'fm-stack');
      var header = el('div', 'fm-dialog-header');
      header.appendChild(el('h3', 'fm-dialog-title', i18n('confirmTitle', 'Are you sure?')));
      header.appendChild(el('p', 'fm-dialog-desc', message));
      wrap.appendChild(header);

      var footer = el('div', 'fm-dialog-footer');
      var cancel = el('button', 'fm-btn fm-btn--outline');
      cancel.type = 'button';
      cancel.appendChild(icon('x'));
      cancel.appendChild(el('span', '', i18n('cancel', 'Cancel')));
      var ok = el('button', 'fm-btn fm-btn--destructive');
      ok.type = 'button';
      ok.appendChild(icon('alert'));
      ok.appendChild(el('span', '', okLabel || i18n('confirm', 'Continue')));
      footer.appendChild(cancel);
      footer.appendChild(ok);
      wrap.appendChild(footer);
      dialog.appendChild(wrap);
      root.appendChild(dialog);

      var answered = false;
      function finish(value) {
        if (answered) return;
        answered = true;
        closeDialog(dialog);
        dialog.remove();
        resolve(value);
      }
      cancel.addEventListener('click', function () { finish(false); });
      ok.addEventListener('click', function () { finish(true); });
      dialog.addEventListener('cancel', function (e) { e.preventDefault(); finish(false); });
      dialog.addEventListener('click', function (e) { if (e.target === dialog) finish(false); });
      openDialog(dialog);
      cancel.focus();
    });
  }

  /* ----------------------------------------------------------- form errors */
  function clearErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach(function (node) { node.textContent = ''; });
    form.querySelectorAll('[aria-invalid="true"]').forEach(function (node) { node.removeAttribute('aria-invalid'); });
  }

  function showErrors(form, errors) {
    if (!errors || typeof errors !== 'object') return;
    Object.keys(errors).forEach(function (field) {
      var target = form.querySelector('[data-error-for="' + CSS.escape(field) + '"]');
      if (target) target.textContent = String(errors[field]);
      var input = form.querySelector('[name="' + CSS.escape(field) + '"]');
      if (input) input.setAttribute('aria-invalid', 'true');
    });
  }

  function setBusy(button, busy) {
    if (!button) return;
    if (busy) {
      button.disabled = true;
      button.classList.add('is-busy');
      var first = button.querySelector('.fm-icon');
      if (first) {
        button._fmIcon = first;
        var spinner = icon('loader');
        first.replaceWith(spinner);
        button._fmSpinner = spinner;
      }
    } else {
      button.disabled = false;
      button.classList.remove('is-busy');
      if (button._fmSpinner && button._fmIcon) button._fmSpinner.replaceWith(button._fmIcon);
      button._fmSpinner = button._fmIcon = null;
    }
  }

  /* ---------------------------------------------------------- form submit */
  function submitForm(form, submitter) {
    var max = parseInt(form.getAttribute('data-fm-max-select') || '0', 10);
    if (max > 0) {
      var checked = form.querySelectorAll('[data-fm-check]:checked').length;
      if (checked === 0) { toast(i18n('selectFirst', 'Select at least one item.'), 'error'); return; }
      if (checked > max) { toast(form.getAttribute('data-fm-max-message') || ('Max ' + max), 'error'); return; }
    }

    var message = form.getAttribute('data-fm-confirm');
    var ask = message ? confirmDialog(message, form.getAttribute('data-fm-confirm-ok')) : Promise.resolve(true);

    ask.then(function (confirmed) {
      if (!confirmed) return;
      var button = submitter || form.querySelector('[type="submit"]');
      clearErrors(form);
      setBusy(button, true);

      fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      }).then(function (response) {
        var type = response.headers.get('content-type') || '';
        if (type.indexOf('application/json') === -1) throw new Error('session');
        return response.json();
      }).then(function (data) {
        setBusy(button, false);
        if (data && data.ok) {
          toast(data.message, 'success');
          var dialog = form.closest('dialog');
          if (dialog) closeDialog(dialog);
          navigate(data.redirect);
        } else {
          toast((data && data.message) || i18n('error'), 'error');
          showErrors(form, data && data.errors);
        }
      }).catch(function (err) {
        setBusy(button, false);
        toast(err && err.message === 'session' ? i18n('session') : i18n('error'), 'error');
      });
    });
  }

  /* -------------------------------------------------------- biblio search */
  function biblioSearch(box, term) {
    var list = box.querySelector('[data-fm-biblio-results]');
    var input = box.querySelector('[data-fm-biblio-input]');
    if (box._fmAbort) box._fmAbort.abort();
    if (term.length < 2) { list.hidden = true; input.setAttribute('aria-expanded', 'false'); return; }

    var controller = typeof AbortController === 'function' ? new AbortController() : null;
    box._fmAbort = controller;
    list.replaceChildren(el('li', 'fm-combobox-empty', i18n('searching', 'Searching…')));
    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');

    var url = box.getAttribute('data-url') + '&q=' + encodeURIComponent(term);
    fetch(url, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      signal: controller ? controller.signal : undefined
    }).then(function (r) { return r.json(); }).then(function (data) {
      list.replaceChildren();
      var results = (data && data.results) || [];
      if (!results.length) {
        list.appendChild(el('li', 'fm-combobox-empty', i18n('noResults', 'No matching records.')));
        return;
      }
      results.forEach(function (item) {
        var li = el('li');
        li.setAttribute('role', 'presentation');
        var option = el('button', 'fm-combobox-option');
        option.type = 'button';
        option.setAttribute('role', 'option');
        option.appendChild(el('span', 'fm-strong', item.title));
        var meta = ['#' + item.id];
        if (item.isbn) meta.push(item.isbn);
        if (item.year) meta.push(item.year);
        option.appendChild(el('span', 'fm-muted', meta.join(' · ')));
        option.addEventListener('click', function () { chooseBiblio(box, item); });
        li.appendChild(option);
        list.appendChild(li);
      });
    }).catch(function (err) {
      if (err && err.name === 'AbortError') return;
      list.replaceChildren(el('li', 'fm-combobox-empty', i18n('error')));
    });
  }

  function chooseBiblio(box, item) {
    var input = box.querySelector('[data-fm-biblio-input]');
    var hidden = box.querySelector('[data-fm-biblio-id]');
    var list = box.querySelector('[data-fm-biblio-results]');
    var selected = box.querySelector('[data-fm-biblio-selected]');

    hidden.value = String(parseInt(item.id, 10) || '');
    list.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    input.closest('.fm-input-icon').hidden = true;

    selected.replaceChildren();
    var label = el('span');
    label.appendChild(icon('book'));
    label.appendChild(document.createTextNode(' '));
    label.appendChild(el('span', 'fm-strong', item.title));
    label.appendChild(el('span', 'fm-muted', '  #' + item.id));
    var change = el('button', 'fm-btn fm-btn--ghost fm-btn--sm');
    change.type = 'button';
    change.appendChild(icon('x'));
    change.appendChild(el('span', '', i18n('clear', 'Change')));
    change.addEventListener('click', function () {
      hidden.value = '';
      selected.hidden = true;
      input.closest('.fm-input-icon').hidden = false;
      input.value = '';
      input.focus();
    });
    selected.appendChild(label);
    selected.appendChild(change);
    selected.hidden = false;
  }

  /* ----------------------------------------------------- per-page state */
  function refreshShowIf(form) {
    form.querySelectorAll('[data-fm-show-if]').forEach(function (node) {
      var rule = node.getAttribute('data-fm-show-if').split('=');
      var checked = form.querySelector('[name="' + CSS.escape(rule[0]) + '"]:checked');
      node.hidden = !(checked && checked.value === rule[1]);
    });
  }

  function refreshBulk(form) {
    var boxes = form.querySelectorAll('[data-fm-check]');
    var checked = form.querySelectorAll('[data-fm-check]:checked').length;
    var all = form.querySelector('[data-fm-check-all]');
    if (all) {
      all.checked = boxes.length > 0 && checked === boxes.length;
      all.indeterminate = checked > 0 && checked < boxes.length;
    }
    var counter = form.querySelector('[data-fm-selected-count]');
    if (counter) counter.textContent = checked ? i18n('selected', '%d selected').replace('%d', checked) : i18n('selectAll', 'Select all');
    var submit = form.querySelector('[data-fm-bulk-submit]');
    if (submit) submit.disabled = checked === 0;
  }

  function init(root) {
    if (!root || root._fmReady) return;
    root._fmReady = true;
    root.querySelectorAll('form').forEach(function (form) {
      refreshShowIf(form);
      if (form.querySelector('[data-fm-check]')) refreshBulk(form);
    });
  }

  /* --------------------------------------------------- delegated events */
  if (!window.__slimsFileManager) {
    window.__slimsFileManager = true;

    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-fm-form')) return;
      e.preventDefault();
      e.stopPropagation();
      submitForm(form, e.submitter);
    });

    document.addEventListener('click', function (e) {
      var opener = e.target.closest('[data-fm-open]');
      if (opener) {
        e.preventDefault();
        openDialog(document.getElementById(opener.getAttribute('data-fm-open')));
        return;
      }

      var closer = e.target.closest('[data-fm-close]');
      if (closer) {
        e.preventDefault();
        closeDialog(closer.closest('dialog'));
        return;
      }

      if (e.target instanceof HTMLDialogElement && e.target.classList.contains('fm-dialog')) {
        closeDialog(e.target); // click on backdrop
        return;
      }

      var toggle = e.target.closest('[data-fm-toggle-password]');
      if (toggle) {
        var input = document.getElementById(toggle.getAttribute('data-fm-toggle-password'));
        if (!input) return;
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        toggle.replaceChildren(icon(show ? 'eyeOff' : 'eye'), el('span', '', toggle.getAttribute(show ? 'data-label-hide' : 'data-label-show')));
        return;
      }

      var tab = e.target.closest('[data-fm-tab]');
      if (tab) {
        var root = tab.closest('[data-fm-root]');
        var name = tab.getAttribute('data-fm-tab');
        tab.parentNode.querySelectorAll('[data-fm-tab]').forEach(function (t) {
          var active = t === tab;
          t.classList.toggle('is-active', active);
          t.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        root.querySelectorAll('[data-fm-panel]').forEach(function (panel) {
          panel.hidden = panel.getAttribute('data-fm-panel') !== name;
        });
        return;
      }

      // close combobox when clicking elsewhere
      document.querySelectorAll('[data-fm-biblio-results]').forEach(function (list) {
        if (!list.hidden && !list.parentNode.contains(e.target)) list.hidden = true;
      });
    });

    document.addEventListener('change', function (e) {
      var target = e.target;
      var form = target.form || target.closest('form');

      if (target.matches('[data-fm-check-all]') && form) {
        form.querySelectorAll('[data-fm-check]').forEach(function (box) { box.checked = target.checked; });
        refreshBulk(form);
        return;
      }
      if (target.matches('[data-fm-check]') && form) {
        refreshBulk(form);
        return;
      }
      if (form && form.querySelector('[data-fm-show-if]')) refreshShowIf(form);

      if (target.matches('[data-fm-autosubmit]') && form) {
        // Goes through the admin's own AJAX handler for .submitViaAJAX forms.
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        return;
      }

      if (target.type === 'file' && target.closest('[data-fm-dropzone]')) {
        var zone = target.closest('[data-fm-dropzone]');
        var label = zone.querySelector('[data-fm-dropzone-label]');
        var error = form && form.querySelector('[data-error-for="' + CSS.escape(target.name) + '"]');
        if (!zone._fmDefault) zone._fmDefault = label.textContent;
        if (error) error.textContent = '';
        var file = target.files && target.files[0];
        var max = parseInt(target.getAttribute('data-max-bytes') || '0', 10);
        if (file && max > 0 && file.size > max) {
          if (error) error.textContent = target.getAttribute('data-too-large') || '';
          target.value = '';
          file = null;
        }
        label.textContent = file ? file.name : zone._fmDefault;
        zone.classList.toggle('has-file', !!file);
      }
    });

    ['dragenter', 'dragover'].forEach(function (type) {
      document.addEventListener(type, function (e) {
        var zone = e.target.closest && e.target.closest('[data-fm-dropzone]');
        if (!zone) return;
        e.preventDefault();
        zone.classList.add('is-dragover');
      });
    });
    ['dragleave', 'drop'].forEach(function (type) {
      document.addEventListener(type, function (e) {
        var zone = e.target.closest && e.target.closest('[data-fm-dropzone]');
        if (!zone) return;
        e.preventDefault();
        zone.classList.remove('is-dragover');
        if (type === 'drop' && e.dataTransfer && e.dataTransfer.files.length) {
          var input = zone.querySelector('input[type="file"]');
          try {
            input.files = e.dataTransfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
          } catch (err) { /* old browser: user can still click */ }
        }
      });
    });

    document.addEventListener('input', function (e) {
      var input = e.target;
      if (!input.matches || !input.matches('[data-fm-biblio-input]')) return;
      var box = input.closest('[data-fm-biblio-search]');
      clearTimeout(box._fmTimer);
      box._fmTimer = setTimeout(function () { biblioSearch(box, input.value.trim()); }, 250);
    });

    document.addEventListener('keydown', function (e) {
      var input = e.target;
      if (!input.matches || !input.matches('[data-fm-biblio-input]')) return;
      var box = input.closest('[data-fm-biblio-search]');
      var options = Array.prototype.slice.call(box.querySelectorAll('.fm-combobox-option'));
      var list = box.querySelector('[data-fm-biblio-results]');
      if (e.key === 'Enter') {
        e.preventDefault(); // never submit the attach form from the search box
        if (!list.hidden && options.length) options[0].click();
      } else if (e.key === 'ArrowDown' && options.length) {
        e.preventDefault();
        options[0].focus();
      } else if (e.key === 'Escape') {
        list.hidden = true;
      }
    });

    document.addEventListener('keydown', function (e) {
      var option = e.target.closest && e.target.closest('.fm-combobox-option');
      if (!option) return;
      var options = Array.prototype.slice.call(option.closest('[data-fm-biblio-results]').querySelectorAll('.fm-combobox-option'));
      var index = options.indexOf(option);
      if (e.key === 'ArrowDown') { e.preventDefault(); (options[index + 1] || option).focus(); }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (index > 0) options[index - 1].focus();
        else option.closest('[data-fm-biblio-search]').querySelector('[data-fm-biblio-input]').focus();
      }
    });
  }

  init(currentRoot());
})();
