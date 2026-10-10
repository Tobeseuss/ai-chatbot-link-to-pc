/* AI-PC Link — اسکریپت پنل مدیریت */
(function () {
        'use strict';

        function copyText(text, btn, doneLabel) {
                var fallback = function () {
                        var ta = document.createElement('textarea');
                        ta.value = text;
                        ta.style.position = 'fixed';
                        ta.style.opacity = '0';
                        document.body.appendChild(ta);
                        ta.select();
                        try { document.execCommand('copy'); } catch (e) { /* ignore */ }
                        document.body.removeChild(ta);
                        flash(btn, doneLabel);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(text).then(function () {
                                flash(btn, doneLabel);
                        }, fallback);
                } else {
                        fallback();
                }
        }

        function flash(btn, label) {
                var old = btn.textContent;
                btn.textContent = label || 'کپی شد ✓';
                setTimeout(function () { btn.textContent = old; }, 2000);
        }

        document.addEventListener('DOMContentLoaded', function () {
                // تایید عملیات خطرناک.
                document.querySelectorAll('.aclp-confirm').forEach(function (btn) {
                        btn.addEventListener('click', function (e) {
                                var msg = btn.getAttribute('data-confirm') || 'مطمئن هستید؟';
                                if (!window.confirm(msg)) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                }
                        });
                });

                // کپی از محتوای یک عنصر (code / pre).
                document.querySelectorAll('.aclp-copy-key').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                                var target = document.getElementById(btn.getAttribute('data-target'));
                                if (!target) { return; }
                                copyText(target.textContent.trim(), btn);
                        });
                });

                // کپی از value یک input (مثل PAT در تنظیمات).
                document.querySelectorAll('.aclp-copy-val').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                                var target = document.getElementById(btn.getAttribute('data-target'));
                                if (!target || !('value' in target)) { return; }
                                copyText(target.value.trim(), btn);
                        });
                });

                // داشبورد: انتخاب روش (کامل/A/B/C — v2.3.0) + انتخاب کلید API و بازسازی متن اصل ۴.
                var pre  = document.getElementById('aclp-agent-prompt-text');
                var sel  = document.getElementById('aclp-prompt-key-select');
                var msel = document.getElementById('aclp-prompt-method-select');
                if (pre && sel && msel) {
                        var PH = 'PASTE_YOUR_REAL_API_KEY_HERE';
                        var PH_BLOCK_RE = /- API key:\s+PASTE_YOUR_REAL_API_KEY_HERE[\s\S]*?Never guess or invent credentials\./;
                        var render = function () {
                                var tpl = document.getElementById('aclp-agent-prompt-template-' + msel.value);
                                if (!tpl) { return; }
                                var text = tpl.textContent;
                                var key = sel.value;
                                if (key) {
                                        var match = text.match(PH_BLOCK_RE);
                                        if (match) {
                                                text = text.replace(PH_BLOCK_RE, '- API key:  ' + key + '   (already provided — use it exactly as it appears in the URLs/headers below)');
                                        }
                                        // جای‌گذاری کلید واقعی در همه URLها و مثال‌ها.
                                        text = text.split(PH).join(key);
                                }
                                pre.textContent = text;
                        };
                        sel.addEventListener('change', render);
                        msel.addEventListener('change', render);
                        render();
                }
        });
})();
