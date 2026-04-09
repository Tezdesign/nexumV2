import { Controller } from '@hotwired/stimulus';
import { useDebounce } from 'stimulus-use';

export default class extends Controller {
    static debounces = ['submit'];

    connect() {

        useDebounce(this, { wait: 300 });
    }

    submit() {
        this.element.requestSubmit();
    }
}