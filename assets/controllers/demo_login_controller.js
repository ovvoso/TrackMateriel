import { Controller } from '@hotwired/stimulus';

/*
 * Instance de démonstration : un clic sur un compte de référence remplit
 * le formulaire de connexion et l'envoie.
 */
export default class extends Controller {
    static values = { password: String };

    use(event) {
        const form = this.element.closest('main').querySelector('form.auth-form');
        form.querySelector('[name="email"]').value = event.currentTarget.dataset.email;
        form.querySelector('[name="password"]').value = this.passwordValue;
        form.requestSubmit();
    }
}
