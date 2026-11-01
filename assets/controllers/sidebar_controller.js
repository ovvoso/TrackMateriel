import { Controller } from '@hotwired/stimulus';

/*
 * Ouvre et ferme la navigation latérale sur petit écran.
 */
export default class extends Controller {
    static targets = ['toggle'];

    open() {
        this.element.classList.add('is-nav-open');
        this.toggleTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.element.classList.remove('is-nav-open');
        this.toggleTarget.setAttribute('aria-expanded', 'false');
    }

    closeOnEscape(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }
}
