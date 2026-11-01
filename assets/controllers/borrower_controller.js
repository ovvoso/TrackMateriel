import { Controller } from '@hotwired/stimulus';

/*
 * Formulaire de prêt : affiche les champs du compte ou ceux d'une personne
 * sans compte, selon le type d'emprunteur choisi.
 */
export default class extends Controller {
    static targets = ['account', 'person'];

    connect() {
        this.toggle();
    }

    toggle() {
        const checked = this.element.querySelector('input[name$="[borrowerType]"]:checked');
        const isPerson = checked !== null && checked.value === 'person';

        this.accountTargets.forEach((row) => { row.hidden = isPerson; });
        this.personTargets.forEach((row) => { row.hidden = !isPerson; });
    }
}
