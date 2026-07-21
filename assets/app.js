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
});
