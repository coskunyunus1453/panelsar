import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

if (document.querySelector('[data-admin-quill]')) {
    import('./admin-editor.js').then((m) => m.initAdminRichEditors());
}
