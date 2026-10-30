(function () {
    'use strict';
    const config = window.localLinkPreviewConfig;
    if (!config || !config.enabled || window.localLinkPreviewInitialized) return;
    window.localLinkPreviewInitialized = true;
    const cache = new Map();
    let showPreviews = config.showPreviews !== false;
    let preferenceSaving = false;
    const storageKey = 'localLinkPreviewHidden:' + config.userId;
    let hiddenPosts;
    try { hiddenPosts = new Set(JSON.parse(localStorage.getItem(storageKey) || '[]')); } catch (_) { hiddenPosts = new Set(); }

    function eligible(value) {
        try {
            if (String(value).trim().startsWith('#')) return false;
            const url = new URL(value, location.href);
            if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) return false;
            if (url.hostname.toLowerCase().replace(/\.$/, '') !== location.hostname.toLowerCase().replace(/\.$/, '')) return true;
            const route = url.searchParams.get('r') || '';
            return !/(?:^|\/)(?:u(?:\/|$)|user\/profile(?:\/|$))/i.test(decodeURIComponent(url.pathname)) && !/^\/?user\/profile(?:\/|$)/i.test(route);
        } catch (_) { return false; }
    }
    function editorUrls(editor) {
        const text = editor.value !== undefined ? editor.value : editor.innerText;
        const typed = (String(text || '').match(/https?:\/\/[^\s<>"']+/ig) || []).map(url => {
            url = url.replace(/[.,;!?]+$/, '');
            while (url.endsWith(')') && (url.match(/\)/g) || []).length > (url.match(/\(/g) || []).length) url = url.slice(0, -1);
            return url;
        });
        const linked = Array.from(editor.querySelectorAll ? editor.querySelectorAll('a[href]') : []).map(anchor => anchor.href);
        return [...new Set([...typed, ...linked].filter(eligible))];
    }
    async function post(endpoint, values) {
        const body = new URLSearchParams(values);
        body.set(config.csrfParam, config.csrfToken);
        const response = await fetch(endpoint, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', Accept: 'application/json'}, body: body.toString()});
        if (!response.ok) throw new Error('request failed');
        return response.json();
    }
    function load(url, id) {
        const key = url + ':' + (id || 0);
        if (!cache.has(key)) cache.set(key, post(config.endpoint, id ? {url, postId: id} : {url}).catch(error => {cache.delete(key); throw error;}));
        return cache.get(key);
    }
    function button(label, callback) {
        const node = document.createElement('button');
        node.type = 'button'; node.className = 'btn btn-sm btn-default'; node.textContent = label;
        node.addEventListener('click', callback); return node;
    }
    function card(data) {
        const link = document.createElement('a');
        link.className = 'llp-card'; link.href = data.url; link.target = '_blank';
        link.rel = 'noopener noreferrer nofollow'; link.dataset.llpUrl = data.url;
        if (data.imageUrl && !data.hideImage) {
            const media = document.createElement('span'), image = document.createElement('img');
            media.className = 'llp-card__media'; image.className = 'llp-card__image';
            image.src = data.imageUrl; image.alt = ''; image.loading = 'lazy';
            image.addEventListener('error', () => media.remove(), {once: true});
            media.appendChild(image); link.appendChild(media);
        }
        const body = document.createElement('span'), site = document.createElement('span'), title = document.createElement('strong');
        body.className = 'llp-card__body'; site.className = 'llp-card__site'; title.className = 'llp-card__title';
        site.textContent = data.siteName || new URL(data.url).hostname; title.textContent = data.title || data.url;
        body.append(site, title);
        if (data.description) {
            const description = document.createElement('span'); description.className = 'llp-card__description'; description.textContent = data.description; body.appendChild(description);
        }
        const external = document.createElement('span'); external.className = 'llp-card__external'; external.setAttribute('aria-hidden', 'true'); external.textContent = '↗';
        body.appendChild(external); link.appendChild(body); return link;
    }
    function hiddenField(form, name) {
        let input = form.querySelector('input[name="' + name + '"]');
        if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = name; form.appendChild(input); }
        input.setAttribute('data-prevent-statechange', ''); return input;
    }
    function setSelected(editor, url) {
        const form = editor.closest('form');
        if (!form || (!url && !form.querySelector('input[name="localLinkPreviewUrl"]'))) return;
        hiddenField(form, 'localLinkPreviewUrl').value = url || '';
    }
    function editorDisabled(editor) { return editor.closest('form')?.querySelector('input[name="localLinkPreviewUrl"]')?.value === '__disabled__'; }
    function editorControls(editor, holder, urls, selected) {
        const bar = document.createElement('div'); bar.className = 'llp-controls';
        if (editorDisabled(editor)) {
            bar.appendChild(button('Vorschau aktivieren', () => {setSelected(editor, selected); renderEditor(editor, holder, urls, selected);})); return bar;
        }
        if (urls.length > 1) {
            const select = document.createElement('select'); select.className = 'form-select form-select-sm llp-select'; select.setAttribute('aria-label', 'Link für Vorschau auswählen');
            urls.forEach((url, index) => {const option = document.createElement('option'); option.value = url; option.textContent = 'Link ' + (index + 1) + ': ' + new URL(url).hostname; option.selected = url === selected; select.appendChild(option);});
            select.addEventListener('change', () => renderEditor(editor, holder, urls, select.value)); bar.appendChild(select);
        }
        const form = editor.closest('form');
        const imageHidden = form?.querySelector('input[name="localLinkPreviewHideImage"]')?.value === '1';
        holder.classList.toggle('llp-no-image', imageHidden);
        const imageToggle = button(imageHidden ? 'Bild anzeigen' : 'Bild ausblenden', () => {
            holder.classList.toggle('llp-no-image'); imageToggle.textContent = holder.classList.contains('llp-no-image') ? 'Bild anzeigen' : 'Bild ausblenden';
            if (form) hiddenField(form, 'localLinkPreviewHideImage').value = holder.classList.contains('llp-no-image') ? '1' : '0';
        });
        bar.append(imageToggle, button('Vorschau entfernen', () => {setSelected(editor, '__disabled__'); renderEditor(editor, holder, urls, selected);})); return bar;
    }
    async function renderEditor(editor, holder, urls, selected) {
        holder.dataset.url = selected;
        const version = holder._llpVersion = (holder._llpVersion || 0) + 1;
        if (!editorDisabled(editor)) setSelected(editor, selected);
        const status = document.createElement('span'); status.className = 'llp-loading';
        const disabled = editorDisabled(editor);
        status.textContent = disabled ? 'Vorschau für diesen Beitrag ausgeschaltet.' : 'Linkvorschau wird geladen …';
        holder.replaceChildren(status, editorControls(editor, holder, urls, selected));
        if (disabled || !showPreviews) return;
        try {
            const data = await load(selected);
            if (!showPreviews || !holder.isConnected || holder._llpVersion !== version || editorDisabled(editor)) return;
            if (data.disabled) {status.textContent = 'Für diesen Link wird keine Vorschau angezeigt.'; return;}
            status.replaceWith(card(data));
        } catch (_) {if (holder._llpVersion === version) status.textContent = 'Keine Vorschau verfügbar. Der Link bleibt erhalten.';}
    }
    function scheduleEditor(editor) {
        clearTimeout(editor._llpTimer);
        editor._llpTimer = setTimeout(() => {
            const urls = editorUrls(editor);
            let holder = editor.parentElement.querySelector(':scope > .llp-editor-preview');
            if (!urls.length || !showPreviews) {if (holder) holder.remove(); if (!urls.length && !editorDisabled(editor)) setSelected(editor, ''); return;}
            if (!holder) {holder = document.createElement('div'); holder.className = 'llp-editor-preview'; editor.insertAdjacentElement('afterend', holder);}
            const selected = urls.includes(holder.dataset.url) ? holder.dataset.url : urls[0];
            if (holder.dataset.url !== selected || !holder.querySelector('.llp-controls')) renderEditor(editor, holder, urls, selected);
        }, 400);
    }
    function postId(container) {
        const element = container.querySelector('[data-ui-widget="post.Post"][id^="post-content-"]');
        return element?.id.match(/^post-content-(\d+)$/)?.[1] || 0;
    }
    function isPostEditor(editor) {return editor.matches('[contenteditable="true"]') && !!editor.closest('#contentForm_message, #contentForm_messageModal');}
    function isPostContent(container) {return !!container.querySelector('[data-ui-widget="post.Post"][id^="post-content-"]');}
    function saveHidden() {try {localStorage.setItem(storageKey, JSON.stringify([...hiddenPosts]));} catch (_) {}}
    async function setAuthorSelection(container, id, disabled) {
        const control = container.querySelector('.llp-post-controls');
        if (control) control.querySelectorAll('button').forEach(node => node.disabled = true);
        try {
            await post(config.selectionEndpoint, {postId: id, disabled: disabled ? '1' : '0'});
            cache.clear(); hiddenPosts.delete(String(id)); saveHidden();
            container.querySelector('.llp-post-previews')?.remove(); delete container.dataset.llpDone; enhancePost(container);
        } catch (_) {
            if (control) {control.querySelectorAll('button').forEach(node => node.disabled = false); const error = document.createElement('span'); error.textContent = 'Änderung konnte nicht gespeichert werden.'; error.setAttribute('role', 'alert'); control.appendChild(error);}
        }
    }
    async function enhancePost(container) {
        if (!showPreviews || container.dataset.llpDone) return;
        const message = container.querySelector('[data-ui-widget="post.Post"][id^="post-content-"]');
        const anchors = Array.from(message.querySelectorAll('a[href]')).filter(anchor => eligible(anchor.getAttribute('href')) && !anchor.closest('.llp-card, .llp-controls, .llp-post-controls'));
        const urls = [...new Set(anchors.map(anchor => anchor.href))];
        if (!urls.length) return;
        container.dataset.llpDone = '1';
        const id = postId(container), wrapper = document.createElement('div'), actions = document.createElement('div');
        wrapper.className = 'llp-post-previews'; actions.className = 'llp-controls llp-post-controls';
        const content = document.createElement('div'); content.className = 'llp-post-cards'; wrapper.append(content, actions);
        container.appendChild(wrapper);
        function visibility() {
            const hidden = hiddenPosts.has(String(id)); content.hidden = hidden;
            actions.replaceChildren(button(hidden ? 'Vorschau anzeigen' : 'Vorschau ausblenden', () => {if (hiddenPosts.has(String(id))) hiddenPosts.delete(String(id)); else hiddenPosts.add(String(id)); saveHidden(); visibility(); if (!hiddenPosts.has(String(id)) && !content.children.length) fill();}));
            if (wrapper.dataset.canEdit === 'true') actions.appendChild(button('Vorschau für alle entfernen', () => setAuthorSelection(container, id, true)));
        }
        async function fill() {
            const count = Math.min(urls.length, Math.max(1, Number(config.maxPreviews) || 1));
            for (let index = 0; index < count; index++) {
                try {
                    const data = await load(urls[index], index === 0 ? id : 0);
                    if (!showPreviews || !wrapper.isConnected) return;
                    if (data.disabled) {
                        if (index === 0) {
                            content.replaceChildren(); actions.replaceChildren();
                            if (data.canEdit) actions.appendChild(button('Vorschau für diesen Beitrag aktivieren', () => setAuthorSelection(container, id, false)));
                            else wrapper.remove();
                        }
                        return;
                    }
                    if (index === 0) wrapper.dataset.canEdit = data.canEdit ? 'true' : 'false';
                    if (!content.querySelector('[data-llp-url="' + CSS.escape(data.url) + '"]')) content.appendChild(card(data));
                } catch (_) { /* Failed requests leave the original link intact, without empty replacement cards. */ }
            }
            if (!content.children.length) {wrapper.remove(); return;}
            visibility();
        }
        visibility(); if (!hiddenPosts.has(String(id))) fill();
    }
    function preferenceControl(target) {
        if (target.previousElementSibling?.classList.contains('llp-preferences')) return;
        const label = document.createElement('label'), input = document.createElement('input'), text = document.createElement('span');
        label.className = 'llp-preferences'; input.type = 'checkbox'; input.checked = showPreviews; text.textContent = 'Linkvorschauen anzeigen';
        input.addEventListener('change', async () => {
            if (preferenceSaving) {input.checked = showPreviews; return;}
            preferenceSaving = true; document.querySelectorAll('.llp-preferences input').forEach(node => node.disabled = true);
            try {
                const result = await post(config.preferenceEndpoint, {enabled: input.checked ? '1' : '0'}); showPreviews = result.enabled;
                document.querySelectorAll('.llp-post-previews, .llp-editor-preview').forEach(node => node.remove());
                document.querySelectorAll('[data-llp-done]').forEach(node => delete node.dataset.llpDone);
                document.querySelectorAll('.llp-preferences input').forEach(node => node.checked = showPreviews);
                scan(document);
            } catch (_) {input.checked = showPreviews; window.alert('Die Einstellung konnte nicht gespeichert werden.');}
            finally {preferenceSaving = false; document.querySelectorAll('.llp-preferences input').forEach(node => node.disabled = false);}
        });
        label.append(input, text); target.before(label);
    }
    function scan(root) {
        const editors = [], posts = [];
        if (root.matches && isPostEditor(root)) editors.push(root);
        if (root.querySelectorAll) editors.push(...Array.from(root.querySelectorAll('[contenteditable="true"]')).filter(isPostEditor));
        editors.forEach(editor => {
            const form = editor.closest('form'); if (form) preferenceControl(form);
            if (editor.dataset.llpBound) {scheduleEditor(editor); return;}
            editor.dataset.llpBound = '1'; editor.addEventListener('input', () => scheduleEditor(editor)); editor.addEventListener('paste', () => setTimeout(() => scheduleEditor(editor), 0)); scheduleEditor(editor);
        });
        if (root.matches?.('.wall-entry-content') && isPostContent(root)) posts.push(root);
        if (root.querySelectorAll) posts.push(...Array.from(root.querySelectorAll('.wall-entry-content')).filter(isPostContent));
        posts.forEach(container => {
            const stream = container.closest('.wall-stream, .stream, .sg-start-stream') || container.parentElement?.parentElement;
            if (stream && !stream.querySelector('.llp-preferences')) {
                const entry = container.closest('.wall-entry') || container; preferenceControl(entry);
            }
            enhancePost(container);
        });
    }
    scan(document);
    new MutationObserver(mutations => mutations.forEach(mutation => mutation.addedNodes.forEach(node => {if (node.nodeType === 1 && !node.closest('.llp-post-previews, .llp-editor-preview, .llp-preferences')) scan(node);})))
        .observe(document.body, {childList: true, subtree: true});
})();
