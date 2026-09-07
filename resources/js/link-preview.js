(function () {
    'use strict';
    const config = window.localLinkPreviewConfig;
    if (!config || !config.enabled) return;
    const cache = new Map(), pattern = /https?:\/\/[^\s<>"']+/ig;
    const urlsFrom = text => [...new Set((String(text || '').match(pattern) || []).map(url => url.replace(/[),.;!?]+$/, '')))];

    async function load(url, postId) {
        const key = url + ':' + (postId || 0);
        if (!cache.has(key)) {
            const body = new URLSearchParams({url});
            if (postId) body.set('postId', postId);
            body.set(config.csrfParam, config.csrfToken);
            cache.set(key, fetch(config.endpoint, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', Accept: 'application/json'},
                body: body.toString()
            }).then(response => {
                if (!response.ok) throw new Error('preview failed');
                return response.json();
            }));
        }
        return cache.get(key);
    }

    function card(data) {
        const link = document.createElement('a');
        link.className = 'llp-card'; link.href = data.url; link.target = '_blank';
        link.rel = 'noopener noreferrer nofollow'; link.dataset.llpUrl = data.url;
        if (data.imageUrl) {
            const media = document.createElement('span'), image = document.createElement('img');
            media.className = 'llp-card__media'; image.className = 'llp-card__image';
            image.src = data.imageUrl; image.alt = ''; image.loading = 'lazy';
            media.appendChild(image); link.appendChild(media);
        }
        const body = document.createElement('span'), site = document.createElement('span'), title = document.createElement('strong');
        body.className = 'llp-card__body'; site.className = 'llp-card__site'; title.className = 'llp-card__title';
        site.textContent = data.siteName || new URL(data.url).hostname; title.textContent = data.title || data.url;
        body.append(site, title);
        if (data.description) {
            const description = document.createElement('span');
            description.className = 'llp-card__description'; description.textContent = data.description;
            body.appendChild(description);
        }
        const external = document.createElement('span');
        external.className = 'llp-card__external'; external.setAttribute('aria-hidden', 'true'); external.textContent = '↗';
        body.appendChild(external); link.appendChild(body);
        return link;
    }

    function hiddenField(form, name) {
        let input = form.querySelector('input[name="' + name + '"]');
        if (!input) {
            input = document.createElement('input'); input.type = 'hidden'; input.name = name;
            form.appendChild(input);
        }
        // Internal preview bookkeeping must not make HumHub's untouched wall
        // composer appear to contain unsaved user changes.
        input.setAttribute('data-prevent-statechange', '');
        return input;
    }

    function setSelected(editor, url) {
        const form = editor.closest('form');
        if (!form) return;
        // Do not mutate an untouched composer at all. The old implementation
        // appended an empty hidden field to every post and comment form during
        // the initial scan, after HumHub had stored the form baseline.
        if (!url && !form.querySelector('input[name="localLinkPreviewUrl"]')) return;
        hiddenField(form, 'localLinkPreviewUrl').value = url || '';
    }

    function controls(editor, holder, urls, selected) {
        const bar = document.createElement('div'); bar.className = 'llp-controls';
        if (urls.length > 1) {
            const select = document.createElement('select');
            select.className = 'form-select form-select-sm llp-select'; select.setAttribute('aria-label', 'Link für Vorschau auswählen');
            urls.forEach((url, index) => {
                const option = document.createElement('option');
                option.value = url; option.textContent = 'Link ' + (index + 1) + ': ' + new URL(url).hostname;
                option.selected = url === selected; select.appendChild(option);
            });
            select.addEventListener('change', () => renderEditor(editor, holder, urls, select.value));
            bar.appendChild(select);
        }
        const imageToggle = document.createElement('button');
        imageToggle.type = 'button'; imageToggle.className = 'btn btn-sm btn-default'; imageToggle.textContent = 'Bild ausblenden';
        imageToggle.addEventListener('click', () => {
            holder.classList.toggle('llp-no-image');
            imageToggle.textContent = holder.classList.contains('llp-no-image') ? 'Bild anzeigen' : 'Bild ausblenden';
            const form = editor.closest('form');
            if (form) hiddenField(form, 'localLinkPreviewHideImage').value = holder.classList.contains('llp-no-image') ? '1' : '0';
        });
        const remove = document.createElement('button');
        remove.type = 'button'; remove.className = 'btn btn-sm btn-default'; remove.textContent = 'Vorschau entfernen';
        remove.addEventListener('click', () => {
            setSelected(editor, '__disabled__');
            editor.dataset.llpSuppressed = urlsFrom(editor.value !== undefined ? editor.value : editor.innerText).join('|');
            holder.remove();
        });
        bar.append(imageToggle, remove);
        return bar;
    }

    async function renderEditor(editor, holder, urls, selected) {
        holder.dataset.url = selected; setSelected(editor, selected);
        holder.innerHTML = '<span class="llp-loading">Linkvorschau wird geladen …</span>';
        try {
            const data = await load(selected);
            if (holder.dataset.url === selected) holder.replaceChildren(card(data), controls(editor, holder, urls, selected));
        } catch (_) {
            const fallback = {url: selected, title: selected, siteName: new URL(selected).hostname, description: 'Keine zusätzlichen Informationen verfügbar.'};
            if (holder.dataset.url === selected) holder.replaceChildren(card(fallback), controls(editor, holder, urls, selected));
        }
    }

    function scheduleEditor(editor) {
        clearTimeout(editor._llpTimer);
        editor._llpTimer = setTimeout(() => {
            const urls = urlsFrom(editor.value !== undefined ? editor.value : editor.innerText);
            const signature = urls.join('|');
            let holder = editor.parentElement.querySelector(':scope > .llp-editor-preview');
            if (!urls.length) { if (holder) holder.remove(); setSelected(editor, ''); editor.dataset.llpSuppressed = ''; return; }
            if (editor.dataset.llpSuppressed === signature) return;
            editor.dataset.llpSuppressed = '';
            if (!holder) {
                holder = document.createElement('div'); holder.className = 'llp-editor-preview';
                editor.insertAdjacentElement('afterend', holder);
            }
            const selected = urls.includes(holder.dataset.url) ? holder.dataset.url : urls[0];
            if (holder.dataset.url !== selected || !holder.querySelector('.llp-card')) renderEditor(editor, holder, urls, selected);
        }, 650);
    }

    function postId(container) {
        const root = container.closest('[data-content-key], [id^="wallEntry_"]');
        if (!root) return 0;
        const match = (root.dataset.contentKey || root.id || '').match(/(?:post[-_]|wallEntry_)(\d+)/i);
        return match ? match[1] : 0;
    }

    function hideStandalone(anchor) {
        const parent = anchor.parentElement;
        if (parent && parent.textContent.trim() === anchor.textContent.trim()) parent.classList.add('llp-source-link-hidden');
    }

    async function enhancePost(container) {
        if (container.dataset.llpDone) return;
        container.dataset.llpDone = '1';
        const anchors = Array.from(container.querySelectorAll('a[href]')).filter(a => /^https?:\/\//i.test(a.href) && !a.closest('.llp-card'));
        const urls = [...new Set(anchors.map(a => a.href))];
        if (!urls.length) return;
        const wrapper = document.createElement('div'); wrapper.className = 'llp-post-previews';
        const count = Math.min(urls.length, Number(config.maxPreviews) || 1);
        for (let index = 0; index < count; index++) {
            try {
                const data = await load(urls[index], index === 0 ? postId(container) : 0);
                if (data.disabled) return;
                if (!wrapper.querySelector('[data-llp-url="' + CSS.escape(data.url) + '"]')) wrapper.appendChild(card(data));
                if (data.hideImage) wrapper.lastElementChild.classList.add('llp-no-image');
            } catch (_) {
                wrapper.appendChild(card({url: urls[index], title: urls[index], siteName: new URL(urls[index]).hostname, description: 'Keine zusätzlichen Informationen verfügbar.'}));
            }
        }
        if (wrapper.children.length) {
            anchors.forEach(hideStandalone);
            container.appendChild(wrapper);
        }
    }

    function scan(root) {
        const editors = [], posts = [];
        if (root.matches && root.matches('[contenteditable="true"], textarea')) editors.push(root);
        if (root.querySelectorAll) editors.push(...root.querySelectorAll('[contenteditable="true"], textarea'));
        editors.forEach(editor => {
            if (editor.dataset.llpBound) return;
            editor.dataset.llpBound = '1';
            editor.addEventListener('input', () => scheduleEditor(editor));
            editor.addEventListener('paste', () => setTimeout(() => scheduleEditor(editor), 0));
            scheduleEditor(editor);
        });
        if (root.matches && root.matches('.wall-entry-content')) posts.push(root);
        if (root.querySelectorAll) posts.push(...root.querySelectorAll('.wall-entry-content'));
        posts.forEach(enhancePost);
    }

    scan(document);
    new MutationObserver(mutations => mutations.forEach(m => m.addedNodes.forEach(node => node.nodeType === 1 && scan(node))))
        .observe(document.body, {childList: true, subtree: true});
})();
