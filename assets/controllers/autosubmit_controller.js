import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    initialize() {
        this.submit = this.debounce(this.submit.bind(this), 300);
    }

    submit() {
        this.element.requestSubmit();
    }

    debounce(func, wait) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }
}