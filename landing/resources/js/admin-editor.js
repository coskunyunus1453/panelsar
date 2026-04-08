import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const toolbarOptions = [
    [{ header: [1, 2, 3, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    ['blockquote', 'code-block'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    [{ indent: '-1' }, { indent: '+1' }],
    ['link'],
    ['clean'],
];

function syncToTextarea(quill, textarea) {
    textarea.value = quill.getSemanticHTML();
}

function initRoot(root) {
    const textareaId = root.dataset.textareaId;
    if (!textareaId) {
        return;
    }

    const textarea = document.getElementById(textareaId);
    if (!textarea) {
        return;
    }

    const editorHost = root.querySelector('[data-quill-host]');
    if (!editorHost) {
        return;
    }

    const initial = textarea.value ?? '';

    const placeholder = root.dataset.placeholder?.trim() || undefined;

    const quill = new Quill(editorHost, {
        theme: 'snow',
        modules: {
            toolbar: toolbarOptions,
        },
        placeholder,
    });

    if (initial.trim() !== '') {
        const delta = quill.clipboard.convert({ html: initial });
        quill.setContents(delta, 'silent');
    }

    const form = textarea.closest('form');
    const runSync = () => syncToTextarea(quill, textarea);

    quill.on('text-change', runSync);
    if (form) {
        form.addEventListener('submit', runSync);
    }

    runSync();
}

export function initHvRichEditors() {
    document.querySelectorAll('[data-hv-quill]').forEach((el) => initRoot(el));
}
