// Vorlagen-Auswahl: befüllt das Antwort-Textfeld mit dem gerenderten Vorlagentext.
document.addEventListener('DOMContentLoaded', function () {
  var templateSelect = document.getElementById('template_select');
  var replyBody = document.getElementById('reply_body');

  if (templateSelect && replyBody && window.TEMPLATES) {
    templateSelect.addEventListener('change', function () {
      var tpl = window.TEMPLATES[templateSelect.value];
      if (tpl) {
        replyBody.value = tpl;
      }
    });
  }

  // Umschalten zwischen "Antwort an Kunde" und "Interne Notiz"
  var replyTabBtn = document.getElementById('tab-reply-btn');
  var noteTabBtn = document.getElementById('tab-note-btn');
  var replyForm = document.getElementById('reply-form');
  var noteForm = document.getElementById('note-form');

  function showReply() {
    replyForm.style.display = 'block';
    noteForm.style.display = 'none';
    replyTabBtn.classList.add('active-reply');
    noteTabBtn.classList.remove('active-note');
  }
  function showNote() {
    replyForm.style.display = 'none';
    noteForm.style.display = 'block';
    noteTabBtn.classList.add('active-note');
    replyTabBtn.classList.remove('active-reply');
  }

  if (replyTabBtn && noteTabBtn) {
    replyTabBtn.addEventListener('click', showReply);
    noteTabBtn.addEventListener('click', showNote);
  }

  // Löschen-Buttons in der Administration bestätigen lassen
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('submit', function (e) {
      if (!confirm(el.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  // Mobile Sidebar ein-/ausblenden
  var sidebarToggle = document.getElementById('sidebarToggle');
  var sidebar = document.getElementById('appSidebar');
  var backdrop = document.getElementById('sidebarBackdrop');

  function closeSidebar() {
    sidebar.classList.remove('is-open');
    backdrop.classList.remove('is-open');
    sidebarToggle.setAttribute('aria-expanded', 'false');
  }
  function openSidebar() {
    sidebar.classList.add('is-open');
    backdrop.classList.add('is-open');
    sidebarToggle.setAttribute('aria-expanded', 'true');
  }

  if (sidebarToggle && sidebar && backdrop) {
    sidebarToggle.addEventListener('click', function () {
      if (sidebar.classList.contains('is-open')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });
    backdrop.addEventListener('click', closeSidebar);
  }
});
