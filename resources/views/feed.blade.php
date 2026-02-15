<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SmartNews Feed</title>
    <style>
        :root {
            --bg: #0f172a;
            --bg-soft: #111827;
            --panel: #1e293b;
            --panel-soft: #334155;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --accent: #fb923c;
            --accent-soft: #fdba74;
            --good: #22c55e;
            --bad: #ef4444;
            --radius: 14px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
            background:
                radial-gradient(1200px 600px at -10% -20%, rgba(56, 189, 248, 0.25), transparent 60%),
                radial-gradient(900px 500px at 110% 0%, rgba(251, 146, 60, 0.22), transparent 60%),
                radial-gradient(900px 700px at 50% 120%, rgba(14, 165, 233, 0.14), transparent 70%),
                linear-gradient(135deg, #020617 0%, #0b1023 38%, #111827 100%);
            color: var(--text);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }

        body::before {
            background:
                linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
            background-size: 34px 34px, 34px 34px;
            mask-image: radial-gradient(circle at 50% 35%, black 35%, transparent 80%);
        }

        body::after {
            background:
                radial-gradient(circle at 18% 30%, rgba(59, 130, 246, 0.18), transparent 28%),
                radial-gradient(circle at 82% 18%, rgba(249, 115, 22, 0.18), transparent 24%),
                radial-gradient(circle at 68% 78%, rgba(6, 182, 212, 0.15), transparent 30%);
            filter: blur(8px);
            animation: drift 16s ease-in-out infinite alternate;
        }

        @keyframes drift {
            from {
                transform: translate3d(0, 0, 0) scale(1);
            }
            to {
                transform: translate3d(0, -18px, 0) scale(1.03);
            }
        }

        .shell {
            max-width: 1280px;
            margin: 0 auto;
            padding: 24px 16px 32px;
            position: relative;
            z-index: 1;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
        }

        .title {
            margin: 0;
            font-size: 30px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .subtitle {
            color: var(--muted);
            font-size: 14px;
            margin: 4px 0 0;
        }

        .grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 16px;
        }

        .panel {
            background: linear-gradient(180deg, rgba(30, 41, 59, 0.95), rgba(15, 23, 42, 0.95));
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: var(--radius);
        }

        .filters {
            padding: 16px;
            position: sticky;
            top: 16px;
            height: fit-content;
        }

        .filters h2 {
            margin: 0 0 14px;
            font-size: 16px;
            color: var(--accent-soft);
        }

        .field {
            margin-bottom: 12px;
        }

        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            color: var(--muted);
        }

        .field input,
        .field select {
            width: 100%;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, 0.35);
            background: rgba(15, 23, 42, 0.75);
            color: var(--text);
            padding: 8px 10px;
            font-size: 14px;
        }

        .range {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .buttons {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        button {
            border: 0;
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(90deg, var(--accent), #f97316);
            color: #111827;
            font-weight: 700;
        }

        .btn-ghost {
            background: rgba(148, 163, 184, 0.2);
            color: var(--text);
        }

        .feed {
            padding: 14px;
        }

        .status {
            display: flex;
            justify-content: space-between;
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 12px;
        }

        .list {
            display: grid;
            gap: 12px;
        }

        .card {
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 12px;
            padding: 14px;
            background: linear-gradient(160deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.9));
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
            font-size: 12px;
            color: var(--muted);
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .badge {
            padding: 3px 8px;
            border-radius: 999px;
            background: rgba(51, 65, 85, 0.8);
            font-size: 11px;
            color: #cbd5e1;
        }

        .badge-important {
            background: rgba(251, 146, 60, 0.2);
            color: var(--accent-soft);
            border: 1px solid rgba(251, 146, 60, 0.4);
        }

        .card h3 {
            margin: 0 0 8px;
            font-size: 19px;
            line-height: 1.35;
        }

        .card p {
            margin: 0;
            color: #cbd5e1;
            line-height: 1.55;
            font-size: 14px;
        }

        .media {
            display: grid;
            gap: 8px;
            margin-top: 12px;
        }

        .media img,
        .media video {
            width: 100%;
            max-height: 380px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .meta {
            margin-top: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            font-size: 12px;
            color: var(--muted);
        }

        .meta a {
            color: #93c5fd;
            text-decoration: none;
        }

        .load-more {
            margin-top: 14px;
            width: 100%;
            background: rgba(148, 163, 184, 0.2);
            color: var(--text);
            border: 1px solid rgba(148, 163, 184, 0.3);
        }

        .empty {
            border: 1px dashed rgba(148, 163, 184, 0.35);
            border-radius: 12px;
            padding: 18px;
            text-align: center;
            color: var(--muted);
        }

        @media (max-width: 980px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .filters {
                position: static;
            }

            .title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="header">
        <div>
            <h1 class="title">SmartNews</h1>
            <p class="subtitle">Лента новостей с фильтрами по категориям, тональности и важности.</p>
        </div>
    </div>

    <div class="grid">
        <aside class="panel filters">
            <h2>Фильтры</h2>
            <form id="filters-form">
                <div class="field">
                    <label for="q">Поиск</label>
                    <input id="q" name="q" type="text" placeholder="Ключевое слово">
                </div>

                <div class="field">
                    <label for="category">Категория</label>
                    <select id="category" name="category">
                        <option value="">Все</option>
                        <option value="it">IT</option>
                        <option value="economy">Экономика</option>
                        <option value="markets">Рынки</option>
                        <option value="politics">Политика</option>
                        <option value="crime">Криминал</option>
                        <option value="medicine">Медицина</option>
                        <option value="laravel">Laravel</option>
                    </select>
                </div>

                <div class="field">
                    <label for="important">Важность</label>
                    <select id="important" name="important">
                        <option value="">Все</option>
                        <option value="1">Только важные</option>
                        <option value="0">Только обычные</option>
                    </select>
                </div>

                <div class="field">
                    <label>Тональность</label>
                    <div class="range">
                        <input id="sentiment_min" name="sentiment_min" type="number" min="-10" max="10" placeholder="от -10">
                        <input id="sentiment_max" name="sentiment_max" type="number" min="-10" max="10" placeholder="до 10">
                    </div>
                </div>

                <div class="field">
                    <label>Диапазон дат</label>
                    <div class="range">
                        <input id="date_from" name="date_from" type="date">
                        <input id="date_to" name="date_to" type="date">
                    </div>
                </div>

                <div class="buttons">
                    <button class="btn-primary" type="submit">Применить</button>
                    <button class="btn-ghost" type="button" id="reset-btn">Сбросить</button>
                </div>
            </form>
        </aside>

        <main class="panel feed">
            <div class="status">
                <span id="status-text">Загрузка...</span>
                <span id="counter-text">0</span>
            </div>
            <div id="news-list" class="list"></div>
            <button id="load-more" class="load-more" type="button" hidden>Показать еще</button>
        </main>
    </div>
</div>

<script>
    const form = document.getElementById('filters-form');
    const resetBtn = document.getElementById('reset-btn');
    const listEl = document.getElementById('news-list');
    const statusEl = document.getElementById('status-text');
    const counterEl = document.getElementById('counter-text');
    const loadMoreEl = document.getElementById('load-more');

    let nextCursor = null;
    let loadedCount = 0;
    let currentFilters = readFiltersFromQuery();

    applyFiltersToForm(currentFilters);
    loadNews(true);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        currentFilters = getFiltersFromForm();
        writeFiltersToQuery(currentFilters);
        loadNews(true);
    });

    resetBtn.addEventListener('click', () => {
        form.reset();
        currentFilters = {};
        writeFiltersToQuery(currentFilters);
        loadNews(true);
    });

    loadMoreEl.addEventListener('click', () => loadNews(false));

    async function loadNews(reset) {
        if (reset) {
            nextCursor = null;
            loadedCount = 0;
            listEl.innerHTML = '';
            counterEl.textContent = '0';
        }

        const params = new URLSearchParams();
        params.set('per_page', '25');

        for (const [key, value] of Object.entries(currentFilters)) {
            if (value !== null && value !== undefined && String(value).trim() !== '') {
                params.set(key, String(value));
            }
        }

        if (!reset && nextCursor) {
            params.set('cursor', nextCursor);
        }

        statusEl.textContent = 'Загрузка...';
        loadMoreEl.hidden = true;

        try {
            const response = await fetch(`/api/news?${params.toString()}`, {
                headers: {'Accept': 'application/json'}
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            const items = Array.isArray(payload.data) ? payload.data : [];

            if (items.length === 0 && reset) {
                listEl.innerHTML = '<div class="empty">По текущим фильтрам ничего не найдено.</div>';
            } else {
                for (const item of items) {
                    listEl.append(createCard(item));
                }
            }

            loadedCount += items.length;
            counterEl.textContent = `${loadedCount} новостей`;
            statusEl.textContent = items.length > 0 ? 'Данные обновлены' : 'Больше новостей нет';

            nextCursor = payload?.meta?.next_cursor ?? null;
            loadMoreEl.hidden = !nextCursor;
        } catch (error) {
            statusEl.textContent = 'Ошибка загрузки ленты';
            loadMoreEl.hidden = true;
        }
    }

    function createCard(item) {
        const article = document.createElement('article');
        article.className = 'card';

        const cardTop = document.createElement('div');
        cardTop.className = 'card-top';

        const date = document.createElement('div');
        date.textContent = formatDate(item.published_at);

        const badges = document.createElement('div');
        badges.className = 'badges';
        if (item.important === true) {
            const importantBadge = document.createElement('span');
            importantBadge.className = 'badge badge-important';
            importantBadge.textContent = 'important';
            badges.append(importantBadge);
        }
        const sentimentBadge = document.createElement('span');
        sentimentBadge.className = 'badge';
        sentimentBadge.style.color = sentimentColor(item.sentiment);
        sentimentBadge.textContent = `sentiment: ${item.sentiment ?? 0}`;
        badges.append(sentimentBadge);

        cardTop.append(date, badges);
        article.append(cardTop);

        const title = document.createElement('h3');
        title.textContent = item.title_generated || item.title_original || 'Без заголовка';
        article.append(title);

        const text = document.createElement('p');
        text.textContent = truncate(item.content_translated || item.content_original || '', 900);
        article.append(text);

        const mediaWrap = buildMedia(item);
        if (mediaWrap !== null) {
            article.append(mediaWrap);
        }

        const meta = document.createElement('div');
        meta.className = 'meta';
        meta.innerHTML = `
            <span>${renderTags(item.tags)}</span>
            ${buildSourceLink(item)}
        `;
        article.append(meta);

        return article;
    }

    function buildMedia(item) {
        const mediaWrap = document.createElement('div');
        mediaWrap.className = 'media';

        const used = new Set();
        if (item.image_url) {
            const image = document.createElement('img');
            image.src = item.image_url;
            image.alt = item.title_generated || item.title_original || 'preview';
            image.loading = 'lazy';
            mediaWrap.append(image);
            used.add(item.image_url);
        }

        if (Array.isArray(item.media)) {
            for (const media of item.media.slice(0, 3)) {
                if (!media || !media.url || used.has(media.url)) {
                    continue;
                }

                const type = String(media.type || '');
                if (type.startsWith('video/')) {
                    const video = document.createElement('video');
                    video.src = media.url;
                    video.controls = true;
                    video.preload = 'none';
                    mediaWrap.append(video);
                    used.add(media.url);
                    continue;
                }

                if (type.startsWith('image/')) {
                    const image = document.createElement('img');
                    image.src = media.url;
                    image.alt = item.title_generated || item.title_original || 'preview';
                    image.loading = 'lazy';
                    mediaWrap.append(image);
                    used.add(media.url);
                }
            }
        }

        return mediaWrap.children.length > 0 ? mediaWrap : null;
    }

    function buildSourceLink(item) {
        const link = item?.source_metadata?.link;
        if (typeof link !== 'string' || link.trim() === '') {
            return '<span></span>';
        }

        return `<a href="${escapeHtml(link)}" target="_blank" rel="noopener noreferrer">источник</a>`;
    }

    function readFiltersFromQuery() {
        const params = new URLSearchParams(window.location.search);
        const filters = {};
        for (const key of ['q', 'category', 'important', 'sentiment_min', 'sentiment_max', 'date_from', 'date_to']) {
            const value = params.get(key);
            if (value !== null && value !== '') {
                filters[key] = value;
            }
        }

        return filters;
    }

    function writeFiltersToQuery(filters) {
        const params = new URLSearchParams();
        for (const [key, value] of Object.entries(filters)) {
            if (String(value).trim() !== '') {
                params.set(key, String(value));
            }
        }
        const query = params.toString();
        const url = query === '' ? window.location.pathname : `${window.location.pathname}?${query}`;
        window.history.replaceState({}, '', url);
    }

    function getFiltersFromForm() {
        const data = new FormData(form);
        const filters = {};
        for (const [key, value] of data.entries()) {
            if (String(value).trim() !== '') {
                filters[key] = value;
            }
        }

        return filters;
    }

    function applyFiltersToForm(filters) {
        for (const [key, value] of Object.entries(filters)) {
            const field = form.elements.namedItem(key);
            if (field) {
                field.value = value;
            }
        }
    }

    function renderTags(tags) {
        if (!Array.isArray(tags) || tags.length === 0) {
            return 'без тегов';
        }

        return tags.map((tag) => `#${escapeHtml(String(tag))}`).join(' ');
    }

    function truncate(value, length) {
        const text = String(value || '').trim();
        if (text.length <= length) {
            return text;
        }

        return `${text.slice(0, length - 1)}…`;
    }

    function sentimentColor(score) {
        if (Number(score) > 0) {
            return getComputedStyle(document.documentElement).getPropertyValue('--good');
        }
        if (Number(score) < 0) {
            return getComputedStyle(document.documentElement).getPropertyValue('--bad');
        }

        return '#cbd5e1';
    }

    function formatDate(value) {
        if (!value) {
            return 'дата неизвестна';
        }

        const date = new Date(value);
        if (Number.isNaN(date.getTime())) {
            return String(value);
        }

        return new Intl.DateTimeFormat('ru-RU', {
            dateStyle: 'medium',
            timeStyle: 'short'
        }).format(date);
    }

    function escapeHtml(value) {
        return value
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');
    }
</script>
</body>
</html>
