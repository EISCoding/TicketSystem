// Initialisiert einen Quill-Rich-Text-Editor über einem versteckten Formularfeld:
// füllt ihn beim Start mit dessen aktuellem Wert und schreibt beim Absenden des
// umgebenden Formulars das erzeugte HTML zurück in das Feld (das der Server liest).
function initRichEditor(editorId, hiddenFieldId, placeholder) {
  var container = document.getElementById(editorId);
  var hidden = document.getElementById(hiddenFieldId);
  if (!container || !hidden || typeof Quill === 'undefined') {
    return null;
  }

  var quill = new Quill(container, {
    theme: 'snow',
    placeholder: placeholder || '',
    modules: {
      toolbar: [
        ['bold', 'italic', 'underline'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['blockquote', 'link'],
        ['clean'],
      ],
    },
  });

  if (hidden.value) {
    // dangerouslyPasteHTML (statt root.innerHTML) laesst Quill das HTML in sein
    // eigenes Delta-Modell einlesen - direktes innerHTML-Setzen haelt Quill nicht
    // synchron und formatiert bei der naechsten Eingabe unter Umstaenden falsch.
    quill.clipboard.dangerouslyPasteHTML(hidden.value);
  }

  var form = hidden.closest('form');
  if (form) {
    form.addEventListener('submit', function () {
      hidden.value = quill.root.innerHTML;
    });
  }

  return quill;
}

document.addEventListener('DOMContentLoaded', function () {
  // Antwort-Editor (ticket.php) - Vorlagen-Auswahl befüllt ihn mit dem gerenderten Vorlagentext.
  var replyQuill = initRichEditor('reply_body_editor', 'reply_body', 'Antwort schreiben...');

  var templateSelect = document.getElementById('template_select');
  if (templateSelect && replyQuill && window.TEMPLATES) {
    templateSelect.addEventListener('change', function () {
      var tpl = window.TEMPLATES[templateSelect.value];
      if (tpl) {
        replyQuill.clipboard.dangerouslyPasteHTML(tpl);
      }
    });
  }

  // Vorlagen-Editor (admin/templates.php)
  initRichEditor('body_editor', 'body', 'Text der Vorlage...');

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
