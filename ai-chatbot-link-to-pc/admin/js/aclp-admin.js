/* AI-PC Link — اسکریپت پنل مدیریت */
(function () {
	'use strict';

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

		// کپی کلید API.
		document.querySelectorAll('.aclp-copy-key').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var target = document.getElementById(btn.getAttribute('data-target'));
				if (!target) { return; }
				var text = target.textContent.trim();
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(function () {
						btn.textContent = 'کپی شد ✓';
						setTimeout(function () { btn.textContent = 'کپی'; }, 2000);
					});
				} else {
					var range = document.createRange();
					range.selectNodeContents(target);
					var sel = window.getSelection();
					sel.removeAllRanges();
					sel.addRange(range);
					document.execCommand('copy');
					btn.textContent = 'کپی شد ✓';
					setTimeout(function () { btn.textContent = 'کپی'; }, 2000);
				}
			});
		});
	});
})();
