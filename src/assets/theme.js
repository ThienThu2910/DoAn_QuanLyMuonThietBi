/* ==========================================================================
   Alt F4 Hub — hành vi giao diện dùng chung
   Sáng/tối · menu mobile · chuyển trang · hiệu ứng click · hộp thoại xác nhận ·
   kiểm tra tệp upload · hiện/ẩn mật khẩu · thông báo · bảng dạng thẻ trên mobile.
   Không thay đổi logic PHP / form gốc: mọi thứ được "gắn thêm" lúc chạy.
   ========================================================================== */
(function () {
    'use strict';

    var KEY = 'altf4-theme';
    var VIEW_KEY = 'altf4-view';
    var root = document.documentElement;
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- 1. THEME: chạy ngay trong <head> để không bị chớp sáng ---------- */
    function stored(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function save(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
    function systemTheme() { return matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
    function applyTheme(t) {
        root.setAttribute('data-theme', t);
        root.setAttribute('data-bs-theme', t);
        var m = document.querySelector('meta[name="theme-color"]');
        if (m) m.setAttribute('content', t === 'dark' ? '#0F1A15' : '#F5F8F6');
    }
    applyTheme(stored(KEY) || systemTheme());
    try {
        matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            if (!stored(KEY)) applyTheme(systemTheme());
        });
    } catch (e) { /* trình duyệt cũ */ }

    function setTheme(next, origin) {
        function commit() { applyTheme(next); save(KEY, next); }
        if (!document.startViewTransition || reduce) {
            root.classList.add('t-theme-anim');
            commit();
            setTimeout(function () { root.classList.remove('t-theme-anim'); }, 450);
            return;
        }
        var x = origin.x, y = origin.y;
        var r = Math.hypot(Math.max(x, innerWidth - x), Math.max(y, innerHeight - y));
        var vt = document.startViewTransition(commit);
        vt.ready.then(function () {
            root.animate(
                { clipPath: ['circle(0px at ' + x + 'px ' + y + 'px)', 'circle(' + r + 'px at ' + x + 'px ' + y + 'px)'] },
                { duration: 640, easing: 'cubic-bezier(.22,.8,.2,1)', pseudoElement: '::view-transition-new(root)' }
            );
        }).catch(function () {});
    }

    /* ---------- 1b. THANH BÊN: nhớ trạng thái thu gọn / mở (áp dụng sớm để không giật) ---------- */
    var SIDE_KEY = 'altf4-side';
    if (stored(SIDE_KEY) === 'collapsed') root.classList.add('t-side-collapsed');

    /* ---------- tiện ích ---------- */
    function $(s, c) { return (c || document).querySelector(s); }
    function $$(s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); }
    function mk(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html) e.innerHTML = html; return e; }
    function safe(fn) { try { fn(); } catch (e) { if (window.console) console.warn('[theme]', e); } }

    var SVG = {
        eye: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>',
        eyeOff: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.9 17.9A10.9 10.9 0 0 1 12 19c-6.4 0-10-7-10-7a18 18 0 0 1 5.1-5.9M9.9 5.2A9.9 9.9 0 0 1 12 5c6.4 0 10 7 10 7a18 18 0 0 1-2.2 3.2M1 1l22 22M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>',
        warn: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/></svg>',
        help: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/></svg>',
        file: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>',
        sun: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="19" height="19"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>',
        moon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="19" height="19"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>'
    };

    /* ---------- 2. NÚT ĐỔI GIAO DIỆN (nút có sẵn trong markup: [data-theme-toggle]) ---------- */
    function initToggle() {
        var btns = $$('[data-theme-toggle]');
        if (!btns.length) {                                     // trang không có khung → thêm nút nổi
            var fab = mk('button', 't-icon-btn t-theme-btn t-theme-fab t-ui', SVG.sun + SVG.moon);
            fab.type = 'button'; fab.setAttribute('data-theme-toggle', '');
            fab.setAttribute('aria-label', 'Đổi giao diện sáng / tối');
            document.body.appendChild(fab);
            btns = [fab];
        }
        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var r = btn.getBoundingClientRect();
                var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                setTheme(next, { x: r.left + r.width / 2, y: r.top + r.height / 2 });
            });
        });
    }

    /* ---------- 3. MENU MOBILE (drawer) ---------- */
    function initDrawer() {
        var side = $('#tSide');
        if (!side) return;
        document.body.classList.add('has-shell');
        var opener = null;
        function open(btn) {
            opener = btn || null;
            root.classList.add('t-drawer-open');
            var first = $('.t-nav', side); if (first) setTimeout(function () { first.focus({ preventScroll: true }); }, 60);
        }
        function close() {
            if (!root.classList.contains('t-drawer-open')) return;
            root.classList.remove('t-drawer-open');
            if (opener && opener.focus) opener.focus({ preventScroll: true });
        }
        document.addEventListener('click', function (e) {
            var o = e.target.closest('[data-drawer-open]');
            if (o) { e.preventDefault(); open(o); return; }
            if (e.target.closest('[data-drawer-close]') || (root.classList.contains('t-drawer-open') && e.target.closest('#tSide a'))) close();
        });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
        window.addEventListener('resize', function () { if (innerWidth > 1023) root.classList.remove('t-drawer-open'); });
        window.addEventListener('pageshow', function () { root.classList.remove('t-drawer-open'); });
    }

    /* ---------- 3b. THANH BÊN: nút tay nắm thu gọn / mở rộng (máy tính) ---------- */
    function initSidebar() {
        var btn = $('[data-side-toggle]');
        if (!btn) return;
        function sync() {
            var collapsed = root.classList.contains('t-side-collapsed');
            btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            btn.setAttribute('aria-label', collapsed ? 'Mở rộng thanh bên' : 'Thu gọn thanh bên');
        }
        btn.addEventListener('click', function () {
            var on = root.classList.toggle('t-side-collapsed');
            save(SIDE_KEY, on ? 'collapsed' : 'open');
            sync();
            btn.blur();                                   // để thanh bên không bị giữ mở bởi :focus-within
        });
        sync();
    }

    /* ---------- 3c. LIQUID GLASS: vệt sáng bám theo con trỏ trên thẻ kính ---------- */
    function initGlass() {
        if (reduce || !window.matchMedia || !matchMedia('(hover: hover)').matches) return;
        var SEL = '.device-card, .lg-glass, .stat, .metric-card, .lg-tile, .lg-hero';
        document.addEventListener('pointermove', function (e) {
            var el = e.target.closest && e.target.closest(SEL);
            if (!el) return;
            var r = el.getBoundingClientRect();
            el.style.setProperty('--mx', (e.clientX - r.left) + 'px');
            el.style.setProperty('--my', (e.clientY - r.top) + 'px');
        }, { passive: true });
    }

    /* ---------- 4. CHUYỂN TRANG ---------- */
    function sameOriginNav(a) {
        if (!a || !a.href || a.target === '_blank' || a.hasAttribute('download')) return false;
        var raw = a.getAttribute('href') || '';
        if (raw.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(raw)) return false;
        if (a.origin !== location.origin) return false;
        if (/export|view_file|download|logout/i.test(a.pathname + a.search)) return false;   // tải tệp / đăng xuất: không chặn
        if (a.hasAttribute('data-no-transition')) return false;
        return true;
    }
    function initLeave() {
        document.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
            var a = e.target.closest('a');
            if (!sameOriginNav(a)) return;
            e.preventDefault();
            root.classList.add('t-leave');
            var go = function () { location.href = a.href; };
            reduce ? go() : setTimeout(go, 190);
        });
        window.addEventListener('pageshow', function (e) {          // quay lại bằng nút Back (bfcache)
            if (e.persisted) {
                root.classList.remove('t-leave');
                $$('.t-loading').forEach(function (b) { b.classList.remove('t-loading'); b.disabled = false; });
            }
        });
    }

    /* ---------- 5. HIỆU ỨNG CLICK (ripple) ---------- */
    function initRipple() {
        var SEL = 'button, .btn, [class*="btn-"], .secondary, .tab-btn, .action-chip, .t-nav, .category-tabs a, .pager a, .demo-chip';
        document.addEventListener('pointerdown', function (e) {
            if (reduce || e.button !== 0) return;
            var el = e.target.closest(SEL);
            if (!el || el.disabled) return;
            var pos = getComputedStyle(el).position;
            if (pos === 'absolute' || pos === 'fixed' || pos === 'sticky') return;
            var r = el.getBoundingClientRect();
            var size = Math.max(r.width, r.height) * 2.2;
            var s = mk('span', 't-ripple');
            s.style.cssText = 'width:' + size + 'px;height:' + size + 'px;left:' + (e.clientX - r.left - size / 2) + 'px;top:' + (e.clientY - r.top - size / 2) + 'px';
            el.classList.add('t-rippling');
            el.appendChild(s);
            setTimeout(function () { s.remove(); if (!el.querySelector('.t-ripple')) el.classList.remove('t-rippling'); }, 700);
        }, { passive: true });
    }

    /* ---------- 6. HỘP THOẠI XÁC NHẬN thay cho confirm() ---------- */
    var bypass = false;
    function openConfirm(message) {
        return new Promise(function (resolve) {
            var danger = /xóa|hủy|từ chối|đặt lại|reset/i.test(message);
            var back = mk('div', 't-dialog-backdrop t-ui');
            var box = mk('div', 't-dialog' + (danger ? ' is-danger' : ''));
            box.setAttribute('role', 'alertdialog');
            box.setAttribute('aria-modal', 'true');
            var ic = mk('div', 't-dialog-icon', danger ? SVG.warn : SVG.help);
            var h = mk('h3'); h.textContent = danger ? 'Bạn chắc chắn chứ?' : 'Xác nhận thao tác';
            var p = mk('p'); p.textContent = message;
            var act = mk('div', 't-dialog-actions');
            var no = mk('button'); no.type = 'button'; no.textContent = 'Quay lại';
            var ok = mk('button', 't-ok'); ok.type = 'button';
            ok.textContent = /xóa/i.test(message) ? 'Xóa' : /hủy/i.test(message) ? 'Hủy phiếu' : /từ chối/i.test(message) ? 'Từ chối'
                : /đặt lại|reset/i.test(message) ? 'Đặt lại' : /duyệt/i.test(message) ? 'Duyệt' : 'Đồng ý';
            act.appendChild(no); act.appendChild(ok);
            [ic, h, p, act].forEach(function (n) { box.appendChild(n); });
            back.appendChild(box);
            document.body.appendChild(back);
            var prev = document.activeElement;
            ok.focus();

            function close(val) {
                document.removeEventListener('keydown', onKey, true);
                back.classList.add('is-closing');
                setTimeout(function () { back.remove(); if (prev && prev.focus) prev.focus(); resolve(val); }, reduce ? 0 : 160);
            }
            function onKey(e) {
                if (e.key === 'Escape') { e.preventDefault(); close(false); }
                else if (e.key === 'Tab') { e.preventDefault(); (document.activeElement === ok ? no : ok).focus(); }
            }
            document.addEventListener('keydown', onKey, true);
            ok.addEventListener('click', function () { close(true); });
            no.addEventListener('click', function () { close(false); });
            back.addEventListener('mousedown', function (e) { if (e.target === back) close(false); });
        });
    }
    function initConfirm() {
        document.addEventListener('click', function (e) {
            if (bypass) return;
            var el = e.target.closest('[onclick*="confirm("]');
            if (!el) return;
            var m = /confirm\(\s*(['"])([\s\S]*?)\1\s*\)/.exec(el.getAttribute('onclick') || '');
            if (!m) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            openConfirm(m[2]).then(function (yes) {
                if (!yes) return;
                var orig = window.confirm;
                bypass = true;
                window.confirm = function () { return true; };      // chạy lại đúng handler gốc, đã được xác nhận
                try { el.click(); } finally { window.confirm = orig; bypass = false; }
            });
        }, true);
    }

    /* ---------- 7. FORM: trạng thái đang gửi ---------- */
    function initForms() {
        document.addEventListener('submit', function (e) {
            if (e.defaultPrevented) return;
            var f = e.target;
            if (!f || (f.method || '').toLowerCase() !== 'post' || f.hasAttribute('data-no-loader')) return;
            var btn = e.submitter || $('[type="submit"]', f);
            if (!btn) return;
            btn.classList.add('t-loading');
            setTimeout(function () { btn.disabled = true; }, 0);   // sau khi trình duyệt đã lấy dữ liệu form
            setTimeout(function () { btn.classList.remove('t-loading'); btn.disabled = false; }, 20000);
        });
    }

    /* ---------- 8. UPLOAD: kiểm tra định dạng / dung lượng + xem trước ---------- */
    function fmtSize(b) { return b < 1024 ? b + ' B' : b < 1048576 ? (b / 1024).toFixed(1) + ' KB' : (b / 1048576).toFixed(2) + ' MB'; }
    function initFiles() {
        var MAX = 2 * 1024 * 1024;                                  // khớp khuyến nghị < 2MB của hệ thống
        $$('input[type="file"]').forEach(function (inp) {
            var box = mk('div', 't-file-info'); box.hidden = true;
            inp.insertAdjacentElement('afterend', box);
            var url = null;
            inp.addEventListener('change', function () {
                if (url) { URL.revokeObjectURL(url); url = null; }
                box.textContent = ''; box.className = 't-file-info';
                var f = inp.files && inp.files[0];
                if (!f) { box.hidden = true; return; }
                var ext = (f.name.split('.').pop() || '').toLowerCase();
                var accept = (inp.getAttribute('accept') || '').split(',').map(function (a) { return a.trim().toLowerCase(); }).filter(Boolean);
                var okType = !accept.length || accept.some(function (a) {      // hỗ trợ ".pdf", "image/*", "image/png"
                    if (a.charAt(0) === '.') return a.slice(1) === ext;
                    if (a.slice(-2) === '/*') return (f.type || '').indexOf(a.slice(0, -1)) === 0;
                    return f.type === a;
                });
                var err = '';
                if (!okType) err = 'Định dạng không được hỗ trợ. Chỉ nhận: ' + accept.map(function (a) { return a.charAt(0) === '.' ? a.slice(1).toUpperCase() : (a === 'image/*' ? 'ẢNH' : a); }).join(', ') + '.';
                else if (f.size > MAX) err = 'Tệp ' + fmtSize(f.size) + ' vượt giới hạn 2 MB. Hãy chọn tệp nhỏ hơn.';
                var text = mk('div'), name = mk('b'), meta = mk('span');
                name.textContent = f.name;
                meta.textContent = err || fmtSize(f.size);
                text.appendChild(name); text.appendChild(meta);
                if (!err && /^image\//.test(f.type)) { url = URL.createObjectURL(f); var im = mk('img'); im.src = url; im.alt = ''; box.appendChild(im); }
                else box.insertAdjacentHTML('beforeend', SVG.file);
                box.appendChild(text);
                if (err) { box.classList.add('is-error'); inp.value = ''; }
                box.hidden = false;
            });
        });
    }

    /* ---------- 9. MẬT KHẨU: hiện / ẩn ---------- */
    function initPasswords() {
        $$('input[type="password"]').forEach(function (inp) {
            var wrap = mk('span', 't-pass');
            inp.parentNode.insertBefore(wrap, inp);
            wrap.appendChild(inp);
            var b = mk('button', 't-pass-btn', SVG.eye);
            b.type = 'button'; b.tabIndex = -1; b.setAttribute('aria-label', 'Hiện mật khẩu');
            b.addEventListener('click', function () {
                var show = inp.type === 'password';
                inp.type = show ? 'text' : 'password';
                b.innerHTML = show ? SVG.eyeOff : SVG.eye;
                b.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
            });
            wrap.appendChild(b);
            b.style.height = (inp.offsetHeight || 44) + 'px';
        });
    }

    /* ---------- 10. THÔNG BÁO ---------- */
    function toast(msg) {
        var wrap = $('.t-toasts');
        if (!wrap) { wrap = mk('div', 't-toasts t-ui'); wrap.setAttribute('aria-live', 'polite'); document.body.appendChild(wrap); }
        var t = mk('div', 't-toast');
        var span = mk('span'); span.textContent = msg;
        var x = mk('button', null, '&times;'); x.type = 'button'; x.setAttribute('aria-label', 'Đóng');
        t.appendChild(span); t.appendChild(x); wrap.appendChild(t);
        function hide() { t.classList.add('is-leaving'); setTimeout(function () { t.remove(); }, 260); }
        x.addEventListener('click', hide);
        setTimeout(hide, 5200);
    }
    window.altf4Toast = toast;
    function initToasts() {
        document.addEventListener('click', function (e) {
            var b = e.target.closest('[data-toast]');
            if (b) toast(b.getAttribute('data-toast'));
        });
    }
    function initFlash() {
        var sel = '.msg-toast, .alert, .alert-success, .alert-danger, .alert-error, [style*="D1FAE5" i][style*="margin-bottom" i]';
        $$(sel).forEach(function (el) {
            if (el.classList.contains('status-pill') || el.closest('.t-flash') || el.classList.contains('t-flash')) return;
            var isError = /alert-danger|alert-error/.test(el.className) || /FEE2E2/i.test(el.getAttribute('style') || '');
            el.classList.add('t-flash');
            var x = mk('button', 't-flash-x', '&times;');
            x.type = 'button'; x.setAttribute('aria-label', 'Đóng thông báo');
            el.appendChild(x);
            function hide() { el.classList.add('is-leaving'); setTimeout(function () { el.remove(); }, 450); }
            x.addEventListener('click', hide);
            if (!isError && !el.querySelector('a')) setTimeout(hide, 6500);   // lỗi và thông báo có liên kết thì giữ lại
        });
    }

    /* ---------- 11. TRANG KHO: kiểu xem lưới / danh sách, điền nhanh tài khoản demo ---------- */
    function initCatalog() {
        var grid = $('#devices');
        var tg = $$('.view-toggle button');
        if (grid && tg.length) {
            function setView(v) {
                grid.classList.toggle('is-list', v === 'list');
                tg.forEach(function (b) {
                    var on = b.getAttribute('data-view') === v;
                    b.classList.toggle('selected', on);
                    b.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
            }
            setView(stored(VIEW_KEY) === 'list' ? 'list' : 'grid');
            tg.forEach(function (b) {
                b.addEventListener('click', function () { var v = b.getAttribute('data-view'); save(VIEW_KEY, v); setView(v); });
            });
        }
        $$('[data-fill-email]').forEach(function (b) {
            b.addEventListener('click', function () {
                var f = b.closest('.auth-card') || document;
                var e = $('input[name="email"]', f), p = $('input[name="password"]', f);
                if (e) e.value = b.getAttribute('data-fill-email');
                if (p) p.value = b.getAttribute('data-fill-pass');
                if (p) p.focus();
            });
        });
    }

    /* ---------- 12. CHI TIẾT NHỎ ---------- */
    function initMotion() {
        [['.devices > .device-card', 't-stagger'], ['.stats > *', 't-stagger'], ['.metrics-grid > *', 't-stagger'],
         ['.grid-2 > *', 't-stagger'], ['.quick-actions > *', 't-stagger'], ['.lg-fan > *', 't-stagger'], ['.lg-tiles > *', 't-stagger'], ['tbody > tr', 't-stagger-row']].forEach(function (g) {
            $$(g[0]).forEach(function (el, i) { el.style.setProperty('--t-i', i); el.classList.add(g[1]); });
        });
        if (!reduce) $$('.stat > strong, .metric-num').forEach(function (el) {     // số liệu đếm lên
            var txt = el.textContent.trim();
            if (!/^\d+$/.test(txt)) return;
            var to = parseInt(txt, 10), t0 = performance.now(), dur = 800;
            if (to === 0) return;
            (function tick(t) {
                var p = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(to * e);
                if (p < 1) requestAnimationFrame(tick);
            })(t0);
        });
        $$('table').forEach(function (t) {                                       // bảng: cuộn ngang + dạng thẻ trên mobile
            if (!t.parentNode.classList.contains('t-table-wrap')) {
                var w = mk('div', 't-table-wrap'); t.parentNode.insertBefore(w, t); w.appendChild(t);
            }
            var heads = $$('thead th', t).map(function (th) { return th.textContent.trim(); });
            if (!heads.length) return;
            $$('tbody tr', t).forEach(function (tr) {
                $$(':scope > td', tr).forEach(function (td, i) { if (!td.hasAttribute('colspan')) td.setAttribute('data-label', heads[i] || ''); });
            });
            t.classList.add('t-stack');
        });
        var bar = mk('div', 't-progress t-ui'); document.body.appendChild(bar);   // thanh tiến trình cuộn
        var tick = 0;
        function onScroll() {
            if (tick) return;
            tick = requestAnimationFrame(function () {
                tick = 0;
                var h = root.scrollHeight - innerHeight;
                bar.style.setProperty('--p', h > 0 ? Math.min(1, scrollY / h) : 0);
            });
        }
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
        document.addEventListener('keydown', function (e) {                       // "/" hoặc Ctrl/⌘+K để tìm kiếm, Esc đóng modal duyệt mượn
            var tag = (document.activeElement && document.activeElement.tagName) || '';
            var s = $('input[name="search"]');
            if (s && ((e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(tag)) || ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k'))) {
                e.preventDefault(); s.focus(); s.select();
            }
            if (e.key === 'Escape' && typeof window.closeModals === 'function') safe(window.closeModals);
        });
    }

    /* ---------- KHỞI ĐỘNG ---------- */
    function boot() {
        [initToggle, initDrawer, initSidebar, initGlass, initLeave, initRipple, initConfirm, initForms, initFiles,
         initPasswords, initToasts, initFlash, initCatalog, initMotion].forEach(safe);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
