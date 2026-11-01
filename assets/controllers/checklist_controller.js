import { Controller } from '@hotwired/stimulus';

/*
 * Liste de cases à cocher filtrable au clavier, avec le compte des éléments cochés.
 */
export default class extends Controller {
    static targets = ['filter', 'item', 'group', 'count', 'empty'];

    connect() {
        this.update();
    }

    filter() {
        const terms = this.filterTarget.value.trim().toLowerCase().split(/\s+/).filter(Boolean);

        this.itemTargets.forEach((item) => {
            const text = item.dataset.search ?? item.textContent.toLowerCase();
            item.hidden = !terms.every((term) => text.includes(term));
        });

        this.groupTargets.forEach((group) => {
            group.hidden = group.querySelectorAll('[data-checklist-target="item"]:not([hidden])').length === 0;
        });

        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = this.itemTargets.some((item) => !item.hidden);
        }
    }

    update() {
        if (!this.hasCountTarget) {
            return;
        }

        const count = this.itemTargets.filter((item) => item.querySelector('input:checked')).length;
        this.countTarget.textContent = count === 0 ? 'Aucun équipement choisi' : `${count} équipement${count > 1 ? 's' : ''} choisi${count > 1 ? 's' : ''}`;
    }
}
