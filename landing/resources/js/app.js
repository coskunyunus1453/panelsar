import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

if (document.querySelector('[data-hv-quill]')) {
    import('./admin-editor.js').then((m) => m.initHvRichEditors());
}
