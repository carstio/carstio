import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    toggle() {
        document.getElementById('appSidebar').classList.toggle('show');
        document.getElementById('sidebarBackdrop').classList.toggle('show');
    }

    close() {
        document.getElementById('appSidebar').classList.remove('show');
        document.getElementById('sidebarBackdrop').classList.remove('show');
    }
}
