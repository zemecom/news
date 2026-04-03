<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SmartNews • Feed</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* iOS 26 Dark Theme Palette */
            --bg-deep: #000000;
            --bg-gradient-start: #0a0a0a;
            --bg-gradient-end: #121214;

            --glass-panel: rgba(30, 30, 35, 0.6);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-highlight: rgba(255, 255, 255, 0.03);

            --text-primary: #ffffff;
            --text-secondary: #a1a1aa;
            --text-tertiary: #52525b;

            --accent-primary: #0A84FF;
            /* System Blue */
            --accent-glow: rgba(10, 132, 255, 0.25);

            --radius-xl: 28px;
            --radius-lg: 20px;
            --radius-md: 14px;
            --radius-sm: 8px;

            --font-main: 'Outfit', sans-serif;

            --status-good: #32d74b;
            --status-bad: #ff453a;
        }

        * {
            box-sizing: border-box;
            outline: none;
        }

        body {
            margin: 0;
            font-family: var(--font-main);
            background-color: var(--bg-deep);
            background-image:
                radial-gradient(circle at 15% 0%, rgba(10, 132, 255, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 85% 100%, rgba(191, 90, 242, 0.08) 0%, transparent 40%);
            color: var(--text-primary);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* Ambient Glow effect */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: -1;
        }

        .shell {
            max-width: 1600px;
            margin: 0 auto;
            padding: 40px 24px;
        }

        /* Header Area */
        .header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 40px;
            padding: 0 12px;
        }

        .brand h1 {
            font-size: 42px;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, #fff 0%, #a1a1aa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.02em;
        }

        .brand p {
            margin: 8px 0 0;
            color: var(--text-secondary);
            font-size: 15px;
            font-weight: 400;
        }

        .stats-badge {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--glass-border);
            padding: 8px 16px;
            border-radius: 99px;
            font-size: 13px;
            color: var(--text-secondary);
            backdrop-filter: blur(10px);
        }

        .stats-badge strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        /* Layout Grid */
        .layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 32px;
            align-items: start;
        }

        /* Sidebar Filters */
        .filters {
            background: var(--glass-panel);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 24px;
            position: sticky;
            top: 40px;
        }

        .filters h3 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-tertiary);
            margin: 0 0 20px 0;
            font-weight: 600;
        }

        .filter-group {
            margin-bottom: 24px;
        }

        .filter-group label {
            display: block;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 10px;
            font-weight: 500;
        }

        .input-glass {
            width: 100%;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            padding: 12px 16px;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .input-glass:focus {
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 2px var(--accent-glow);
        }

        select.input-glass {
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23ffffff'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
            cursor: pointer;
        }

        input[type="date"].input-glass {
            color-scheme: dark;
            min-width: 0;
            padding-left: 10px;
            padding-right: 4px;
        }

        /* Darken calendar icon */
        ::-webkit-calendar-picker-indicator {
            filter: invert(1);
            opacity: 0.5;
            cursor: pointer;
        }

        .range-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .btn-group {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }

        .btn {
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-weight: 600;
            font-size: 14px;
            border-radius: var(--radius-md);
            padding: 12px 20px;
            transition: transform 0.1s active;
            letter-spacing: -0.01em;
            width: 100%;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn-primary {
            background: var(--accent-primary);
            color: #fff;
            box-shadow: 0 4px 20px var(--accent-glow);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
            border: 1px solid var(--glass-border);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* News Grid */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            /* 3 Columns as requested */
            gap: 24px;
        }

        .card {
            background: var(--glass-panel);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, border-color 0.3s ease;
            height: 100%;
        }

        .card:hover {
            transform: translateY(-4px);
            border-color: rgba(255, 255, 255, 0.15);
            box-shadow: 0 10px 40px -10px rgba(0, 0, 0, 0.5);
        }

        .card-preview {
            width: 100%;
            background: #1a1a1a;
            position: relative;
            overflow: hidden;
        }

        .card-preview a {
            display: block;
            width: 100%;
        }

        .card-preview img,
        .card-preview video {
            width: 100%;
            height: auto;
            display: block;
            transition: transform 0.3s ease, opacity 0.3s ease;
            cursor: zoom-in;
        }

        .card-preview:hover img {
            transform: scale(1.05);
        }

        .card-body {
            padding: 24px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 12px;
        }

        .date {
            color: var(--text-tertiary);
            font-weight: 500;
        }

        .badges {
            display: flex;
            gap: 6px;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 11px;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            border: 1px solid var(--glass-border);
        }

        .badge.important {
            background: rgba(255, 69, 58, 0.15);
            color: #ff453a;
            border-color: rgba(255, 69, 58, 0.3);
        }

        .badge.sentiment-good {
            color: var(--status-good);
            background: rgba(50, 215, 75, 0.1);
            border-color: rgba(50, 215, 75, 0.2);
        }

        .badge.sentiment-bad {
            color: var(--status-bad);
            background: rgba(255, 69, 58, 0.1);
            border-color: rgba(255, 69, 58, 0.2);
        }

        .card-title {
            font-size: 18px;
            line-height: 1.4;
            font-weight: 600;
            margin: 0 0 12px;
            color: var(--text-primary);
        }

        .card-text {
            font-size: 14px;
            line-height: 1.6;
            color: var(--text-secondary);
            margin: 0 0 20px;
            flex-grow: 1;
        }

        .card-footer {
            margin-top: auto;
            border-top: 1px solid var(--glass-border);
            padding-top: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .tags {
            font-size: 12px;
            color: var(--text-tertiary);
            max-width: 70%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .source-link a {
            color: var(--accent-primary);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: opacity 0.2s;
        }

        .source-link a:hover {
            opacity: 0.8;
        }

        .load-more-container {
            grid-column: 1 / -1;
            text-align: center;
            margin-top: 40px;
            padding-bottom: 40px;
        }

        .status-msg {
            text-align: center;
            grid-column: 1 / -1;
            padding: 40px;
            color: var(--text-secondary);
            font-size: 15px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .news-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 900px) {
            .layout {
                grid-template-columns: 1fr;
            }

            .filters {
                position: static;
                margin-bottom: 32px;
            }
        }

        @media (max-width: 600px) {
            .news-grid {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
        }
    </style>
</head>

<body>
    <div class="shell">
        <header class="header">
            <div class="brand">
                <h1>SmartNews</h1>
            </div>
            <div style="display: flex; gap: 12px; align-items: center;">
                <a href="/admin" class="btn btn-secondary"
                    style="padding: 8px 16px; font-size: 13px; text-decoration: none;">
                    Admin Panel
                </a>
                <div class="stats-badge">
                    Новостей: <strong id="total-articles">...</strong>
                </div>
            </div>
        </header>

        <div class="layout">
            <aside class="filters">
                <h3>Фильтры</h3>
                <form id="filters-form">
                    <div class="filter-group">
                        <label for="q">Поиск</label>
                        <input id="q" name="q" class="input-glass" type="text" placeholder="Ключевые слова...">
                    </div>

                    <div class="filter-group">
                        <label for="source_id">Источник</label>
                        <select id="source_id" name="source_id" class="input-glass">
                            <option value="">Все источники</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="category">Категория</label>
                        <select id="category" name="category" class="input-glass">
                            <option value="">Все категории</option>
                            <option value="it">Технологии (IT)</option>
                            <option value="economy">Экономика</option>
                            <option value="markets">Рынки</option>
                            <option value="politics">Политика</option>
                            <option value="crime">Криминал</option>
                            <option value="medicine">Медицина</option>
                            <option value="laravel">Laravel</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="important">Приоритет</label>
                        <select id="important" name="important" class="input-glass">
                            <option value="">Все новости</option>
                            <option value="1">Важные</option>
                            <option value="0">Обычные</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Тональность</label>
                        <div class="range-row">
                            <input id="sentiment_min" name="sentiment_min" class="input-glass" type="number" min="-10"
                                max="10" placeholder="Мин">
                            <input id="sentiment_max" name="sentiment_max" class="input-glass" type="number" min="-10"
                                max="10" placeholder="Макс">
                        </div>
                    </div>

                    <div class="filter-group">
                        <label>Период</label>
                        <div class="range-row">
                            <input id="date_from" name="date_from" class="input-glass" type="date" title="С даты">
                            <input id="date_to" name="date_to" class="input-glass" type="date" title="По дату">
                        </div>
                    </div>

                    <div class="btn-group">
                        <button class="btn btn-primary" type="submit">Применить</button>
                        <button class="btn btn-secondary" type="button" id="reset-btn">Сбросить</button>
                    </div>
                </form>
            </aside>

            <main>
                <div id="news-grid" class="news-grid">
                    <!-- Cards injected here -->
                </div>

                <div id="status-msg" class="status-msg">Загрузка ленты...</div>

                <div class="load-more-container">
                    <button id="load-more" class="btn btn-secondary" style="width: auto; padding: 12px 32px;"
                        type="button" hidden>Load More</button>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/fslightbox/3.4.1/index.min.js"></script>
    <script>
        const form = document.getElementById('filters-form');
        const resetBtn = document.getElementById('reset-btn');
        const gridEl = document.getElementById('news-grid');
        const statusEl = document.getElementById('status-msg');
        const totalEl = document.getElementById('total-articles');
        const loadMoreEl = document.getElementById('load-more');

        let isLoading = false;

        let nextCursor = null;
        let currentFilters = readFiltersFromQuery();

        applyFiltersToForm(currentFilters);
        loadSources();
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

        async function loadSources() {
            try {
                const res = await fetch('/api/sources', { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const payload = await res.json();
                const select = document.getElementById('source_id');
                for (const s of payload.data || []) {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = s.name;
                    select.append(opt);
                }
                // Restore selected source from filters
                if (currentFilters.source_id) {
                    select.value = currentFilters.source_id;
                }
            } catch (e) { console.error(e); }
        }

        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && nextCursor && !isLoading) {
                loadNews(false);
            }
        }, {
            rootMargin: '100px',
            threshold: 0.1
        });

        observer.observe(loadMoreEl);

        async function loadNews(reset) {
            if (isLoading) return;
            isLoading = true;

            if (reset) {
                nextCursor = null;
                gridEl.innerHTML = '';
                totalEl.textContent = '...';
                statusEl.hidden = false;
                statusEl.textContent = 'Обновление ленты...';
            }

            const params = new URLSearchParams();
            params.set('per_page', '24');

            for (const [key, value] of Object.entries(currentFilters)) {
                if (value !== null && value !== undefined && String(value).trim() !== '') {
                    params.set(key, String(value));
                }
            }

            if (!reset && nextCursor) {
                params.set('cursor', nextCursor);
            }

            loadMoreEl.hidden = true;

            try {
                const response = await fetch(`/api/news?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' }
                });

                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const payload = await response.json();
                const items = Array.isArray(payload.data) ? payload.data : [];

                if (payload.meta && payload.meta.total !== undefined) {
                    totalEl.textContent = new Intl.NumberFormat('ru-RU').format(payload.meta.total);
                }

                if (items.length === 0 && reset) {
                    statusEl.textContent = 'Новости не найдены.';
                    statusEl.hidden = false;
                } else {
                    statusEl.hidden = true;
                    for (const item of items) {
                        gridEl.append(createCard(item));
                    }
                    if (typeof refreshFsLightbox !== 'undefined') {
                        refreshFsLightbox();
                    }
                }

                nextCursor = payload?.meta?.next_cursor ?? null;

                if (nextCursor) {
                    loadMoreEl.hidden = false;
                    loadMoreEl.textContent = 'Загрузка...';
                } else {
                    loadMoreEl.hidden = true;
                }
            } catch (error) {
                console.error(error);
                statusEl.textContent = 'Ошибка загрузки ленты.';
                statusEl.hidden = false;
            } finally {
                isLoading = false;
            }
        }

        function createCard(item) {
            const article = document.createElement('article');
            article.className = 'card';

            // Preview Media — prefer local cached file, fallback to original URL
            const previewUrl = item.image_url || item.image_url_original || null;
            if (previewUrl) {
                const preview = document.createElement('div');
                preview.className = 'card-preview';

                const link = document.createElement('a');
                link.href = previewUrl;
                link.setAttribute('data-fslightbox', 'gallery');

                const img = document.createElement('img');
                img.src = previewUrl;
                img.loading = 'lazy';
                img.alt = '';

                const fallbackUrl = item.image_url_original && item.image_url_original !== previewUrl
                    ? item.image_url_original
                    : null;

                if (fallbackUrl) {
                    img.addEventListener('error', () => {
                        img.src = fallbackUrl;
                        link.href = fallbackUrl;
                    }, { once: true });
                }

                link.append(img);
                preview.append(link);
                article.append(preview);
            }

            // Body
            const body = document.createElement('div');
            body.className = 'card-body';

            // Meta Row
            const meta = document.createElement('div');
            meta.className = 'card-meta';

            const date = document.createElement('div');
            date.className = 'date';
            date.textContent = formatDate(item.published_at);

            const badges = document.createElement('div');
            badges.className = 'badges';

            if (item.important) {
                const imp = document.createElement('span');
                imp.className = 'badge important';
                imp.textContent = 'CORE';
                badges.append(imp);
            }

            const sent = document.createElement('span');
            sent.className = 'badge ' + (item.sentiment > 0 ? 'sentiment-good' : (item.sentiment < 0 ? 'sentiment-bad' : ''));
            sent.textContent = item.sentiment > 0 ? '+' + item.sentiment : item.sentiment;
            badges.append(sent);

            meta.append(date, badges);
            body.append(meta);

            // Title
            const titleText = item.title_generated || item.title_original || 'Без заголовка';
            const rawContent = item.content_translated || item.content_original || '';
            const contentText = cleanContent(rawContent);

            // Hide title only if it's exactly the same as content
            const isDuplicate = !item.title_generated && titleText === contentText;

            if (!isDuplicate) {
                const title = document.createElement('h3');
                title.className = 'card-title';
                title.textContent = titleText;
                body.append(title);
            }

            // Text
            const text = document.createElement('p');
            text.className = 'card-text';
            text.textContent = contentText;
            body.append(text);

            // Footer
            const footer = document.createElement('div');
            footer.className = 'card-footer';

            const tags = document.createElement('div');
            tags.className = 'tags';
            tags.textContent = renderTags(item.tags);

            const linkDiv = document.createElement('div');
            linkDiv.className = 'source-link';

            if (item.source_metadata?.link) {
                const a = document.createElement('a');
                a.href = item.source_metadata.link;
                a.target = '_blank';
                a.rel = 'noopener';
                a.textContent = (item.source_name || 'Источник') + ' →';
                linkDiv.append(a);
            }

            footer.append(tags, linkDiv);
            body.append(footer);

            article.append(body);
            return article;
        }

        function readFiltersFromQuery() {
            const params = new URLSearchParams(window.location.search);
            const filters = {};
            for (const key of ['q', 'category', 'important', 'sentiment_min', 'sentiment_max', 'date_from', 'date_to', 'source_id']) {
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
                if (field) field.value = value;
            }
        }

        function renderTags(tags) {
            if (!Array.isArray(tags) || tags.length === 0) return '';
            return tags.map(t => `#${t}`).join(' ');
        }

        function truncate(str, n) {
            return (str.length > n) ? str.slice(0, n - 1) + '…' : str;
        }

        function cleanContent(text) {
            if (!text) return '';
            // Remove Telegram subscription spam like "👉 Топор Live. Подписаться"
            return text.replace(/\s*👉.*(?:Подписаться|подписаться).*$/s, '').trim();
        }

        function formatDate(value) {
            if (!value) return '';
            const date = new Date(value);
            return new Intl.DateTimeFormat('ru-RU', {
                day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'
            }).format(date);
        }
    </script>
</body>

</html>
